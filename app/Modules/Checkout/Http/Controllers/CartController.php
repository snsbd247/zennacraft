<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Checkout\Services\CartService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private BehaviorEventService $behaviorEventService,
        private MediaService $mediaService,
    ) {}

    /** Slide-out cart drawer contents (offer bar + items + auto-suggest). */
    public function drawer(Request $request): View
    {
        return view('storefront.cart._drawer', $this->drawerViewData());
    }

    private function drawerViewData(): array
    {
        $items = $this->cartService->items();
        $cartProductIds = $items->pluck('product_id')->filter()->all();
        $suggested = Product::where('status', 'active')
            ->when($cartProductIds, fn ($q) => $q->whereNotIn('id', $cartProductIds))
            ->with('thumbnail')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'items' => $items,
            'summary' => $this->cartService->summary(),
            'offer' => Offer::activeFor('cart_free_gift'),
            'suggested' => $suggested,
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
        ];
    }

    private function drawerFragment(): JsonResponse
    {
        return response()->json([
            'html' => view('storefront.cart._drawer', $this->drawerViewData())->render(),
            'count' => $this->cartService->count(),
        ]);
    }

    public function index(Request $request): View
    {
        $couponCode = $request->query('coupon_code');

        if (filled($couponCode)) {
            $this->ensureCouponAttemptAllowed($request);
        }

        $cartSummary = $this->cartService->summary($couponCode);

        if (filled($couponCode)) {
            $this->trackCouponPreview($request, $cartSummary['coupon_result'] ?? [], $cartSummary);
        }

        return view('storefront.cart.index', [
            'cartItems' => $this->cartService->items(),
            'cartSummary' => $cartSummary,
            'couponCode' => $couponCode,
        ]);
    }

    /**
     * Add several variants of a product in one request — powers the product
     * page's multi-select (order several colours/sizes together). Each variant
     * keeps its own distinct SKU as a separate cart line.
     */
    public function addMany(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'checkout' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $productId = (int) $validated['product_id'];
        $added = 0;
        $errors = [];

        foreach ($validated['items'] as $item) {
            try {
                $this->cartService->add(
                    $productId,
                    isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                    (int) ($item['quantity'] ?? 1)
                );
                $added++;
            } catch (ValidationException $e) {
                $errors[] = $e->validator->errors()->first();
            } catch (\Throwable $e) {
                $errors[] = 'One selected item could not be added.';
            }
        }

        if ($added === 0) {
            $message = $errors[0] ?? 'Nothing could be added to the cart.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : back()->withErrors(['cart' => $message]);
        }

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_ADDED_TO_CART, [
            'product_id' => $productId,
            'metadata' => ['items' => $added, 'cart_count' => $this->cartService->count()],
        ]);

        // "Order Now" navigates to checkout; "Add to Cart" (AJAX) returns the
        // cart drawer fragment so the page never reloads.
        if (filter_var($validated['checkout'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('checkout', ['cart_checkout' => 1]);
        }

        if ($request->expectsJson()) {
            return $this->drawerFragment();
        }

        return redirect()->route('cart.index')->with('success', $added.' item(s) added to your cart.');
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'redirect_to_cart' => ['nullable', 'boolean'],
        ]);

        $this->cartService->add(
            (int) $validated['product_id'],
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            (int) ($validated['quantity'] ?? 1)
        );

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_ADDED_TO_CART, [
            'product_id' => (int) $validated['product_id'],
            'product_variant_id' => isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            'metadata' => [
                'quantity' => (int) ($validated['quantity'] ?? 1),
                'cart_count' => $this->cartService->count(),
            ],
        ]);

        if ($request->expectsJson()) {
            return $this->drawerFragment();
        }

        if (filter_var($validated['redirect_to_cart'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('cart.index')->with('success', 'Product added to cart.');
        }

        return back()->with('success', 'Product added to cart.');
    }

    public function update(string $key, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ]);

        $this->cartService->update($key, (int) $validated['quantity']);
        $line = $this->cartService->items()->firstWhere('key', $key);

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_CART_UPDATED, [
            'product_id' => $line['product_id'] ?? null,
            'product_variant_id' => $line['variant_id'] ?? null,
            'metadata' => [
                'quantity' => (int) $validated['quantity'],
                'cart_count' => $this->cartService->count(),
            ],
        ]);

        if ($request->expectsJson()) {
            return $this->drawerFragment();
        }

        return redirect()
            ->route('cart.index', array_filter(['coupon_code' => $validated['coupon_code'] ?? null]))
            ->with('success', 'Cart quantity updated.');
    }

    public function remove(string $key, Request $request): RedirectResponse|JsonResponse
    {
        $line = $this->cartService->items()->firstWhere('key', $key);
        $this->cartService->remove($key);

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_CART_REMOVED, [
            'product_id' => $line['product_id'] ?? null,
            'product_variant_id' => $line['variant_id'] ?? null,
            'metadata' => [
                'cart_count' => $this->cartService->count(),
            ],
        ]);

        if ($request->expectsJson()) {
            return $this->drawerFragment();
        }

        return redirect()
            ->route('cart.index', array_filter(['coupon_code' => $request->input('coupon_code')]))
            ->with('success', 'Cart item removed.');
    }

    public function clear(): RedirectResponse
    {
        $this->cartService->clear();

        return redirect()->route('cart.index')->with('success', 'Cart cleared.');
    }

    protected function trackCouponPreview(Request $request, array $couponResult, array $cartSummary): void
    {
        $context = [
            'coupon_id' => ($couponResult['coupon'] ?? null)?->id,
            'metadata' => [
                'valid' => (bool) ($couponResult['valid'] ?? false),
                'discount_amount' => (float) ($couponResult['discount_amount'] ?? 0),
                'cart_total' => (float) ($cartSummary['total'] ?? 0),
            ],
        ];

        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_COUPON_ATTEMPTED, $context);

        if (($couponResult['valid'] ?? false) && ($couponResult['coupon'] ?? null)) {
            $this->behaviorEventService->record($request, BehaviorEventService::EVENT_COUPON_APPLIED, $context);
        }
    }

    protected function ensureCouponAttemptAllowed(Request $request): void
    {
        $identity = $request->hasSession() ? $request->session()->getId() : (string) $request->ip();
        $key = 'coupon-apply:'.hash_hmac('sha256', $identity, (string) config('app.key'));

        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Too many coupon attempts. Please try again shortly.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
