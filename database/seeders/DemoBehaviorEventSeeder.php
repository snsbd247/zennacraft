<?php

namespace Database\Seeders;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoBehaviorEventSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = Customer::query()->where('phone', 'like', '0100000000%')->orderBy('id')->get();
        $products = Product::query()->where('sku', 'like', 'ZC-DEMO-%')->with('storefrontVariants')->orderBy('id')->get();
        $orders = Order::query()->where('order_number', 'like', 'DEMO-ORD-%')->with('items')->orderBy('id')->get();
        $coupons = Coupon::query()->whereIn('code', ['DEMO10', 'DEMO-GIFT'])->get()->keyBy('code');

        if ($products->isEmpty()) {
            return;
        }

        CustomerBehaviorEvent::query()
            ->where('session_id', 'like', 'demo-session-%')
            ->delete();

        $sources = ['facebook', 'instagram', 'google', 'whatsapp', 'direct', 'unknown'];
        $events = [
            'product_viewed',
            'package_selected',
            'added_to_cart',
            'cart_updated',
            'cart_removed',
            'checkout_started',
            'coupon_attempted',
            'coupon_applied',
            'order_submitted',
        ];

        foreach (range(1, 45) as $index) {
            $customer = $customers->isNotEmpty() && $index % 6 !== 0
                ? $customers[($index - 1) % $customers->count()]
                : null;
            $product = $products[($index - 1) % $products->count()];
            $variant = $product->storefrontVariants->values()->get(($index - 1) % max(1, $product->storefrontVariants->count()));
            $order = $orders->isNotEmpty() ? $orders[($index - 1) % $orders->count()] : null;
            $eventType = $events[($index - 1) % count($events)];
            $coupon = in_array($eventType, ['coupon_attempted', 'coupon_applied'], true)
                ? ($index % 2 === 0 ? $coupons->get('DEMO10') : $coupons->get('DEMO-GIFT'))
                : null;
            $source = $sources[($index - 1) % count($sources)];

            CustomerBehaviorEvent::create([
                'customer_id' => $customer?->id,
                'session_id' => 'demo-session-'.str_pad((string) (($index % 12) + 1), 2, '0', STR_PAD_LEFT),
                'order_id' => $eventType === 'order_submitted' ? $order?->id : null,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'coupon_id' => $coupon?->id,
                'event_type' => $eventType,
                'source' => $source,
                'url' => '/products/'.$product->slug,
                'referrer' => $source === 'direct' ? null : 'https://'.$source.'.example.test/demo',
                'user_agent_hash' => hash('sha256', 'demo-user-agent-'.$index),
                'ip_hash' => hash('sha256', '203.0.113.'.$index),
                'metadata' => [
                    'demo' => true,
                    'cart_value' => round((float) ($variant?->price ?? $product->price) * (1 + ($index % 2)), 2),
                    'package_type' => $variant?->package_type,
                    'source_bucket' => $source,
                ],
                'occurred_at' => now()->subHours(80 - $index),
            ]);
        }
    }
}
