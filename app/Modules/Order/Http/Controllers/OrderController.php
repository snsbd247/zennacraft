<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Courier\Services\CourierService;
use App\Modules\Fraud\Services\CustomerFraudService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Services\OrderEditService;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\Verification\Services\OrderVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const PER_PAGE_OPTIONS = [20, 50, 100];

    // Preset order-comment options for the note popup on Manage Order.
    public const COMMENT_PRESETS = [
        'Product Return',
        'Phone Off !!',
        'Cancel Order By Customer',
        'Cash On Delivery',
        "Customer Doesn't Pick Up The Call",
    ];

    private ?Collection $activeCouriersCache = null;

    public function __construct(
        private OrderManagementService $orderManagementService,
        private OrderVerificationService $verificationService,
        private MediaService $mediaService,
        private CourierService $courierService,
        private CustomerFraudService $customerFraudService,
        private OrderEditService $orderEditService,
    ) {}

    /**
     * "Manage Order" — the filterable order list.
     */
    public function index(Request $request): View|\Illuminate\Http\Response
    {
        $filters = $request->only(['status', 'phone', 'order_number', 'search', 'source', 'district', 'approve_admin', 'creator', 'courier', 'date_from', 'date_to']);

        $perPage = (int) $request->query('per_page', 50);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 50;
        }

        $orders = $this->orderManagementService->paginate($filters, $perPage);

        // Cheap extra loads the shared filteredQuery doesn't already do —
        // who verified/approved the order for the Activities column.
        $orders->getCollection()->loadMissing(['verifiedBy:id,name', 'verificationAttempts']);

        // Live search / pagination: return just the results region (no reload).
        if ($request->ajax()) {
            return response()->view('studio.orders.partials._results', [
                'orders' => $orders,
                'filters' => $filters,
                'perPage' => $perPage,
                'couriers' => $this->activeCouriers(),
                'commentPresets' => self::COMMENT_PRESETS,
                'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
            ]);
        }

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('studio.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => $this->orderManagementService->allowedStatuses(),
            'statusCounts' => $statusCounts,
            'totalOrders' => (int) $statusCounts->sum(),
            'couriers' => $this->activeCouriers(),
            'commentPresets' => self::COMMENT_PRESETS,
            'verificationOutcomes' => $this->verificationService->outcomeOptions(),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
            // Filter dropdown option sources.
            'districts' => Order::query()->whereNotNull('district')->distinct()->orderBy('district')->pluck('district'),
            'orderSources' => Order::SOURCES,
            'approveAdmins' => \App\Modules\AdminAuth\Models\StaffUser::query()
                ->whereIn('id', Order::query()->whereNotNull('verified_by')->distinct()->pluck('verified_by'))
                ->orderBy('name')
                ->get(['id', 'name']),
            // Baseline for the live "new orders" watcher — the newest order id
            // at page load; the poller counts anything newer than this.
            'latestOrderId' => (int) Order::query()->max('id'),
        ]);
    }

    /**
     * Lightweight poll for the Manage Order list's live new-order watcher.
     * Returns how many orders are newer than `after` (the id the page last
     * knew about) plus the newest one's headline info — no HTML, cheap query.
     */
    public function newCheck(Request $request): JsonResponse
    {
        $after = (int) $request->query('after', 0);
        $latest = (int) Order::query()->max('id');

        $count = $after > 0 ? Order::query()->where('id', '>', $after)->count() : 0;
        $order = null;
        if ($count > 0) {
            $newest = Order::query()->where('id', '>', $after)->latest('id')->first(['order_number', 'customer_name', 'total']);
            if ($newest) {
                $order = ['number' => $newest->order_number, 'customer' => $newest->customer_name, 'total' => (float) $newest->total];
            }
        }

        return response()->json(['count' => $count, 'latest_id' => $latest, 'order' => $order]);
    }

    /**
     * Records a call-verification attempt for an order (the Call
     * Verification column on the Manage Order list). Reuses the standalone
     * Verification module's own service so a "verified" outcome runs the
     * exact same confirm-order + risk-recalc flow it always has.
     */
    public function verify(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        try {
            $this->verificationService->recordAttempt(
                $order,
                $validated['outcome'],
                $validated['notes'] ?? null,
                $validated['next_follow_up_at'] ?? null,
            );
        } catch (ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }

        // recordAttempt() mutates a locked copy inside its transaction, so the
        // bound $order stays stale — without this refresh the swapped row would
        // still render as "unverified", making it look like the first click did
        // nothing (the two-click bug).
        $order->refresh();

        if ($request->wantsJson()) {
            return $this->rowResponse($order, 'Call verification recorded for '.$order->order_number.'.');
        }

        return back()->with('success', 'Call verification recorded for '.$order->order_number.'.');
    }

    /**
     * Assigns a courier to an order from the Manage Order courier popup.
     */
    public function assignCourier(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'courier_provider_id' => ['required', 'integer', 'exists:courier_providers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $shipment = $this->courierService->assignShipment($order, [
                'courier_provider_id' => $validated['courier_provider_id'],
                'tracking_number' => $validated['tracking_number'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }

        [$flashKey, $message] = $this->courierAssignMessage($order, $shipment);

        if ($request->wantsJson()) {
            return $this->rowResponse($order, $message);
        }

        return back()->with($flashKey, $message);
    }

    /**
     * Tells staff what actually happened — plain "assigned" for a manual
     * courier, or whether the live API push (see CourierService::
     * assignShipment()) succeeded or failed, so a silent API failure never
     * looks identical to success in the UI.
     *
     * @return array{0: string, 1: string} flash key ('success'|'error'), message
     */
    protected function courierAssignMessage(Order $order, Shipment $shipment): array
    {
        $providerSlug = $shipment->courierProvider?->slug;
        $providerName = $shipment->courierProvider?->name ?? 'the courier';
        $base = 'Courier assigned to '.$order->order_number;

        if (! $this->courierService->hasLiveApiClient($providerSlug)) {
            return ['success', $base.'.'];
        }

        if (filled($shipment->consignment_id)) {
            return ['success', $base.' and pushed to '.$providerName.' (tracking: '.$shipment->tracking_number.').'];
        }

        return ['error', $base.', but the push to '.$providerName.' failed — check the Courier screen to retry or verify the API credentials in Settings.'];
    }

    /**
     * Order-comment popup on Manage Order — a preset comment and/or a
     * free-text note, saved as an order note.
     */
    public function storeComment(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $parts = array_filter([$validated['preset'] ?? null, $validated['note'] ?? null], fn ($v) => filled($v));
        $text = trim(implode(' — ', $parts));

        if ($text === '') {
            $exception = ValidationException::withMessages(['preset' => 'Select a comment or write one.']);
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }

        $order->orderNotes()->create([
            'note' => $text,
            'staff_user_id' => auth()->guard('staff')->id(),
        ]);

        if ($request->wantsJson()) {
            return $request->boolean('detail')
                ? $this->detailNotesResponse($order, 'Note added.')
                : $this->rowResponse($order->refresh(), 'Comment added to '.$order->order_number.'.');
        }

        return back()->with('success', 'Comment added to '.$order->order_number.'.');
    }

    /**
     * "Ip Block" action — blacklists the order's phone so future
     * checkouts from it are refused (reuses the fraud module's service).
     */
    public function block(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $this->customerFraudService->blacklistCustomer(
            $order->customer,
            $order->customer_phone,
            'Blocked from Manage Order — '.$order->order_number,
        );

        if ($request->wantsJson()) {
            return $this->rowResponse($order, $order->customer_phone.' blocked.');
        }

        return back()->with('success', $order->customer_phone.' blocked.');
    }

    /**
     * Courier "fraud checker" popup — this phone's real order history
     * with us, broken down per courier with a delivery success rate.
     */
    public function fraudCheck(Request $request): JsonResponse
    {
        $phone = trim((string) $request->query('phone', ''));

        if ($phone === '') {
            return response()->json(['error' => 'A phone number is required.'], 422);
        }

        $orders = Order::query()
            ->where('customer_phone', $phone)
            ->with('shipment.courierProvider')
            ->get(['id', 'status']);

        $byCourier = $this->activeCouriers()
            ->mapWithKeys(fn ($c) => [$c->name => ['orders' => 0, 'delivered' => 0, 'cancelled' => 0]])
            ->all();

        foreach ($orders as $order) {
            $name = $order->shipment?->courierProvider?->name;
            if (! $name) {
                continue;
            }
            $byCourier[$name] ??= ['orders' => 0, 'delivered' => 0, 'cancelled' => 0];
            $byCourier[$name]['orders']++;
            if ($order->status === 'delivered') {
                $byCourier[$name]['delivered']++;
            }
            if (in_array($order->status, ['cancelled', 'returned'], true)) {
                $byCourier[$name]['cancelled']++;
            }
        }

        return response()->json([
            'phone' => $phone,
            'total_orders' => $orders->count(),
            'total_delivered' => $orders->where('status', 'delivered')->count(),
            'total_cancelled' => $orders->whereIn('status', ['cancelled', 'returned'])->count(),
            'couriers' => collect($byCourier)->map(fn (array $v, string $name) => [
                'name' => $name,
                'orders' => $v['orders'],
                'delivered' => $v['delivered'],
                'cancelled' => $v['cancelled'],
                'success_rate' => $v['orders'] > 0 ? (int) round(($v['delivered'] / $v['orders']) * 100) : 0,
            ])->values(),
        ]);
    }

    public function posPrint(Order $order): View
    {
        $order->load(['items']);

        return view('studio.orders.print.pos', ['order' => $order]);
    }

    public function labelPrint(Order $order): View
    {
        $order->load(['shipment.courierProvider']);

        return view('studio.orders.print.label', ['order' => $order]);
    }

    private function activeCouriers(): Collection
    {
        return $this->activeCouriersCache ??= CourierProvider::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function loadForRow(Order $order): Order
    {
        return $order->load([
            'customer.segment',
            'customer.riskProfile',
            'items.product.thumbnail',
            'items.variant',
            'shipment.courierProvider',
            'orderNotes',
            'verifiedBy:id,name',
        ])->loadCount('items');
    }

    private function rowResponse(Order $order, string $message): JsonResponse
    {
        $html = view('studio.orders.partials._row', [
            'order' => $this->loadForRow($order),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
            'couriers' => $this->activeCouriers(),
            'commentPresets' => self::COMMENT_PRESETS,
        ])->render();

        return response()->json([
            'success' => true,
            'message' => $message,
            'regions' => ['order-row-'.$order->id => $html],
        ]);
    }

    /**
     * A single order's full detail + status controls.
     */
    public function show(Order $order): View
    {
        $order->load([
            'items.product.thumbnail',
            'items.variant',
            'customer',
            'shipment.courierProvider',
            'orderNotes.staffUser',
            'statusHistories.staffUser',
            'exchangedFrom',
            'exchangeOrders',
        ]);

        return view('studio.orders.show', [
            'order' => $order,
            'statuses' => $this->orderManagementService->allowedStatuses(),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    /**
     * AJAX regions for the order-detail page: the header status badge and the
     * fulfillment card (stepper + status history). Kept separate from the list
     * row region so a status change updates whichever page it was made from.
     */
    private function detailStatusResponse(Order $order, string $message): JsonResponse
    {
        $order->load(['statusHistories.staffUser']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'regions' => [
                'order-status-badge' => view('studio.orders.partials._detail-status-badge', ['order' => $order])->render(),
                'order-fulfillment' => view('studio.orders.partials._detail-fulfillment', ['order' => $order])->render(),
            ],
        ]);
    }

    private function detailNotesResponse(Order $order, string $message): JsonResponse
    {
        $order->load(['orderNotes.staffUser']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'regions' => ['order-notes' => view('studio.orders.partials._detail-notes', ['order' => $order])->render()],
        ]);
    }

    // ---- Order item / discount editing (detail page) ----

    public function addItem(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->runItemEdit($order, $request, fn () => $this->orderEditService->addItem(
            $order,
            (int) $validated['product_id'],
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            (int) ($validated['quantity'] ?? 1),
            isset($validated['price']) ? (float) $validated['price'] : null,
        ), 'Product added to '.$order->order_number.'.');
    }

    public function updateItem(Order $order, OrderItem $item, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        return $this->runItemEdit($order, $request, fn () => $this->orderEditService->updateItem(
            $order,
            $item,
            (int) $validated['quantity'],
            (float) $validated['price'],
        ), 'Item updated.');
    }

    public function removeItem(Order $order, OrderItem $item, Request $request): RedirectResponse|JsonResponse
    {
        return $this->runItemEdit($order, $request, fn () => $this->orderEditService->removeItem($order, $item), 'Item removed.');
    }

    public function setDiscount(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'discount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->runItemEdit($order, $request, fn () => $this->orderEditService->setDiscount(
            $order,
            (float) $validated['discount'],
            $validated['reason'] ?? null,
        ), 'Discount updated.');
    }

    /**
     * Runs an edit closure, translating validation failures into the same
     * JSON/redirect shape the other order actions use, and re-rendering the
     * items + summary regions on success so the detail page updates live.
     */
    private function runItemEdit(Order $order, Request $request, \Closure $edit, string $message): RedirectResponse|JsonResponse
    {
        try {
            $edit();
        } catch (ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }

        if ($request->wantsJson()) {
            return $this->detailItemsResponse($order, $message);
        }

        return back()->with('success', $message);
    }

    private function detailItemsResponse(Order $order, string $message): JsonResponse
    {
        $order->refresh()->load(['items.product.thumbnail', 'items.variant']);
        $data = [
            'order' => $order,
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ];

        return response()->json([
            'success' => true,
            'message' => $message,
            'regions' => [
                'order-items' => view('studio.orders.partials._detail-items', $data)->render(),
                'order-summary' => view('studio.orders.partials._detail-summary', $data)->render(),
            ],
        ]);
    }

    /**
     * Courier-aware status transition. Shipping, delivery and return are routed
     * through the courier so the shipment record, its tracking timeline and the
     * order status all stay in sync:
     *   - shipped   → auto-create/dispatch a courier entry, then move to Shipped
     *   - delivered → if a courier shipment exists, mark it delivered (which
     *                 syncs the order); otherwise a plain "direct delivered"
     *   - returned  → same, marking the shipment returned when there is one
     * Every other status uses the normal order-status flow.
     */
    private function transitionOrder(Order $order, string $status, ?string $note, ?string $rtoReason): Order
    {
        if ($status === 'shipped') {
            return $this->courierService->shipWithAutoCourier($order, $note);
        }

        if (in_array($status, ['delivered', 'returned'], true)) {
            $shipment = $order->shipment()->first();

            if ($shipment && $shipment->courier_provider_id && $shipment->status !== $status) {
                $this->courierService->markShipmentOutcome($shipment, $status, $note);

                return $order->refresh();
            }
        }

        return $this->orderManagementService->updateStatus($order, $status, $note, $rtoReason);
    }

    public function updateStatus(Order $order, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
            'rto_reason' => ['nullable', 'string', 'in:'.implode(',', Order::RTO_REASONS)],
        ]);

        // Capture the returned instance: the transition reloads a locked copy
        // inside its transaction, so the passed $order stays stale — rendering
        // it (row or detail badge) would show the old status without this.
        $order = $this->transitionOrder(
            $order,
            $validated['status'],
            $validated['note'] ?? null,
            $validated['rto_reason'] ?? null,
        );

        if ($request->wantsJson()) {
            return $request->boolean('detail')
                ? $this->detailStatusResponse($order, 'Order status updated to '.$validated['status'].'.')
                : $this->rowResponse($order, 'Order status updated to '.$validated['status'].'.');
        }

        return back()->with('success', 'Order status updated to '.$validated['status'].'.');
    }

    /**
     * Manage Order -> Customer card "Edit" — lets staff correct/complete the
     * delivery address (and district) after the order was placed. Not
     * restricted to EDITABLE_STATUSES like item/pricing edits — a wrong or
     * incomplete address (e.g. missing area/city, which blocks the Pathao
     * API auto-push — see PathaoCourierClient::resolveCityAndZone()) is
     * exactly the kind of thing staff need to fix on an already-shipped
     * order, not just a fresh one.
     */
    public function updateAddress(Order $order, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'district' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        $order->update($validated);

        return back()->with('success', 'Address updated for '.$order->order_number.'.');
    }

    /**
     * "Order Source" — where orders come from, with per-source revenue
     * and delivered-only profit (COD principle, matching Studio's other
     * money figures).
     */
    public function source(): View
    {
        $rows = Order::query()
            ->selectRaw('source, count(*) as orders_count, sum(total) as revenue')
            ->groupBy('source')
            ->orderByDesc('orders_count')
            ->get();

        $deliveredProfit = Order::query()
            ->where('status', 'delivered')
            ->selectRaw('source, sum(gross_profit) as profit')
            ->groupBy('source')
            ->pluck('profit', 'source');

        $totalOrders = (int) $rows->sum('orders_count');

        return view('studio.orders.source', [
            'rows' => $rows,
            'deliveredProfit' => $deliveredProfit,
            'totalOrders' => $totalOrders,
            'sourceLabels' => Order::SOURCES,
        ]);
    }

    /**
     * "Order Processing Report" — the funnel across the order lifecycle
     * plus average time-to-delivery, all from real status history.
     */
    public function processingReport(): View
    {
        $statuses = $this->orderManagementService->allowedStatuses();

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalOrders = (int) $statusCounts->sum();

        // Real average processing time: for delivered orders, how long
        // from creation to the delivered status history entry.
        $deliveredOrders = Order::query()
            ->where('status', 'delivered')
            ->with(['statusHistories' => fn ($q) => $q->where('new_status', 'delivered')])
            ->get(['id', 'created_at']);

        $durationsHours = $deliveredOrders
            ->map(function (Order $order) {
                $deliveredAt = $order->statusHistories->first()?->created_at;

                return $deliveredAt ? $order->created_at->diffInHours($deliveredAt) : null;
            })
            ->filter(fn ($hours) => $hours !== null);

        $avgProcessingHours = $durationsHours->isNotEmpty()
            ? round($durationsHours->avg(), 1)
            : null;

        // Real conversion metrics.
        $delivered = (int) ($statusCounts['delivered'] ?? 0);
        $cancelled = (int) ($statusCounts['cancelled'] ?? 0);
        $returned = (int) ($statusCounts['returned'] ?? 0);

        return view('studio.orders.processing-report', [
            'statuses' => $statuses,
            'statusCounts' => $statusCounts,
            'totalOrders' => $totalOrders,
            'avgProcessingHours' => $avgProcessingHours,
            'deliveryRate' => $totalOrders > 0 ? round(($delivered / $totalOrders) * 100, 1) : 0.0,
            'cancelRate' => $totalOrders > 0 ? round(($cancelled / $totalOrders) * 100, 1) : 0.0,
            'returnRate' => $totalOrders > 0 ? round(($returned / $totalOrders) * 100, 1) : 0.0,
        ]);
    }
}
