<?php

namespace App\Modules\Order\Services;

use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Order\Models\Order;

class OrderExchangeService
{
    public function __construct(private CheckoutService $checkoutService) {}

    /**
     * Creates a new order that replaces $original — same customer, a
     * fresh set of replacement items, linked back via
     * exchanged_from_order_id. Reuses checkout's own item preparation
     * and order-creation transaction so pricing/cost/profit computation
     * can never drift from a normal order.
     *
     * @param  array<int, array{product_id:int, variant_id:?int, quantity:int, price:?float}>  $items
     */
    public function create(Order $original, array $items, ?string $note = null): Order
    {
        $preparedItems = array_map(
            fn (array $item) => $this->checkoutService->prepareItem(
                (int) $item['product_id'],
                isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                (int) $item['quantity'],
                isset($item['price']) ? (float) $item['price'] : null,
            ),
            $items,
        );

        $subtotal = array_sum(array_map(fn (array $item): float => (float) $item['subtotal'], $preparedItems));

        return $this->checkoutService->createOrderFromItems($preparedItems, [
            'customer_id' => $original->customer_id,
            'customer_name' => $original->customer_name,
            'customer_phone' => $original->customer_phone,
            'customer_email' => $original->customer_email,
            'address' => $original->address,
            'district' => $original->district,
            'delivery_fee' => 0,
            'delivery_zone' => $original->delivery_zone,
            'payment_method' => $original->payment_method ?? 'cod',
            'total' => $subtotal,
            'source' => 'custom',
            'exchanged_from_order_id' => $original->id,
            'notes' => $note ?: 'Exchange for order '.$original->order_number,
        ]);
    }
}
