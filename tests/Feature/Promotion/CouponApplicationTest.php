<?php

namespace Tests\Feature\Promotion;

use App\Modules\Catalog\Models\Category;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CouponApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    protected function makeProduct(array $overrides = []): Product
    {
        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-'.uniqid(),
            'status' => 'active',
        ]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price' => 1000.00,
            'stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    public function test_valid_coupon_reduces_cart_total_correctly(): void
    {
        $product = $this->makeProduct(['price' => 1000.00]);

        $coupon = Coupon::create([
            'code' => 'SAVE10',
            'name' => '10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'status' => 'active',
            'applies_to' => 'all',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer',
            'phone' => '01712345611',
            'address' => '123 Test Road, Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
            'coupon_code' => $coupon->code,
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_phone', '01712345611')->first();
        $this->assertNotNull($order, 'Expected an order to be created with the coupon applied.');
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame(1000.0, (float) $order->subtotal);
        $this->assertSame(100.0, (float) $order->coupon_discount_amount, '10% of 1000 must be 100.');
        $this->assertSame(900.0, (float) $order->total, 'Total must be subtotal minus the discount.');
    }

    public function test_expired_or_usage_capped_coupon_is_rejected(): void
    {
        $product = $this->makeProduct(['price' => 1000.00]);

        $coupon = Coupon::create([
            'code' => 'EXPIRED10',
            'name' => 'Expired 10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'status' => 'active',
            'applies_to' => 'all',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer',
            'phone' => '01712345612',
            'address' => '123 Test Road, Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
            'coupon_code' => $coupon->code,
        ]);

        $response->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseMissing('orders', ['customer_phone' => '01712345612']);
    }
}
