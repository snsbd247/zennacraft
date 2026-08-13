<?php

namespace App\Modules\LandingPage\Http\Controllers;

use App\Modules\Checkout\Services\CartService;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Checkout\Services\DeliveryChargeService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Promotion\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Places an order straight from a landing page's embedded order form — no
 * separate checkout page. Populates the cart with the picked items and reuses
 * CheckoutService::createOrder so fraud checks, inventory, coupons, order
 * automation and auto-call verification all run exactly like normal checkout.
 */
class LandingOrderController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CheckoutService $checkoutService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'delivery_zone' => ['required', Rule::in(array_keys(DeliveryChargeService::ZONES))],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'landing_page_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        // Build a fresh cart from the picked items.
        $this->cartService->clear();
        $added = 0;
        foreach ($data['items'] as $item) {
            try {
                $this->cartService->add((int) $item['product_id'], isset($item['variant_id']) ? (int) $item['variant_id'] : null, (int) $item['quantity']);
                $added++;
            } catch (ValidationException) {
                // skip an unavailable line
            }
        }

        if ($added === 0) {
            return response()->json(['ok' => false, 'message' => 'None of the selected items are available right now.'], 422);
        }

        // Attribute to this landing page (createOrder reads this from session).
        if (! empty($data['landing_page_id'])) {
            session(['zc_source_landing_page_id' => (int) $data['landing_page_id']]);
        }

        try {
            $order = $this->checkoutService->createOrder([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'delivery_zone' => $data['delivery_zone'],
                'coupon_code' => $data['coupon_code'] ?? null,
                'cart_checkout' => true,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => $e->validator->errors()->first()], 422);
        }

        $this->cartService->clear();

        return response()->json([
            'ok' => true,
            'redirect' => URL::signedRoute('checkout.success', ['order' => $order->order_number]),
        ]);
    }

    /** Live preview of a promo code's discount for the current landing selection. */
    public function couponPreview(Request $request, CouponService $couponService, DeliveryChargeService $delivery): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:100'],
            'delivery_zone' => ['nullable', Rule::in(array_keys(DeliveryChargeService::ZONES))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $subtotal = 0.0;
        foreach ($data['items'] as $it) {
            $price = ! empty($it['variant_id']) ? ProductVariant::whereKey($it['variant_id'])->value('price') : null;
            $price ??= Product::whereKey($it['product_id'])->value('price');
            $subtotal += (float) $price * (int) $it['quantity'];
        }

        $shipping = (float) $delivery->feeFor($data['delivery_zone'] ?? null, $subtotal);
        $result = $couponService->applyToCheckout(strtoupper(trim($data['coupon_code'])), [
            'subtotal' => $subtotal,
            'delivery_fee' => $shipping,
        ]);

        return response()->json([
            'ok' => true,
            'valid' => (bool) ($result['valid'] ?? false),
            'message' => $result['message'] ?? null,
            'discount_amount' => (float) ($result['discount_amount'] ?? 0),
            'free_shipping' => (bool) ($result['free_shipping'] ?? false),
        ]);
    }
}
