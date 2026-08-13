<?php

namespace App\Modules\Courier\Services;

use App\Modules\Courier\Exceptions\CourierApiException;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Courier\Services\Api\CourierApiManager;
use App\Modules\Finance\Services\FinanceService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\RTO\Services\OrderRiskService;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourierService
{
    protected const SHIPMENT_STATUSES = [
        'pending',
        'assigned',
        'shipped',
        'delivered',
        'returned',
        'cancelled',
        'failed',
    ];

    protected const PROVIDER_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private OrderManagementService $orderManagementService,
        private CourierPerformanceService $performanceService,
        private TrackingService $trackingService,
        private FinanceService $financeService,
        private OrderRiskService $orderRiskService,
        private CourierApiManager $apiManager,
    ) {}

    public function activeProviders(): Collection
    {
        return CourierProvider::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * The Orders list "Confirm" action: assign a courier and move the order
     * straight to Shipped in one step. Pure orchestration of two existing,
     * already-transactional methods — assignShipment() only ever creates the
     * shipment as "assigned"; updateShipment() is what actually transitions
     * shipment status to "shipped" and (via its own syncOrderStatus() call)
     * moves the order to "shipped" too, exactly like the existing order-show
     * page's shipment tracking panel already does it one step at a time.
     *
     * Deliberately NOT wrapped in an outer DB::transaction() — assignShipment()
     * makes a live API call (courier push) after its own inner transaction
     * commits; nesting that inside another open transaction here would hold
     * the order's row lock for the entire duration of that network call.
     * Each step already commits its own consistent state on its own.
     */
    public function assignAndDispatch(Order $order, array $data): Shipment
    {
        $shipment = $this->assignShipment($order, $data);

        return $this->updateShipment($shipment, ['status' => 'shipped']);
    }

    /**
     * "Mark Shipment" from the order actions: guarantee a courier entry exists
     * and dispatch it in one step. If no courier is assigned yet, auto-assign
     * the recommended provider (falling back to the best / first active one) so
     * a shipment + tracking event is always created and the order moves to
     * Shipped. If no courier provider is configured at all, the order still
     * moves to Shipped (without a courier record) rather than failing.
     */
    public function shipWithAutoCourier(Order $order, ?string $note = null): Order
    {
        $shipment = Shipment::where('order_id', $order->id)->first();

        if (! $shipment || ! $shipment->courier_provider_id) {
            $provider = $this->performanceService->recommendProviderForOrder($order)
                ?? $this->activeProviders()->first();

            if ($provider) {
                $shipment = $this->assignShipment($order, [
                    'courier_provider_id' => $provider->id,
                    'notes' => $note,
                ]);
            }
        }

        if ($shipment && $shipment->courier_provider_id) {
            if ($shipment->status !== 'shipped') {
                // updateShipment()->syncOrderStatus() moves the order to Shipped
                // and records the tracking event.
                $this->updateShipment($shipment, ['status' => 'shipped', 'notes' => $note]);
            }

            return $order->refresh();
        }

        return $this->orderManagementService->updateStatus($order, 'shipped', $note);
    }

    /**
     * Reflect a delivered/returned outcome through the courier so the shipment,
     * its timestamps, the tracking timeline and the order status all move
     * together. Used when the order already has a courier shipment; a direct
     * mark (no shipment) is handled by the plain order-status flow instead.
     */
    public function markShipmentOutcome(Shipment $shipment, string $status, ?string $note = null): Shipment
    {
        return $this->updateShipment($shipment, ['status' => $status, 'notes' => $note]);
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Shipment::query()
            ->with(['order', 'courierProvider'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['courier_provider_id'] ?? null, fn ($query, $providerId) => $query->where('courier_provider_id', $providerId))
            ->when($filters['tracking_number'] ?? null, fn ($query, $trackingNumber) => $query->where('tracking_number', 'like', '%'.$trackingNumber.'%'))
            ->when($filters['order_number'] ?? null, function ($query, $orderNumber) {
                $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%'.$orderNumber.'%'));
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function assignShipment(Order $order, array $data): Shipment
    {
        if ($order->risk_hold_status === 'active') {
            throw ValidationException::withMessages([
                'courier_provider_id' => 'This order has an active risk hold and cannot be assigned to a courier until the hold is cleared from the RTO risk holds screen.',
            ]);
        }

        $oldProviderId = null;

        $shipment = DB::transaction(function () use ($order, $data, &$oldProviderId) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->risk_hold_status === 'active') {
                throw ValidationException::withMessages([
                    'courier_provider_id' => 'This order has an active risk hold and cannot be assigned to a courier until the hold is cleared from the RTO risk holds screen.',
                ]);
            }

            $shipment = Shipment::where('order_id', $order->id)->lockForUpdate()->first();
            $oldProviderId = $shipment?->courier_provider_id;

            $payload = [
                'courier_provider_id' => $data['courier_provider_id'] ?? null,
                // Preserve whatever's already on the shipment when the caller
                // doesn't send a real value — re-submitting the assign form
                // (e.g. to change courier_cost) must not wipe a tracking
                // number/consignment id a previous API push already saved.
                'tracking_number' => filled($data['tracking_number'] ?? null) ? $data['tracking_number'] : $shipment?->tracking_number,
                'consignment_id' => filled($data['consignment_id'] ?? null) ? $data['consignment_id'] : $shipment?->consignment_id,
                'status' => 'assigned',
                'delivery_charge' => $data['delivery_charge'] ?? $shipment?->delivery_charge ?? 0,
                'cod_amount' => $data['cod_amount'] ?? $shipment?->cod_amount ?? $order->total,
                'courier_cost' => $data['courier_cost'] ?? $shipment?->courier_cost,
                'assigned_at' => $shipment?->assigned_at ?? now(),
                'notes' => $data['notes'] ?? $shipment?->notes,
            ];

            if ($shipment) {
                $shipment->update($payload);
            } else {
                $shipment = Shipment::create($payload + ['order_id' => $order->id]);
            }

            return $shipment->refresh()->load(['order.items', 'courierProvider']);
        });

        $this->recalculateMetricsForProviderIds([$oldProviderId, $shipment->courier_provider_id]);
        $this->syncFinanceForOrder($shipment->order);
        $this->createAutoTrackingEvent($shipment, 'assigned');
        $this->recalculateOrderRisk($shipment->order, 'shipment_assigned');

        // No consignment id yet (the courier's own order id — only ever set by
        // a successful API push) — if this provider has a live API client
        // configured and enabled (Studio -> Settings -> Courier API Setup),
        // push the order to them automatically. This fires even if staff
        // typed something into the tracking-number field: that field is a
        // plain text box on the assign form with no way to signal "don't
        // push", and a successful push overwrites it with the courier's real
        // tracking code anyway (see pushToProviderApi() below). Best-effort:
        // a failed push never blocks the assignment itself, exactly like the
        // other post-assignment side effects above — the shipment stays
        // assigned and can be pushed again (or filled in manually) from the
        // Courier screen.
        if (blank($shipment->consignment_id)) {
            try {
                $shipment = $this->pushToProviderApi($shipment);
            } catch (CourierApiException $exception) {
                logger()->warning('Courier API auto-push failed', [
                    'shipment_id' => $shipment->id,
                    'courier_provider_id' => $shipment->courier_provider_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $shipment;
    }

    /** Whether this provider slug has a configured + enabled live API client — lets a caller phrase its own feedback message correctly. */
    public function hasLiveApiClient(?string $providerSlug): bool
    {
        return $this->apiManager->clientFor($providerSlug) !== null;
    }

    /**
     * Push a shipment's order to its assigned provider's live API and save
     * the returned tracking number / consignment id. Throws on failure (bad
     * credentials, rejected order, unmatched address) — callers that want
     * to keep going regardless (like the auto-push in assignShipment()
     * above) must catch CourierApiException themselves; a caller acting on
     * an explicit staff action (a "Push to courier" button) should let it
     * propagate so the real reason reaches the screen.
     */
    public function pushToProviderApi(Shipment $shipment): Shipment
    {
        $shipment->loadMissing(['order.items', 'courierProvider']);
        $client = $this->apiManager->clientFor($shipment->courierProvider?->slug);

        if (! $client) {
            throw new CourierApiException('This courier has no live API connected — enable it in Studio -> Settings -> Courier API Setup, or enter the tracking number manually.');
        }

        $result = $client->createOrder($shipment);

        $shipment->update([
            'tracking_number' => $result['tracking_number'] ?: $shipment->tracking_number,
            'consignment_id' => $result['consignment_id'] ?: $shipment->consignment_id,
        ]);

        return $shipment->fresh(['order.items', 'courierProvider']);
    }

    /**
     * Pull the provider's current status for an already-pushed shipment and
     * fold it through the normal updateShipment() path so the order status,
     * tracking timeline and finance sync all move together exactly as they
     * would from a manual status change. Returns the shipment unchanged
     * (no exception) when the provider's status doesn't map to one of ours
     * or hasn't changed — there's nothing to apply.
     */
    public function syncStatusFromProviderApi(Shipment $shipment): Shipment
    {
        $shipment->loadMissing(['order.items', 'courierProvider']);
        $client = $this->apiManager->clientFor($shipment->courierProvider?->slug);

        if (! $client) {
            throw new CourierApiException('This courier has no live API connected — enable it in Studio -> Settings -> Courier API Setup.');
        }

        $result = $client->trackOrder($shipment);
        $status = $result['status'] ?? null;

        if ($status === null || $status === $shipment->status) {
            return $shipment;
        }

        return $this->updateShipment($shipment, ['status' => $status]);
    }

    public function updateShipment(Shipment $shipment, array $data): Shipment
    {
        $oldProviderId = null;

        $shipment = DB::transaction(function () use ($shipment, $data, &$oldProviderId) {
            $shipment = Shipment::with(['order.items', 'courierProvider'])->lockForUpdate()->findOrFail($shipment->id);
            $previousStatus = $shipment->status;
            $oldProviderId = $shipment->courier_provider_id;
            $payload = [];

            foreach (['courier_provider_id', 'tracking_number', 'consignment_id', 'delivery_charge', 'cod_amount', 'courier_cost', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            if (array_key_exists('status', $data) && filled($data['status'])) {
                if (! in_array($data['status'], self::SHIPMENT_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'status' => 'The selected shipment status is invalid.',
                    ]);
                }

                $payload['status'] = $data['status'];
                $this->applyShipmentTimestamp($shipment, $payload['status'], $payload);
            }

            if ($payload !== []) {
                $shipment->update($payload);
            }

            $shipment->refresh()->load(['order.items', 'courierProvider']);

            if (($payload['status'] ?? $previousStatus) !== $previousStatus) {
                $this->syncOrderStatus($shipment, $payload['status']);
            }

            return $shipment->refresh()->load(['order.items', 'courierProvider']);
        });

        $this->recalculateMetricsForProviderIds([$oldProviderId, $shipment->courier_provider_id]);

        if (array_key_exists('courier_cost', $data)) {
            $this->syncFinanceForOrder($shipment->order);
        }

        if (array_key_exists('status', $data) && filled($data['status'])) {
            $this->createAutoTrackingEvent($shipment, $shipment->status);
        }

        $this->recalculateOrderRisk($shipment->order, 'shipment_updated');

        return $shipment;
    }

    public function shipmentStatuses(): array
    {
        return self::SHIPMENT_STATUSES;
    }

    public function providerStatuses(): array
    {
        return self::PROVIDER_STATUSES;
    }

    protected function applyShipmentTimestamp(Shipment $shipment, string $status, array &$payload): void
    {
        if ($status === 'shipped' && ! $shipment->shipped_at) {
            $payload['shipped_at'] = now();
        }

        if ($status === 'delivered' && ! $shipment->delivered_at) {
            $payload['delivered_at'] = now();
        }

        if ($status === 'returned' && ! $shipment->returned_at) {
            $payload['returned_at'] = now();
        }
    }

    protected function syncOrderStatus(Shipment $shipment, string $shipmentStatus): void
    {
        $orderStatus = match ($shipmentStatus) {
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
            'pending', 'assigned', 'failed' => null,
            default => null,
        };

        if ($orderStatus) {
            $this->orderManagementService->updateStatus($shipment->order, $orderStatus, 'Shipment status updated to '.$shipmentStatus);
        }
    }

    protected function recalculateMetricsForProviderIds(array $providerIds): void
    {
        foreach (array_filter(array_unique($providerIds)) as $providerId) {
            try {
                $provider = CourierProvider::find($providerId);

                if ($provider) {
                    $this->performanceService->recalculateProvider($provider);
                }
            } catch (Throwable $exception) {
                logger()->warning('Courier metric recalculation failed', [
                    'courier_provider_id' => $providerId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    protected function syncFinanceForOrder(Order $order): void
    {
        try {
            $this->financeService->syncCourierCostForOrder($order);
        } catch (Throwable $exception) {
            logger()->warning('Order finance courier cost sync failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function createAutoTrackingEvent(Shipment $shipment, string $shipmentStatus): void
    {
        $trackingEvent = $this->trackingEventForShipmentStatus($shipmentStatus);

        if (! $trackingEvent) {
            return;
        }

        try {
            $alreadyExists = $shipment->trackingEvents()
                ->where('status', $trackingEvent['status'])
                ->exists();

            if ($alreadyExists) {
                return;
            }

            $this->trackingService->createEvent($shipment, $trackingEvent);
        } catch (Throwable $exception) {
            logger()->warning('Automatic shipment tracking event failed', [
                'shipment_id' => $shipment->id,
                'shipment_status' => $shipmentStatus,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function trackingEventForShipmentStatus(string $shipmentStatus): ?array
    {
        return match ($shipmentStatus) {
            'assigned' => [
                'status' => 'assigned',
                'title' => 'Shipment assigned',
            ],
            'shipped' => [
                'status' => 'in_transit',
                'title' => 'Shipment in transit',
            ],
            'delivered' => [
                'status' => 'delivered',
                'title' => 'Delivered',
            ],
            'returned' => [
                'status' => 'returned',
                'title' => 'Returned',
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'title' => 'Shipment cancelled',
            ],
            'failed' => [
                'status' => 'failed',
                'title' => 'Delivery failed',
            ],
            default => null,
        };
    }

    protected function recalculateOrderRisk(Order $order, string $trigger): void
    {
        try {
            $this->orderRiskService->recalculateOrder($order, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Order RTO risk recalculation after shipment change failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
