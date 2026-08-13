<?php

namespace Tests\Feature\Checkout;

use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The checkout "Apply coupon" box (AJAX) recomputes totals via checkout.coupon. */
class CheckoutCouponTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create(['name' => 'Ghee', 'slug' => 'ghee-c', 'sku' => 'GC-1', 'price' => 1000, 'status' => 'active', 'stock' => 10]);
    }

    public function test_apply_valid_coupon_returns_discounted_totals(): void
    {
        $product = $this->product();
        Coupon::create(['code' => 'TAKA100', 'name' => '100 off', 'discount_type' => 'fixed', 'discount_value' => 100, 'status' => 'active', 'applies_to' => 'all', 'min_order_amount' => 0]);

        $res = $this->postJson(route('checkout.coupon'), ['product_id' => $product->id, 'quantity' => 1, 'coupon_code' => 'taka100'])->assertOk();
        $this->assertTrue($res->json('valid'));
        $this->assertEquals(100.0, $res->json('discount_amount'));
    }

    public function test_invalid_coupon_is_flagged_not_applied(): void
    {
        $product = $this->product();
        $res = $this->postJson(route('checkout.coupon'), ['product_id' => $product->id, 'quantity' => 1, 'coupon_code' => 'NOPE'])->assertOk();
        $this->assertFalse($res->json('valid'));
        $this->assertEquals(0.0, $res->json('discount_amount'));
    }
}
