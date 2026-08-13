<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Media\Services\MediaService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Promotion\Services\CouponService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    private const SESSION_KEY = 'storefront_cart.items';

    public function __construct(
        private CouponService $couponService,
        private MediaService $mediaService,
        private DeliveryChargeService $deliveryChargeService,
    ) {}

    public function add(int $productId, ?int $variantId = null, int $quantity = 1): void
    {
        $line = $this->lineFor($productId, $variantId, $quantity);
        $items = $this->rawItems();
        $key = $line['key'];
        $existingQuantity = (int) ($items[$key]['quantity'] ?? 0);
        $newQuantity = min(99, $existingQuantity + $quantity);

        $this->lineFor($productId, $variantId, $newQuantity);

        $items[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $newQuantity,
        ];

        $this->putRawItems($items);
    }

    public function update(string $key, int $quantity): void
    {
        $items = $this->rawItems();

        if (! isset($items[$key])) {
            throw ValidationException::withMessages([
                'cart' => 'The selected cart item is no longer available.',
            ]);
        }

        $quantity = max(1, min(99, $quantity));
        $this->lineFor((int) $items[$key]['product_id'], $items[$key]['variant_id'] ? (int) $items[$key]['variant_id'] : null, $quantity);
        $items[$key]['quantity'] = $quantity;

        $this->putRawItems($items);
    }

    public function remove(string $key): void
    {
        $items = $this->rawItems();
        unset($items[$key]);
        $this->putRawItems($items);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return collect($this->rawItems())->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0));
    }

    public function items(): Collection
    {
        return collect($this->rawItems())
            ->map(function (array $item) {
                try {
                    return $this->lineFor(
                        (int) $item['product_id'],
                        $item['variant_id'] ? (int) $item['variant_id'] : null,
                        (int) $item['quantity']
                    );
                } catch (ValidationException) {
                    return null;
                }
            })
            ->filter()
            ->values();
    }

    public function checkoutItems(): array
    {
        $items = $this->items()->all();

        if ($items === []) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        return $items;
    }

    public function summary(?string $couponCode = null, ?string $deliveryZone = null): array
    {
        $items = $this->items();
        $subtotal = $items->sum(fn (array $item): float => (float) $item['subtotal']);
        $deliveryFee = $this->deliveryFee($deliveryZone, $subtotal);
        $cartData = $this->cartData($items->all(), $deliveryFee);
        $couponResult = filled($couponCode)
            ? $this->couponService->applyToCheckout((string) $couponCode, $cartData)
            : $this->couponService->defaultResult($cartData);

        return [
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

    public function lineFor(int $productId, ?int $variantId, int $quantity): array
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

        $product = Product::query()
            ->with(['category', 'thumbnail'])
            ->where('status', 'active')
            ->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is invalid.',
            ]);
        }

        $variant = null;

        if ($variantId) {
            $variant = ProductVariant::query()
                ->with('image')
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->where('show_on_storefront', true)
                ->find($variantId);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'The selected package is invalid.',
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

        $price = (float) ($variant?->price ?? $product->price);
        $subtotal = $price * $quantity;
        $packageName = $variant?->name;

        return [
            'key' => $this->key($product->id, $variant?->id),
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'category_id' => $product->category_id,
            'landing_page_id' => null,
            'product_name' => $variant ? $product->name.' - '.$variant->name : $product->name,
            'display_name' => $product->name,
            'package_name' => $packageName,
            'sku' => $variant?->sku ?? $product->sku,
            'price' => $price,
            'unit_cost' => (float) ($variant?->cost_price ?? $product->cost_price ?? 0),
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'image_url' => $this->imageUrl($variant?->image ?? $product->thumbnail),
            'product_url' => route('storefront.product.show', $product->slug),
        ];
    }

    public function cartData(array $items, float $deliveryFee): array
    {
        $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['subtotal']);

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $subtotal + $deliveryFee,
            'items' => $items,
        ];
    }

    public function deliveryFee(?string $zone = null, float $subtotal = 0.0): float
    {
        return $this->deliveryChargeService->feeFor($zone, $subtotal);
    }

    protected function rawItems(): array
    {
        return session()->get(self::SESSION_KEY, []);
    }

    protected function putRawItems(array $items): void
    {
        session()->put(self::SESSION_KEY, $items);
    }

    protected function key(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?: 'base');
    }

    protected function imageUrl($media): ?string
    {
        return $media ? $this->mediaService->url($media) : null;
    }
}
