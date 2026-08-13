<?php

namespace Tests\Feature\Checkout;

use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartDrawerTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, float $price): Product
    {
        return Product::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'sku' => strtoupper(\Illuminate\Support\Str::random(6)), 'price' => $price, 'status' => 'active', 'stock' => 100]);
    }

    public function test_empty_drawer_renders(): void
    {
        $this->get(route('cart.drawer'))->assertOk()->assertSee('empty');
    }

    public function test_drawer_shows_offer_bar_and_cart_item(): void
    {
        $product = $this->product('Gawa Ghee 1kg', 1650);
        Offer::create(['name' => 'Free Oil', 'placement' => 'cart_free_gift', 'threshold_amount' => 3000, 'reward_text' => '500ml Mustard Oil', 'active' => true]);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->get(route('cart.drawer'))->assertOk()
            ->assertSee('Gawa Ghee 1kg')
            ->assertSee('500ml Mustard Oil')
            ->assertSee('more to unlock');
    }

    public function test_ajax_add_returns_json_fragment_with_count(): void
    {
        $product = $this->product('African Honey', 768);
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()->assertJsonStructure(['html', 'count'])->assertJson(['count' => 1]);
    }
}
