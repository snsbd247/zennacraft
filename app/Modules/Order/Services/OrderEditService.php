<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Line-item + discount editing for an existing order (the Studio order-detail
 * page). Only orders in EDITABLE_STATUSES (pending/confirmed/processing) can be
 * changed — once shipped/delivered/cancelled/returned the money is settled.
 *
 * Totals are recomputed with the SAME formula CheckoutService::createOrderFromItems
 * uses, so an edited order stays consistent with a freshly-created one:
 *   subtotal           = Σ item.subtotal
 *   product_cost_total = Σ item.unit_cost × qty
 *   total              = max(0, subtotal − discounts + delivery_fee)
 *   gross_profit       = total − product_cost_total
 *
 * Inventory reservation is intentionally left to the status-transition flow
 * (reserve on confirm / release on cancel) — this service only touches the
 * order's own money and line items, never product stock, to avoid double
 * counting against that flow.
 */
class OrderEditService
{
    public function addItem(Order $order, int $productId, ?int $variantId, int $quantity, ?float $price = null): OrderItem
    {
        $this->assertEditable($order);

        $product = Product::with('variants')->findOrFail($productId);
        $variant = $variantId ? $product->variants->firstWhere('id', $variantId) : null;

        if ($variantId && ! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'The selected variant is invalid.']);
        }

        $quantity = max(1, $quantity);
        $unitPrice = $price !== null ? max(0, $price) : (float) ($variant?->price ?? $product->price);

        return DB::transaction(function () use ($order, $product, $variant, $quantity, $unitPrice) {
            $item = $order->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'product_name' => $variant ? $product->name.' - '.$variant->name : $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => $unitPrice,
                'unit_cost' => (float) ($variant?->cost_price ?? $product->cost_price ?? 0),
                'quantity' => $quantity,
                'subtotal' => $unitPrice * $quantity,
            ]);

            $this->recalculate($order);

            return $item;
        });
    }

    public function updateItem(Order $order, OrderItem $item, int $quantity, float $price): void
    {
        $this->assertEditable($order);
        $this->assertOwnsItem($order, $item);

        $quantity = max(1, $quantity);
        $price = max(0, $price);

        DB::transaction(function () use ($order, $item, $quantity, $price) {
            $item->update([
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $price * $quantity,
            ]);

            $this->recalculate($order);
        });
    }

    public function removeItem(Order $order, OrderItem $item): void
    {
        $this->assertEditable($order);
        $this->assertOwnsItem($order, $item);

        if ($order->items()->count() <= 1) {
            throw ValidationException::withMessages(['item' => 'An order must keep at least one product.']);
        }

        DB::transaction(function () use ($order, $item) {
            $item->delete();
            $this->recalculate($order);
        });
    }

    public function setDiscount(Order $order, float $amount, ?string $reason = null): void
    {
        $this->assertEditable($order);

        $subtotal = (float) $order->items()->sum('subtotal');
        // A manual discount can't exceed the goods value (total can't go negative).
        $amount = max(0, min($amount, $subtotal));

        DB::transaction(function () use ($order, $amount, $reason) {
            $order->update([
                'manual_discount_type' => $amount > 0 ? 'flat' : null,
                'manual_discount_value' => $amount > 0 ? $amount : null,
                'manual_discount_amount' => $amount,
                'manual_discount_reason' => $reason,
            ]);

            $this->recalculate($order);
        });
    }

    public function assertEditable(Order $order): void
    {
        if (! in_array($order->status, Order::EDITABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This order can no longer be edited (only pending, confirmed or processing orders can be changed).',
            ]);
        }
    }

    private function assertOwnsItem(Order $order, OrderItem $item): void
    {
        if ((int) $item->order_id !== (int) $order->id) {
            abort(404);
        }
    }

    private function recalculate(Order $order): void
    {
        $order->load('items');

        $subtotal = (float) $order->items->sum(fn (OrderItem $i) => (float) $i->subtotal);
        $productCost = (float) $order->items->sum(fn (OrderItem $i) => (float) $i->unit_cost * (int) $i->quantity);
        $discount = (float) $order->manual_discount_amount + (float) $order->coupon_discount_amount;
        $total = max(0, $subtotal - $discount + (float) $order->delivery_fee);

        $order->update([
            'subtotal' => $subtotal,
            'product_cost_total' => $productCost,
            'total' => $total,
            'gross_profit' => $total - $productCost,
        ]);
    }
}
