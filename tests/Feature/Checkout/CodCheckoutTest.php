<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CodCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Checkout fires Facebook pixel/CAPI tracking calls; make sure no
        // real network request is ever attempted during tests regardless
        // of settings state.
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

    public function test_guest_can_complete_cod_checkout_and_order_is_created(): void
    {
        $product = $this->makeProduct();

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer',
            'phone' => '01712345601',
            'address' => '123 Test Road, Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_phone', '01712345601')->first();
        $this->assertNotNull($order, 'Expected an order to be created.');
        $this->assertSame('pending', $order->status);
        $this->assertSame(2000.0, (float) $order->total);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_server_recalculates_total_and_ignores_tampered_client_price(): void
    {
        $product = $this->makeProduct(['price' => 1000.00]);

        // CheckoutRequest's validated() output only ever contains the
        // rule-listed fields (name/phone/address/product_id/variant_id/
        // quantity/coupon_code) — price/total/subtotal below are not in
        // that rule set, so Laravel drops them before CheckoutService
        // ever sees them. This proves a client cannot influence the
        // charged amount by adding extra form fields.
        $response = $this->post('/checkout', [
            'name' => 'Jane Customer',
            'phone' => '01712345602',
            'address' => '123 Test Road, Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1,
            'total' => 1,
            'subtotal' => 1,
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_phone', '01712345602')->first();
        $this->assertNotNull($order, 'Expected an order to be created.');
        $this->assertSame(1000.0, (float) $order->total, 'Order total must come from the product price, not client input.');
        $this->assertSame(1000.0, (float) $order->subtotal);
    }

    public function test_checkout_persists_the_selected_district(): void
    {
        // Regression guard: 'district' was validated by CheckoutRequest and
        // even had a real form field, but CheckoutService::createOrder()
        // silently dropped it — nothing ever saved it. Added 2026-07-24
        // alongside the Dashboard's "Top Selling by District" panel.
        $product = $this->makeProduct();

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer',
            'phone' => '01712345603',
            'address' => '123 Test Road, Dhaka',
            'district' => 'Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_phone', '01712345603')->first();
        $this->assertNotNull($order, 'Expected an order to be created.');
        $this->assertSame('Dhaka', $order->district);
    }

    public function test_checkout_rejects_invalid_bd_phone_number(): void
    {
        $product = $this->makeProduct();

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer',
            'phone' => '12345',
            'address' => '123 Test Road, Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('orders', ['customer_phone' => '12345']);
    }
}
