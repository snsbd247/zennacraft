<?php

namespace App\Modules\Order\Services;

use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerIntelligenceService;
use App\Modules\Finance\Services\AccountService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\VariantInventoryService;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\RTO\Services\CustomerRiskService;
use App\Modules\Shared\Services\PhoneService;
use App\Modules\Shared\Support\OperationalStanding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderManagementService
{
    public function __construct(
        private InventoryService $inventoryService,
        private VariantInventoryService $variantInventoryService,
        private CustomerIntelligenceService $customerIntelligenceService,
        private CustomerRiskService $customerRiskService,
        private PhoneService $phoneService,
        private CommunicationService $communicationService,
        private MarketingSegmentEngineService $marketingSegmentEngine,
        private AccountService $accountService,
    ) {}

    protected const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'returned',
    ];

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The one place the Orders list's filter set (status/phone/order
     * number/search/source/courier/date range) is applied — any future
     * Orders list/export UI should build on this rather than
     * re-implementing its own filter logic.
     */
    public function filteredQuery(array $filters = [])
    {
        return Order::query()
            ->with([
                'customer.segment',
                'customer.riskProfile',
                'customer.blacklistEntries',
                'items.product.thumbnail',
                'items.variant',
                'shipment.courierProvider.metric',
                'shipment.trackingEvents',
                'sourceLandingPage',
                'orderNotes.staffUser',
                'riskProfile',
                'fraudEvents',
            ])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['phone'] ?? null, fn ($query, $phone) => $this->applyPhoneFilter($query, (string) $phone))
            ->when($filters['order_number'] ?? null, fn ($query, $orderNumber) => $query->where('order_number', 'like', '%'.$orderNumber.'%'))
            ->when($filters['search'] ?? null, fn ($query, $search) => $this->applySearchFilter($query, (string) $search))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['district'] ?? null, fn ($query, $district) => $query->where('district', $district))
            ->when($filters['approve_admin'] ?? null, fn ($query, $adminId) => $query->where('verified_by', $adminId))
            ->when($filters['creator'] ?? null, fn ($query, $creator) => $creator === 'admin'
                ? $query->where('source', 'custom')
                : $query->whereIn('source', ['website', 'landing', 'whatsapp']))
            ->when($filters['courier'] ?? null, fn ($query, $courierId) => $query->whereHas(
                'shipment',
                fn ($shipmentQuery) => $shipmentQuery->where('courier_provider_id', $courierId)
            ))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest();
    }

    public function find(int $id): ?Order
    {
        return Order::with([
            'customer.segment',
            'customer.riskProfile',
            'customer.blacklistEntries',
            'coupon',
            'couponUsage',
            'items.product.thumbnail',
            'items.variant',
            'riskProfile',
            'riskHoldReleasedBy',
            'statusHistories.staffUser',
            'verificationAttempts.staffUser',
            'orderNotes.staffUser',
            'fraudEvents',
            'customerCommunications.staffUser',
            'communicationMessages',
            'shipment.courierProvider.metric',
            'shipment.trackingEvents',
            'trackingEvents',
        ])
            ->find($id);
    }

    public function updateStatus(Order $order, string $status, ?string $note = null, ?string $rtoReason = null): Order
    {
        OperationalStanding::assertActive();

        if (! in_array($status, $this->allowedStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected order status is invalid.',
            ]);
        }

        return DB::transaction(function () use ($order, $status, $note, $rtoReason) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            $previousStatus = $order->status;

            if ($previousStatus === $status) {
                return $order->refresh()->load(['customer', 'items', 'statusHistories.staffUser']);
            }

            if ($status === 'confirmed') {
                $this->reserveInventory($order);
            }

            if (in_array($status, ['cancelled', 'returned'], true)) {
                $this->releaseInventory($order);
            }

            $attributes = ['status' => $status];

            if ($status === 'returned' && $rtoReason !== null) {
                $attributes['rto_reason'] = $rtoReason;
            }

            $order->update($attributes);

            $order->statusHistories()->create([
                'previous_status' => $previousStatus,
                'new_status' => $status,
                'notes' => $note,
                'staff_user_id' => auth()->guard('staff')->id(),
            ]);

            $customer = $this->customerIntelligenceService->syncFromOrder($order);

            if (in_array($status, ['delivered', 'cancelled', 'returned'], true)) {
                $this->recalculateCustomerRisk($customer, 'order_status:'.$status);
            }

            if ($status === 'delivered') {
                $this->accountService->recordDeliveredOrderCredit($order);
            }

            $this->evaluateMarketingSegments($customer, 'order_status:'.$status);
            $this->createOrderCommunication($order, $status);
            $this->triggerAutomation($order, $previousStatus, $status);

            return $order->refresh()->load(['customer', 'items', 'statusHistories.staffUser']);
        });
    }

    public function allowedStatuses(): array
    {
        return self::STATUSES;
    }

    protected function reserveInventory(Order $order): void
    {
        if ($order->inventory_reserved_at) {
            return;
        }

        foreach ($order->items as $item) {
            $this->ensureStockAvailable($item);
        }

        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $variant = ProductVariant::lockForUpdate()->find($item->variant_id);
                $previousStock = (int) $variant->stock;
                $newStock = $previousStock - (int) $item->quantity;

                $variant->update(['stock' => $newStock]);
                $this->variantInventoryService->adjustStock(
                    $variant,
                    $newStock,
                    'Order confirmed: '.$order->order_number,
                    $previousStock
                );

                continue;
            }

            $product = Product::lockForUpdate()->find($item->product_id);
            $previousStock = (int) $product->stock;
            $newStock = $previousStock - (int) $item->quantity;

            $product->update(['stock' => $newStock]);
            $this->inventoryService->adjustStock(
                $product,
                $newStock,
                'Order confirmed: '.$order->order_number,
                $previousStock
            );
        }

        $order->forceFill(['inventory_reserved_at' => now()])->save();
    }

    protected function applyPhoneFilter($query, string $phone): void
    {
        $normalized = $this->phoneService->normalize($phone);

        if ($normalized === '') {
            return;
        }

        $values = $this->phoneService->lookupValues($normalized) ?: [$normalized];

        $query->where(function ($phoneQuery) use ($values): void {
            foreach ($values as $value) {
                $phoneQuery->orWhere('customer_phone', 'like', '%'.$value.'%');
            }
        });
    }

    protected function applySearchFilter($query, string $search): void
    {
        $normalizedPhone = $this->phoneService->normalize($search);
        $phoneValues = $normalizedPhone !== '' ? ($this->phoneService->lookupValues($normalizedPhone) ?: [$normalizedPhone]) : [];

        $query->where(function ($searchQuery) use ($search, $phoneValues): void {
            $searchQuery->where('order_number', 'like', '%'.$search.'%')
                ->orWhere('customer_name', 'like', '%'.$search.'%');

            foreach ($phoneValues as $value) {
                $searchQuery->orWhere('customer_phone', 'like', '%'.$value.'%');
            }
        });
    }

    protected function releaseInventory(Order $order): void
    {
        if (! $order->inventory_reserved_at || $order->inventory_released_at) {
            return;
        }

        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $variant = ProductVariant::lockForUpdate()->find($item->variant_id);
                $previousStock = (int) $variant->stock;
                $newStock = $previousStock + (int) $item->quantity;

                $variant->update(['stock' => $newStock]);
                $this->variantInventoryService->adjustStock(
                    $variant,
                    $newStock,
                    'Order inventory restored: '.$order->order_number,
                    $previousStock
                );

                continue;
            }

            $product = Product::lockForUpdate()->find($item->product_id);
            $previousStock = (int) $product->stock;
            $newStock = $previousStock + (int) $item->quantity;

            $product->update(['stock' => $newStock]);
            $this->inventoryService->adjustStock(
                $product,
                $newStock,
                'Order inventory restored: '.$order->order_number,
                $previousStock
            );
        }

        $order->forceFill(['inventory_released_at' => now()])->save();
    }

    protected function ensureStockAvailable(object $item): void
    {
        if ($item->variant_id) {
            $variant = ProductVariant::lockForUpdate()->find($item->variant_id);

            if (! $variant || (int) $variant->stock < (int) $item->quantity) {
                throw ValidationException::withMessages([
                    'status' => 'Not enough stock is available to confirm this order.',
                ]);
            }

            return;
        }

        $product = Product::lockForUpdate()->find($item->product_id);

        if (! $product || (int) $product->stock < (int) $item->quantity) {
            throw ValidationException::withMessages([
                'status' => 'Not enough stock is available to confirm this order.',
            ]);
        }
    }

    protected function recalculateCustomerRisk(Customer $customer, string $trigger): void
    {
        try {
            $this->customerRiskService->recalculateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Customer RTO risk recalculation failed', [
                'customer_id' => $customer->id ?? null,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function createOrderCommunication(Order $order, string $status): void
    {
        try {
            $this->communicationService->generateOrderStatusMessage($order->refresh(), $status);
        } catch (Throwable $exception) {
            logger()->warning('Order communication automation failed', [
                'order_id' => $order->id,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function evaluateMarketingSegments(Customer $customer, string $trigger): void
    {
        try {
            $this->marketingSegmentEngine->evaluateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment order evaluation failed', [
                'customer_id' => $customer->id ?? null,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function triggerAutomation(Order $order, string $previousStatus, string $newStatus): void
    {
        try {
            app(AutomationEngineService::class)->handleTrigger('order.status_changed', $order->refresh(), [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Order status automation trigger failed', [
                'order_id' => $order->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'error' => $exception->getMessage(),
            ]);
        }
    }

}
