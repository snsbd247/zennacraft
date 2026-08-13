<?php

namespace Tests\Feature\LandingPage;

use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandingOrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, float $price, int $stock = 50): Product
    {
        return Product::create(['name' => $name, 'slug' => Str::slug($name), 'sku' => strtoupper(Str::random(6)), 'price' => $price, 'status' => 'active', 'stock' => $stock]);
    }

    public function test_landing_page_renders_embedded_order_form(): void
    {
        $product = $this->product('China Hoodie', 1050);
        $page = LandingPage::create(['title' => 'Hoodie LP', 'slug' => 'hoodie-lp', 'status' => 'active', 'template' => 'classic', 'suggested_products' => [$product->id]]);

        $this->get('/hoodie-lp')->assertOk()
            ->assertSee('zc-order', false)          // the on-page order form
            ->assertSee('Confirm order')
            ->assertSee('China Hoodie')
            ->assertSee('href="#zc-order"', false);  // CTA scrolls to the form, not a checkout page
    }

    public function test_embedded_form_places_a_real_order(): void
    {
        $product = $this->product('Panjabi', 1650);
        $page = LandingPage::create(['title' => 'Panjabi LP', 'slug' => 'panjabi-lp', 'status' => 'active', 'template' => 'bold', 'suggested_products' => [$product->id]]);

        $res = $this->postJson(route('landing.order.store'), [
            'name' => 'Karim Uddin', 'phone' => '01711100616', 'address' => 'Mirpur, Dhaka',
            'delivery_zone' => 'inside_dhaka', 'landing_page_id' => $page->id,
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
        ]);

        $res->assertOk()->assertJson(['ok' => true])->assertJsonStructure(['redirect']);
        $this->assertStringContainsString('checkout/success', $res->json('redirect'));

        $order = Order::where('customer_phone', 'like', '%1711100616')->firstOrFail();
        $this->assertEquals(2, $order->items->sum('quantity'));
        $this->assertSame('landing', $order->source);
        $this->assertSame($page->id, $order->source_landing_page_id);
    }

    public function test_promo_code_preview_returns_discount_for_landing_selection(): void
    {
        $product = $this->product('Ghee', 1000);
        \App\Modules\Promotion\Models\Coupon::create(['code' => 'SAVE150', 'name' => '৳150 off', 'discount_type' => 'fixed', 'discount_value' => 150, 'status' => 'active', 'applies_to' => 'all', 'min_order_amount' => 0]);

        $this->postJson(route('landing.order.coupon'), [
            'coupon_code' => 'save150', 'delivery_zone' => 'inside_dhaka',
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
        ])->assertOk()->assertJson(['ok' => true, 'valid' => true, 'discount_amount' => 150]);
    }

    public function test_each_variant_renders_as_its_own_selectable_card(): void
    {
        $product = $this->product('Kantha', 2000, 0);
        $v1 = ProductVariant::create(['product_id' => $product->id, 'name' => 'White / 5ft', 'sku' => 'K-W5', 'price' => 2350, 'stock' => 10, 'status' => 'active', 'show_on_storefront' => true, 'sort_order' => 0, 'option_values' => ['Color' => 'White', 'Size' => '5ft']]);
        $v2 = ProductVariant::create(['product_id' => $product->id, 'name' => 'Blue / 6ft', 'sku' => 'K-B6', 'price' => 2500, 'stock' => 10, 'status' => 'active', 'show_on_storefront' => true, 'sort_order' => 1, 'option_values' => ['Color' => 'Blue', 'Size' => '6ft']]);
        $page = LandingPage::create(['title' => 'K LP', 'slug' => 'k-lp', 'status' => 'active', 'template' => 'sales', 'suggested_products' => [$product->id]]);

        $this->get('/k-lp')->assertOk()
            ->assertSee('data-variant="'.$v1->id.'"', false)  // one card per variant
            ->assertSee('data-variant="'.$v2->id.'"', false)
            ->assertSee('White / 5ft')
            ->assertSee('Blue / 6ft')
            ->assertDontSee('data-lo-variant', false);        // the old dropdown is gone
    }

    public function test_variant_product_orders_the_chosen_variant(): void
    {
        $product = $this->product('Hoodie', 1050, 0); // stock on the variant
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'M / Black', 'sku' => 'HD-M-BLK', 'price' => 1100, 'stock' => 10, 'status' => 'active', 'show_on_storefront' => true, 'sort_order' => 0, 'option_values' => ['Size' => 'M', 'Color' => 'Black']]);
        $page = LandingPage::create(['title' => 'V LP', 'slug' => 'v-lp', 'status' => 'active', 'template' => 'sales', 'suggested_products' => [$product->id]]);

        $this->postJson(route('landing.order.store'), [
            'name' => 'Rahim', 'phone' => '01822200300', 'address' => 'Dhaka', 'delivery_zone' => 'inside_dhaka',
            'landing_page_id' => $page->id, 'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('order_items', ['variant_id' => $variant->id, 'quantity' => 1]);
    }
}
