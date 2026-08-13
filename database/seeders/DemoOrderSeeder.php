<?php

namespace Database\Seeders;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerSegment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\RTO\Models\CustomerRiskProfile;
use App\Modules\RTO\Models\OrderRiskProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoOrderSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = Customer::query()->where('phone', 'like', '0100000000%')->orderBy('id')->get();
        $products = Product::query()->where('sku', 'like', 'ZC-DEMO-%')->with('variants')->orderBy('id')->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $coupons = $this->ensureCoupons();
        $statuses = [
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
            'delivered',
            'delivered',
            'cancelled',
            'returned',
        ];

        foreach (range(1, 25) as $index) {
            $customer = $customers[($index - 1) % $customers->count()];
            $status = $statuses[($index - 1) % count($statuses)];
            $orderNumber = 'DEMO-ORD-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
            $coupon = $index % 4 === 0 ? $coupons['DEMO10'] : ($index % 7 === 0 ? $coupons['DEMO-GIFT'] : null);
            $createdAt = now()->subDays(28 - $index)->subHours($index % 9);
            $items = $this->orderItems($products, $index);
            $subtotal = (float) $items->sum('subtotal');
            $deliveryFee = $index % 3 === 0 ? 130 : 70;
            $discount = $coupon ? ($coupon->discount_type === 'percentage' ? min($subtotal * 0.10, 350) : 150) : 0;
            $total = max(0, $subtotal + $deliveryFee - $discount);
            $productCost = (float) $items->sum('cost_total');
            $courierCost = in_array($status, ['delivered', 'returned', 'shipped'], true) ? ($deliveryFee * 0.72) : 0;
            $verificationStatus = match ($status) {
                'pending' => 'pending',
                'cancelled' => 'cancelled',
                default => 'verified',
            };

            $order = Order::updateOrCreate(
                ['order_number' => $orderNumber],
                [
                    'customer_id' => $customer->id,
                    'coupon_id' => $coupon?->id,
                    'coupon_code' => $coupon?->code,
                    'coupon_discount_amount' => $discount,
                    'coupon_free_shipping' => false,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'customer_email' => $customer->email,
                    'address' => $customer->address."\nArea: Demo Area\nDistrict: ".$this->districtFor($customer->address),
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,
                    'product_cost_total' => $productCost,
                    'courier_cost_total' => $courierCost,
                    'gross_profit' => $total - $productCost - $courierCost,
                    'status' => $status,
                    'verification_status' => $verificationStatus,
                    'verified_at' => $verificationStatus === 'verified' ? $createdAt->copy()->addHours(3) : null,
                    'verified_by' => $this->demoStaffId(),
                    'notes' => "Alternative phone: 01000009999\nArea: Demo Area\nDistrict: ".$this->districtFor($customer->address)."\nDEMO-QA order for UI testing.",
                    'risk_hold_status' => $index % 11 === 0 ? 'active' : 'released',
                    'risk_hold_reason' => $index % 11 === 0 ? 'DEMO high value manual review.' : null,
                    'risk_hold_at' => $index % 11 === 0 ? $createdAt->copy()->addHours(1) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addHours(6),
                ]
            );

            $order->items()->delete();
            $items->each(fn (array $item) => OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
            ]));

            OrderStatusHistory::query()->where('order_id', $order->id)->delete();
            foreach ($this->statusPath($status) as $position => [$previous, $next]) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'previous_status' => $previous,
                    'new_status' => $next,
                    'notes' => 'DEMO QA status event: '.ucfirst(str_replace('_', ' ', $next)),
                    'staff_user_id' => $this->demoStaffId(),
                    'created_at' => $createdAt->copy()->addHours($position + 1),
                    'updated_at' => $createdAt->copy()->addHours($position + 1),
                ]);
            }

            if ($coupon) {
                CouponUsage::updateOrCreate(
                    ['order_id' => $order->id, 'coupon_id' => $coupon->id],
                    [
                        'customer_id' => $customer->id,
                        'code' => $coupon->code,
                        'discount_amount' => $discount,
                        'used_at' => $createdAt->copy()->addMinutes(20),
                    ]
                );
            }

            OrderRiskProfile::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'risk_score' => $index % 11 === 0 ? 86 : ($status === 'returned' ? 72 : 22),
                    'risk_level' => $index % 11 === 0 ? 'high' : ($status === 'returned' ? 'medium' : 'low'),
                    'risk_reasons' => ['DEMO QA order risk profile', 'Status: '.$status],
                    'customer_risk_score' => $index % 11 === 0 ? 80 : 20,
                    'courier_risk_score' => $status === 'returned' ? 70 : 25,
                    'verification_risk_score' => $verificationStatus === 'pending' ? 45 : 10,
                    'last_calculated_at' => now(),
                ]
            );
        }

        $this->refreshCustomerSnapshots($customers);
    }

    protected function ensureCoupons(): array
    {
        return [
            'DEMO10' => Coupon::updateOrCreate(
                ['code' => 'DEMO10'],
                [
                    'name' => 'Demo 10 Percent',
                    'description' => 'Demo QA percentage coupon.',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                    'max_discount_amount' => 350,
                    'min_order_amount' => 900,
                    'usage_limit' => 200,
                    'usage_limit_per_customer' => 5,
                    'starts_at' => now()->subMonth(),
                    'ends_at' => now()->addMonth(),
                    'status' => 'active',
                    'applies_to' => 'all',
                    'created_by' => $this->demoStaffId(),
                ]
            ),
            'DEMO-GIFT' => Coupon::updateOrCreate(
                ['code' => 'DEMO-GIFT'],
                [
                    'name' => 'Demo Gift Coupon',
                    'description' => 'Demo QA fixed discount for gift buyers.',
                    'discount_type' => 'fixed',
                    'discount_value' => 150,
                    'max_discount_amount' => null,
                    'min_order_amount' => 1200,
                    'usage_limit' => 120,
                    'usage_limit_per_customer' => 3,
                    'starts_at' => now()->subMonth(),
                    'ends_at' => now()->addMonth(),
                    'status' => 'active',
                    'applies_to' => 'all',
                    'created_by' => $this->demoStaffId(),
                ]
            ),
        ];
    }

    protected function orderItems(Collection $products, int $index): Collection
    {
        return collect(range(0, $index % 2))->map(function (int $offset) use ($products, $index): array {
            $product = $products[($index + $offset) % $products->count()];
            $variants = $product->variants
                ->where('status', 'active')
                ->where('show_on_storefront', true)
                ->values();
            $variant = $variants->isNotEmpty()
                ? $variants[($index + $offset) % $variants->count()]
                : null;
            $quantity = 1 + (($index + $offset) % 2);
            $price = (float) ($variant?->price ?? $product->price);
            $cost = (float) ($variant?->cost_price ?? $product->cost_price ?? 0);

            return [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'product_name' => $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $price * $quantity,
                'cost_total' => $cost * $quantity,
            ];
        });
    }

    protected function statusPath(string $status): array
    {
        $paths = [
            'pending' => [[null, 'pending']],
            'confirmed' => [[null, 'pending'], ['pending', 'confirmed']],
            'processing' => [[null, 'pending'], ['pending', 'confirmed'], ['confirmed', 'processing']],
            'shipped' => [[null, 'pending'], ['pending', 'confirmed'], ['confirmed', 'processing'], ['processing', 'shipped']],
            'delivered' => [[null, 'pending'], ['pending', 'confirmed'], ['confirmed', 'processing'], ['processing', 'shipped'], ['shipped', 'delivered']],
            'cancelled' => [[null, 'pending'], ['pending', 'cancelled']],
            'returned' => [[null, 'pending'], ['pending', 'confirmed'], ['confirmed', 'processing'], ['processing', 'shipped'], ['shipped', 'returned']],
        ];

        return $paths[$status] ?? [[null, $status]];
    }

    protected function refreshCustomerSnapshots(Collection $customers): void
    {
        foreach ($customers as $customer) {
            $orders = $customer->orders()->get(['id', 'status', 'total', 'created_at']);
            $delivered = $orders->where('status', 'delivered');
            $cancelled = $orders->where('status', 'cancelled');
            $returned = $orders->where('status', 'returned');

            $customer->forceFill([
                'total_orders' => $orders->count(),
                'total_spent' => (float) $delivered->sum('total'),
                'delivered_orders' => $delivered->count(),
                'cancelled_orders' => $cancelled->count(),
                'returned_orders' => $returned->count(),
                'first_order_at' => $orders->min('created_at'),
                'last_order_at' => $orders->max('created_at'),
            ])->save();

            CustomerSegment::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'segment' => $this->segmentFor($delivered->count(), (float) $delivered->sum('total'), $returned->count(), $cancelled->count()),
                    'lifetime_value' => (float) $delivered->sum('total'),
                    'total_orders' => $orders->count(),
                    'delivered_orders' => $delivered->count(),
                    'cancelled_orders' => $cancelled->count(),
                    'returned_orders' => $returned->count(),
                    'last_order_at' => $orders->max('created_at'),
                    'last_calculated_at' => now(),
                ]
            );

            CustomerRiskProfile::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'risk_score' => ($returned->count() + $cancelled->count()) > 1 ? 72 : 20,
                    'risk_level' => ($returned->count() + $cancelled->count()) > 1 ? 'high' : 'low',
                    'total_orders' => $orders->count(),
                    'delivered_orders' => $delivered->count(),
                    'cancelled_orders' => $cancelled->count(),
                    'returned_orders' => $returned->count(),
                    'delivery_rate' => $orders->count() > 0 ? round(($delivered->count() / $orders->count()) * 100, 2) : 0,
                    'cancel_rate' => $orders->count() > 0 ? round(($cancelled->count() / $orders->count()) * 100, 2) : 0,
                    'return_rate' => $orders->count() > 0 ? round(($returned->count() / $orders->count()) * 100, 2) : 0,
                    'last_calculated_at' => now(),
                ]
            );
        }
    }

    protected function segmentFor(int $deliveredOrders, float $revenue, int $returned, int $cancelled): string
    {
        return match (true) {
            $returned + $cancelled >= 2 => 'AT_RISK',
            $deliveredOrders >= 5 || $revenue >= 20000 => 'VIP',
            $deliveredOrders >= 3 => 'LOYAL',
            $deliveredOrders >= 1 => 'REGULAR',
            default => 'NEW',
        };
    }

    protected function districtFor(?string $address): string
    {
        return trim(collect(preg_split('/,|\|/', (string) $address))
            ->map(fn (string $part): string => trim($part))
            ->filter(fn (string $part): bool => $part !== '' && $part !== 'DEMO-QA')
            ->last() ?: 'Dhaka');
    }

    protected function demoStaffId(): ?int
    {
        return \App\Modules\AdminAuth\Models\StaffUser::query()->orderBy('id')->value('id');
    }
}
