<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Customer\Services\CustomerIntelligenceService;
use App\Modules\Fraud\Services\CustomerFraudService;
use App\Modules\Marketing\Services\MarketingCampaignService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\Promotion\Services\CouponService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Security\Services\OrderSecurityService;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutService
{
    public function __construct(
        private CustomerIntelligenceService $customerIntelligenceService,
        private CouponService $couponService,
        private CartService $cartService,
        private CustomerFraudService $customerFraudService,
        private OrderSecurityService $orderSecurityService,
        private OrderManagementService $orderManagementService,
        private MarketingCampaignService $marketingCampaignService,
        private SettingService $settingService,
        private PhoneService $phoneService,
        private DeliveryChargeService $deliveryChargeService,
        private PaymentGatewayService $paymentGatewayService,
    ) {}

    public function createOrder(array $data): Order
    {
        $this->orderSecurityService->assertValidPhone((string) $data['phone']);
        $data['phone'] = $this->phoneService->normalize((string) $data['phone']);

        $items = $this->checkoutItems($data);
        $itemsSubtotal = collect($items)->sum(fn (array $item): float => (float) $item['subtotal']);
        $deliveryZone = $data['delivery_zone'] ?? null;
        $paymentMethod = $this->paymentGatewayService->isValidMethod((string) ($data['payment_method'] ?? 'cod'))
            ? (string) ($data['payment_method'] ?? 'cod')
            : 'cod';

        $deliveryFee = $this->deliveryFee($deliveryZone, $itemsSubtotal, $items);
        $cartData = array_merge($this->cartData($items, $deliveryFee), [
            'customer_phone' => $data['phone'],
        ]);
        $customer = Customer::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $data['phone']))->first();

        $this->orderSecurityService->assertNotBlacklisted($data['phone']);
        $this->orderSecurityService->assertPhoneCooldown($data['phone']);

        if (filled($data['coupon_code'] ?? null)) {
            $this->orderSecurityService->assertCouponNotLocked($data['phone']);
        }

        $couponResult = $this->couponResult($data['coupon_code'] ?? null, $cartData, $customer);

        if (($data['coupon_code'] ?? null) && ! $couponResult['valid']) {
            $this->recordCouponFraudAttempt($data['phone'], (string) $data['coupon_code'], $couponResult['message']);

            throw ValidationException::withMessages([
                'coupon_code' => $couponResult['message'],
            ]);
        }

        $coupon = $couponResult['coupon'];
        $payableDeliveryFee = (float) $couponResult['delivery_fee'];
        $total = (float) $couponResult['total_after_discount'];

        $this->orderSecurityService->validatePreCheckout($data, array_merge($cartData, [
            'delivery_fee' => $payableDeliveryFee,
            'total' => $total,
            'total_before_discount' => (float) $couponResult['total_before_discount'],
            'total_after_discount' => $total,
            'discount_amount' => (float) $couponResult['discount_amount'],
            'free_shipping' => (bool) $couponResult['free_shipping'],
        ]));

        // Spec §3.8: attribute the order to the landing page the customer
        // most recently visited in this session, if any — set by
        // StorefrontController::landingPageShow(). Cleared after use so it
        // doesn't leak into a later, unrelated order in the same session.
        $sourceLandingPageId = session('zc_source_landing_page_id');
        $source = $sourceLandingPageId ? 'landing' : 'website';
        session()->forget('zc_source_landing_page_id');

        $order = $this->createOrderFromItems($items, [
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'coupon_discount_amount' => (float) $couponResult['discount_amount'],
            'coupon_free_shipping' => (bool) $couponResult['free_shipping'],
            'customer_name' => $data['name'],
            'customer_phone' => $data['phone'],
            'customer_email' => $data['email'] ?? null,
            'address' => $data['address'],
            'district' => $data['district'] ?? null,
            'delivery_fee' => $payableDeliveryFee,
            'delivery_zone' => $deliveryZone,
            'payment_method' => $paymentMethod,
            'total' => $total,
            'source' => $source,
            'source_landing_page_id' => $sourceLandingPageId,
            'notes' => $this->checkoutNotes($data),
        ]);

        if ($coupon) {
            $this->couponService->recordUsage($coupon, $order, (float) $couponResult['discount_amount']);
        }

        $analysis = $this->runFraudAnalysis($order);
        $order = $this->autoRejectCriticalFraud($order, $analysis);
        $this->recordCampaignAttribution($order);
        $this->triggerAutomation('order.created', $order, [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
        ]);

        // Auto-call verification (fires only when enabled in Settings; never breaks checkout).
        try {
            app(\App\Modules\Verification\Services\AutoCallVerificationService::class)->requestCall($order);
        } catch (Throwable) {
            // ignore — verification call is best-effort
        }

        // Order-confirmation SMS with the order number (fires only when enabled).
        try {
            app(\App\Modules\Communication\Services\OrderSmsService::class)->sendConfirmation($order);
        } catch (Throwable) {
            // ignore — confirmation SMS is best-effort
        }

        return $order->refresh()->load(['items', 'customer']);
    }

    /**
     * Phase 3C: the shared order-creation transaction — both storefront
     * checkout (createOrder() above) and any future Studio "Create order"
     * flow are meant to funnel through this exact method, so inventory
     * timing, customer sync, and profit-field computation can never drift
     * between paths. Neither coupon application nor manual-discount
     * application happens here — the caller resolves whatever discount
     * mechanism it uses and hands over the final $orderAttributes.
     *
     * $orderAttributes must include everything Order::create() needs
     * except order_number/subtotal/product_cost_total/courier_cost_total/
     * gross_profit/status, which are always computed here so neither
     * caller can accidentally set them inconsistently. 'total' is
     * required (used to derive gross_profit).
     */
    public function createOrderFromItems(array $items, array $orderAttributes): Order
    {
        $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['subtotal']);
        $productCostTotal = collect($items)->sum(fn (array $item): float => ((float) $item['unit_cost']) * (int) $item['quantity']);
        $orderItems = collect($items)->map(fn (array $item): array => $this->orderItemPayload($item))->all();
        $total = (float) $orderAttributes['total'];

        return DB::transaction(function () use ($orderItems, $subtotal, $productCostTotal, $total, $orderAttributes) {
            $order = Order::create(array_merge([
                'order_number' => $this->generateOrderNumber(),
                'courier_cost_total' => 0,
                'status' => 'pending',
            ], $orderAttributes, [
                'subtotal' => $subtotal,
                'product_cost_total' => $productCostTotal,
                'gross_profit' => $total - $productCostTotal,
            ]));

            $order->items()->createMany($orderItems);
            $this->customerIntelligenceService->syncFromOrder($order);
            $order->refresh();

            return $order->load(['items', 'customer']);
        });
    }

    public function preview(int $productId, ?int $variantId = null, int $quantity = 1, ?string $couponCode = null, ?Customer $customer = null, ?string $deliveryZone = null): array
    {
        $item = $this->prepareItem($productId, $variantId, $quantity);
        $deliveryFee = $this->deliveryFee($deliveryZone, (float) $item['subtotal']);
        $couponResult = $this->couponResult($couponCode, $this->cartData([$item], $deliveryFee), $customer);

        return [
            'item' => $item,
            'items' => [$item],
            'subtotal' => $item['subtotal'],
            'delivery_fee' => $couponResult['delivery_fee'],
            'original_delivery_fee' => $deliveryFee,
            'discount_amount' => $couponResult['discount_amount'],
            'free_shipping' => $couponResult['free_shipping'],
            'total_before_discount' => $couponResult['total_before_discount'],
            'total' => $couponResult['total_after_discount'],
            'coupon_result' => $couponResult,
        ];
    }

    public function previewCart(?string $couponCode = null, ?Customer $customer = null, ?string $deliveryZone = null): array
    {
        $items = $this->cartService->checkoutItems();
        $itemsSubtotal = collect($items)->sum(fn (array $item): float => (float) $item['subtotal']);
        $deliveryFee = $this->deliveryFee($deliveryZone, $itemsSubtotal, $items);
        $cartData = $this->cartData($items, $deliveryFee);
        $couponResult = $this->couponResult($couponCode, $cartData, $customer);

        return [
            'item' => $items[0] ?? null,
            'items' => $items,
            'subtotal' => $cartData['subtotal'],
            'delivery_fee' => $couponResult['delivery_fee'],
            'original_delivery_fee' => $deliveryFee,
            'discount_amount' => $couponResult['discount_amount'],
            'free_shipping' => $couponResult['free_shipping'],
            'total_before_discount' => $couponResult['total_before_discount'],
            'total' => $couponResult['total_after_discount'],
            'coupon_result' => $couponResult,
        ];
    }

    public function generateOrderNumber(): string
    {
        // Configurable from Studio → Setting & Configuration → Order Number.
        // Defaults keep the original ZC-{date}-{random} behaviour.
        $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->settingService->get('order', 'prefix', 'ZC'))) ?: 'ZC';
        $format = (string) $this->settingService->get('order', 'format', 'date_random');
        $start = max(1, (int) $this->settingService->get('order', 'start', 1));

        do {
            if ($format === 'sequential') {
                $next = $this->nextSequentialOrderNumber($prefix, $start);
                $orderNumber = $prefix.'-'.$next; // short, clean, starts from the configured number
                $start = $next + 1; // if it somehow collides, try the next number
            } elseif ($format === 'random') {
                $orderNumber = $prefix.'-'.strtoupper(Str::random(8));
            } else {
                $orderNumber = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            }
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /** Highest numeric order number for this prefix + 1 (floored at the configured start). */
    protected function nextSequentialOrderNumber(string $prefix, int $start): int
    {
        $highest = Order::where('order_number', 'like', $prefix.'-%')
            ->orderByDesc('id')->limit(200)->pluck('order_number')
            ->map(function ($number) use ($prefix) {
                $suffix = substr((string) $number, strlen($prefix) + 1);

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })->max();

        return max($start, ((int) $highest) + 1);
    }

    /**
     * Phase 3C: $priceOverride lets Studio's admin-placed-order flow
     * (staff negotiates a price on the phone) reuse this exact same
     * validation — product/variant existence, active status, stock
     * availability — without duplicating it. Storefront checkout never
     * passes this (stays null), so its behavior is unchanged: price
     * always comes from the product/variant record, never the request.
     * Made public (was protected) for the same reason — this is the one
     * piece of checkout's item-preparation logic an external caller
     * legitimately needs, not an invitation to reach into the rest of
     * this service's internals.
     */
    public function prepareItem(int $productId, ?int $variantId, int $quantity, ?float $priceOverride = null): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        if ($quantity > 99) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity may not be greater than 99.',
            ]);
        }

        $product = Product::where('status', 'active')->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is invalid.',
            ]);
        }

        $variant = null;

        if ($variantId) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('status', 'active')
                ->where('show_on_storefront', true)
                ->find($variantId);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'The selected variant is invalid.',
                ]);
            }

            if ($quantity > (int) $variant->stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'The requested quantity is not available.',
                ]);
            }
        } elseif ($quantity > (int) $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => 'The requested quantity is not available.',
            ]);
        }

        $price = $priceOverride ?? (float) ($variant?->price ?? $product->price);
        $subtotal = $price * $quantity;

        return [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'category_id' => $product->category_id,
            'landing_page_id' => null,
            'product_name' => $variant ? $product->name.' - '.$variant->name : $product->name,
            'sku' => $variant?->sku ?? $product->sku,
            'price' => $price,
            'unit_cost' => (float) ($variant?->cost_price ?? $product->cost_price ?? 0),
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ];
    }

    protected function deliveryFee(?string $zone = null, float $subtotal = 0.0, array $items = []): float
    {
        return $this->deliveryChargeService->feeFor($zone, $subtotal, $this->cartHasFreeDeliveryItem($items));
    }

    /** True when any cart item is a "Free Delivery Product" (Campaign/Offer). */
    protected function cartHasFreeDeliveryItem(array $items): bool
    {
        $productIds = collect($items)->pluck('product_id')->filter()->unique()->all();

        if (empty($productIds)) {
            return false;
        }

        return \App\Modules\Product\Models\Product::whereIn('id', $productIds)
            ->where('free_delivery', true)
            ->exists();
    }

    protected function cartData(array $items, float $deliveryFee): array
    {
        $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['subtotal']);

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $subtotal + $deliveryFee,
            'items' => $items,
        ];
    }

    protected function checkoutItems(array $data): array
    {
        if (filter_var($data['cart_checkout'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->cartService->checkoutItems();
        }

        return [
            $this->prepareItem(
                (int) $data['product_id'],
                isset($data['variant_id']) ? (int) $data['variant_id'] : null,
                (int) ($data['quantity'] ?? 1)
            ),
        ];
    }

    protected function orderItemPayload(array $item): array
    {
        unset(
            $item['key'],
            $item['unit_cost'],
            $item['category_id'],
            $item['landing_page_id'],
            $item['display_name'],
            $item['package_name'],
            $item['image_url'],
            $item['product_url']
        );

        return $item;
    }

    protected function checkoutNotes(array $data): ?string
    {
        $notes = trim((string) ($data['notes'] ?? ''));

        return $notes ?: null;
    }

    protected function couponResult(?string $couponCode, array $cartData, ?Customer $customer = null): array
    {
        if (! filled($couponCode)) {
            return $this->couponService->defaultResult($cartData);
        }

        return $this->couponService->applyToCheckout((string) $couponCode, $cartData, $customer);
    }

    protected function runFraudAnalysis(Order $order): array
    {
        try {
            return $this->customerFraudService->analyzeOrder($order);
        } catch (Throwable $exception) {
            logger()->warning('Checkout fraud analysis failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);
        }

        return [
            'action' => CustomerFraudService::ACTION_ALLOW,
            'score' => 0,
            'severity' => 'low',
            'reasons' => [],
            'signals' => [],
        ];
    }

    protected function autoRejectCriticalFraud(Order $order, array $analysis): Order
    {
        if (! $this->securityFlag('auto_reject_critical_fraud', false)) {
            return $order;
        }

        if (($analysis['severity'] ?? 'low') !== 'critical') {
            return $order;
        }

        try {
            $score = (int) ($analysis['score'] ?? 100);

            $this->customerFraudService->recordFraudEvent(
                $order->customer,
                $order,
                'critical_fraud_reject',
                'critical',
                $score,
                'Critical fraud order auto rejected.',
                [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'score' => $score,
                    'reason_code' => 'critical_fraud_reject',
                ]
            );

            $notes = trim(trim((string) $order->notes).PHP_EOL.'Auto rejected by fraud protection.');
            $order->forceFill(['notes' => $notes])->save();

            return $this->orderManagementService->updateStatus($order, 'cancelled', 'Auto rejected by fraud protection.');
        } catch (Throwable $exception) {
            logger()->warning('Critical fraud auto reject failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);

            return $order->refresh();
        }
    }

    protected function recordCouponFraudAttempt(string $phone, string $couponCode, string $message): void
    {
        try {
            $this->orderSecurityService->recordCheckoutAttempt(
                $phone,
                'coupon_abuse',
                [
                    'severity' => 'medium',
                    'score' => 20,
                    'reason' => 'Invalid or limited coupon attempt during checkout.',
                    'reason_code' => 'invalid_coupon',
                    'coupon_code_hash' => sha1(strtoupper(trim($couponCode))),
                    'message' => $message,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Coupon fraud event logging failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Phase 3C: made public so an admin-placed order fires the same
     * "order.created" automations (confirmation SMS, etc.) a storefront
     * order does — automations respond to "a new order exists", not to
     * which channel it came from.
     */
    public function triggerAutomation(string $triggerKey, Order $order, array $context = []): void
    {
        try {
            app(AutomationEngineService::class)->handleTrigger($triggerKey, $order, $context);
        } catch (Throwable $exception) {
            logger()->warning('Checkout automation trigger failed', [
                'trigger_key' => $triggerKey,
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function recordCampaignAttribution(Order $order): void
    {
        try {
            $this->marketingCampaignService->recordOrderAttribution($order);
        } catch (Throwable $exception) {
            logger()->warning('Marketing campaign attribution failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function securityFlag(string $key, bool $default): bool
    {
        return filter_var(
            $this->settingService->get('general', $key, $default),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
