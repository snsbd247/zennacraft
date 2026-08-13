<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Checkout\Exceptions\PaymentGatewayException;
use App\Modules\Checkout\Http\Requests\CheckoutRequest;
use App\Modules\Checkout\Services\CartService;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Checkout\Services\DeliveryChargeService;
use App\Modules\Checkout\Services\Payment\BkashPaymentClient;
use App\Modules\Checkout\Services\PaymentGatewayService;
use App\Modules\Facebook\Services\FacebookTrackingService;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Recovery\Services\RecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
        private RecoveryService $recoveryService,
        private AuditService $auditService,
        private FacebookTrackingService $facebookTrackingService,
        private BehaviorEventService $behaviorEventService,
        private DeliveryChargeService $deliveryChargeService,
        private PaymentGatewayService $paymentGatewayService,
        private BkashPaymentClient $bkash,
    ) {}

    public function checkout(Request $request): View
    {
        $preview = null;
        $initiateCheckoutEvent = null;
        $cartCheckout = false;
        $this->facebookTrackingService->trackPageView($request);
        $deliveryZone = $request->query('delivery_zone');
        $variantId = $request->filled('variant_id') ? (int) $request->query('variant_id') : null;

        if ($request->filled('product_id')) {
            // "Buy now" links carry only a product_id. Variant products keep
            // their stock on the variants (base stock is 0), so default to the
            // first available variant — otherwise checkout would 404.
            if (! $variantId) {
                $variantId = $this->defaultVariantId((int) $request->query('product_id'));
            }
            try {
                $preview = $this->checkoutService->preview(
                    (int) $request->query('product_id'),
                    $variantId,
                    (int) $request->query('quantity', 1),
                    $request->query('coupon_code'),
                    deliveryZone: $deliveryZone
                );

                $this->saveCheckoutRecovery($request);
                $initiateCheckoutEvent = $this->facebookTrackingService->trackInitiateCheckout($preview, $request);
                $this->trackCheckoutStarted($request, $preview, false);
            } catch (ValidationException) {
                abort(404);
            }
        } elseif ($this->cartService->count() > 0) {
            try {
                $preview = $this->checkoutService->previewCart($request->query('coupon_code'), deliveryZone: $deliveryZone);
                $cartCheckout = true;
                $initiateCheckoutEvent = $this->facebookTrackingService->trackInitiateCheckout($preview, $request);
                $this->trackCheckoutStarted($request, $preview, true);
            } catch (ValidationException) {
                $preview = null;
            }
        }

        return view('storefront.checkout.index', [
            'preview' => $preview,
            'cartCheckout' => $cartCheckout,
            'productId' => $request->query('product_id'),
            'variantId' => $variantId,
            'quantity' => (int) $request->query('quantity', 1),
            'couponCode' => $request->query('coupon_code'),
            'deliveryZone' => $deliveryZone,
            'deliveryTiers' => $this->deliveryChargeService->tiers(),
            'deliveryZones' => DeliveryChargeService::ZONES,
            'freeDeliveryThreshold' => $this->deliveryChargeService->freeDeliveryThreshold(),
            'freeDeliveryForAllOrders' => $this->deliveryChargeService->freeDeliveryForAllOrders(),
            'enabledGateways' => $this->paymentGatewayService->enabledGateways(),
            'facebookInitiateCheckoutEventId' => $initiateCheckoutEvent?->event_id,
        ]);
    }

    /**
     * First purchasable variant for a product (stock lives on variants for
     * variant products) so a "Buy now" with only a product_id still reaches a
     * working checkout. Returns null for simple products — checkout then uses
     * the base product row as before.
     */
    private function defaultVariantId(int $productId): ?int
    {
        return ProductVariant::where('product_id', $productId)
            ->where('status', 'active')
            ->where('show_on_storefront', true)
            ->where('stock', '>', 0)
            ->orderBy('sort_order')->orderBy('id')
            ->value('id');
    }

    /** AJAX: recompute checkout totals for a typed coupon code (Apply button). */
    public function couponPreview(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('coupon_code')));
        $zone = $request->input('delivery_zone');

        try {
            $preview = $request->boolean('cart_checkout')
                ? $this->checkoutService->previewCart($code ?: null, deliveryZone: $zone)
                : $this->checkoutService->preview(
                    (int) $request->input('product_id'),
                    $request->filled('variant_id') ? (int) $request->input('variant_id') : $this->defaultVariantId((int) $request->input('product_id')),
                    (int) $request->input('quantity', 1),
                    $code ?: null,
                    deliveryZone: $zone
                );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
        }

        $couponResult = $preview['coupon_result'] ?? [];

        return response()->json([
            'ok' => true,
            'valid' => $code !== '' && (bool) ($couponResult['valid'] ?? false),
            'message' => $code === '' ? null : ($couponResult['message'] ?? null),
            'subtotal' => (float) $preview['subtotal'],
            'discount_amount' => (float) $preview['discount_amount'],
            'delivery_fee' => (float) $preview['delivery_fee'],
            'total' => (float) $preview['total'],
            'free_shipping' => (bool) $preview['free_shipping'],
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (filled($validated['coupon_code'] ?? null)) {
            $this->behaviorEventService->record($request, BehaviorEventService::EVENT_COUPON_ATTEMPTED, [
                'product_id' => $validated['product_id'] ?? null,
                'product_variant_id' => $validated['variant_id'] ?? null,
                'metadata' => [
                    'coupon_code' => $validated['coupon_code'],
                    'cart_checkout' => filter_var($validated['cart_checkout'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ],
            ]);
        }

        $order = $this->checkoutService->createOrder($validated);
        $this->behaviorEventService->linkSessionToCustomer($request, $order->customer, $order);

        $this->logAudit(
            'checkout',
            'create',
            'checkout',
            'Checkout order created: '.$order->order_number,
            ['id' => $order->id, 'order_number' => $order->order_number, 'total' => $order->total]
        );

        $this->markRecoveryRecovered($request, $order);
        if (filter_var($validated['cart_checkout'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->cartService->clear();
        }

        $purchaseEvent = $this->facebookTrackingService->trackPurchase($order, $request);
        $this->trackOrderSubmitted($request, $order);

        if ($order->payment_method === 'bkash') {
            $redirect = $this->redirectToBkash($order);

            if ($redirect) {
                return $redirect->with('facebook_purchase_event_id', $purchaseEvent?->event_id);
            }
        }

        return redirect()
            ->to(URL::signedRoute('checkout.success', ['order' => $order->order_number]))
            ->with('facebook_purchase_event_id', $purchaseEvent?->event_id);
    }

    /**
     * The order already exists (created above) regardless of what happens
     * next — this only decides where the customer's browser goes. On any
     * bKash failure we fall through to the normal success/invoice page
     * with a flash message instead of leaving the customer stuck: the
     * order is safely on record and can be settled another way (COD,
     * a manual payment link, a retry) from Studio.
     */
    protected function redirectToBkash(Order $order): ?RedirectResponse
    {
        if (! $this->bkash->isConfigured()) {
            logger()->warning('bKash checkout selected but not configured', ['order_id' => $order->id, 'order_number' => $order->order_number]);

            return null;
        }

        try {
            $result = $this->bkash->createPayment($order, route('checkout.bkash.callback'));
        } catch (PaymentGatewayException $exception) {
            logger()->warning('bKash create payment failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $order->forceFill([
            'payment_gateway_reference' => $result['payment_id'],
            'payment_status' => 'pending',
        ])->save();

        return redirect()->away($result['bkash_url']);
    }

    public function success(Order $order, Request $request): View
    {
        $this->facebookTrackingService->trackPageView($request);
        $order->loadMissing(['items.product.thumbnail', 'shipment']);

        return view('storefront.checkout.success', [
            'order' => $order,
            'invoiceUrl' => URL::signedRoute('checkout.invoice', ['order' => $order->order_number]),
        ]);
    }

    public function invoice(Order $order, Request $request): View
    {
        $this->facebookTrackingService->trackPageView($request);
        $order->loadMissing(['items']);

        return view('storefront.checkout.invoice', [
            'order' => $order,
        ]);
    }

    protected function logAudit(
        string $eventType,
        string $eventAction,
        string $module,
        string $description,
        array $metadata = []
    ): void {
        try {
            $this->auditService->log($eventType, $eventAction, $module, $description, $metadata);
        } catch (Throwable $exception) {
            logger()->warning('Audit logging failed', [
                'event_type' => $eventType,
                'event_action' => $eventAction,
                'module' => $module,
                'description' => $description,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function saveCheckoutRecovery(Request $request): void
    {
        try {
            $recovery = $this->recoveryService->saveRecovery([
                'recovery_id' => $request->hasSession() ? $request->session()->get('checkout_recovery_id') : null,
                'product_id' => (int) $request->query('product_id'),
                'variant_id' => $request->filled('variant_id') ? (int) $request->query('variant_id') : null,
                'quantity' => (int) $request->query('quantity', 1),
            ]);

            if ($request->hasSession()) {
                $request->session()->put('checkout_recovery_id', $recovery->id);
            }
        } catch (Throwable $exception) {
            logger()->warning('Checkout recovery logging failed', [
                'product_id' => $request->query('product_id'),
                'variant_id' => $request->query('variant_id'),
                'quantity' => $request->query('quantity', 1),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Live capture of the checkout form as the customer fills it — so a
     * customer who types their name/phone/address but never clicks "Confirm"
     * is still saved as an incomplete order the owner can follow up on. The
     * storefront posts here (debounced + on page-leave via sendBeacon); it
     * updates the same session recovery row, and CheckoutController::store()
     * later flips it to "recovered" when they do submit.
     */
    public function captureRecovery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:600'],
            'product_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        // Only persist once the customer has typed something identifying —
        // an empty visit isn't an "incomplete order".
        if (blank($data['name'] ?? null) && blank($data['phone'] ?? null) && blank($data['address'] ?? null)) {
            return response()->json(['ok' => false]);
        }

        try {
            $recovery = $this->recoveryService->saveRecovery([
                'recovery_id' => $request->hasSession() ? $request->session()->get('checkout_recovery_id') : null,
                'product_id' => $data['product_id'] ?? null,
                'variant_id' => $data['variant_id'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            if ($request->hasSession()) {
                $request->session()->put('checkout_recovery_id', $recovery->id);
            }
        } catch (Throwable $exception) {
            logger()->warning('Checkout recovery capture failed', ['error' => $exception->getMessage()]);

            return response()->json(['ok' => false]);
        }

        return response()->json(['ok' => true]);
    }

    protected function trackCheckoutStarted(Request $request, array $preview, bool $cartCheckout): void
    {
        $item = collect($preview['items'] ?? [])->first();

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_CHECKOUT_STARTED, [
            'product_id' => $item['product_id'] ?? null,
            'product_variant_id' => $item['variant_id'] ?? null,
            'metadata' => [
                'cart_checkout' => $cartCheckout,
                'items_count' => count($preview['items'] ?? []),
                'subtotal' => (float) ($preview['subtotal'] ?? 0),
                'total' => (float) ($preview['total'] ?? 0),
            ],
        ]);

        if (filled($request->query('coupon_code'))) {
            $this->trackCouponResult($request, $preview);
        }
    }

    protected function trackCouponResult(Request $request, array $preview): void
    {
        $couponResult = $preview['coupon_result'] ?? [];
        $context = [
            'coupon_id' => ($couponResult['coupon'] ?? null)?->id,
            'metadata' => [
                'valid' => (bool) ($couponResult['valid'] ?? false),
                'discount_amount' => (float) ($couponResult['discount_amount'] ?? 0),
                'total' => (float) ($preview['total'] ?? 0),
            ],
        ];

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_COUPON_ATTEMPTED, $context);

        if (($couponResult['valid'] ?? false) && ($couponResult['coupon'] ?? null)) {
            $this->behaviorEventService->record($request, BehaviorEventService::EVENT_COUPON_APPLIED, $context);
        }
    }

    protected function trackOrderSubmitted(Request $request, Order $order): void
    {
        $item = $order->items->first();

        if ($order->coupon_id) {
            $this->behaviorEventService->record($request, BehaviorEventService::EVENT_COUPON_APPLIED, [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'coupon_id' => $order->coupon_id,
                'metadata' => [
                    'discount_amount' => (float) $order->coupon_discount_amount,
                ],
            ]);
        }

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_ORDER_SUBMITTED, [
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'product_id' => $item?->product_id,
            'product_variant_id' => $item?->variant_id,
            'coupon_id' => $order->coupon_id,
            'metadata' => [
                'order_total' => (float) $order->total,
                'items_count' => $order->items->count(),
            ],
        ]);
    }

    protected function markRecoveryRecovered(CheckoutRequest $request, Order $order): void
    {
        try {
            $validated = $request->validated();
            if (empty($validated['product_id'])) {
                return;
            }

            $recovery = $this->recoveryService->saveRecovery([
                'recovery_id' => $request->hasSession() ? $request->session()->get('checkout_recovery_id') : null,
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'] ?? 1,
                'customer_name' => $validated['name'],
                'customer_phone' => $validated['phone'],
                'customer_email' => $validated['email'] ?? null,
                'address' => $validated['address'],
            ]);

            $recovery = $this->recoveryService->markStatus($recovery, 'recovered');

            $this->logAudit(
                'recovery',
                'status',
                'recovery',
                'Checkout recovery marked recovered: '.$order->order_number,
                ['id' => $recovery->id, 'order_number' => $order->order_number, 'status' => $recovery->status]
            );
        } catch (Throwable $exception) {
            logger()->warning('Checkout recovery status update failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
