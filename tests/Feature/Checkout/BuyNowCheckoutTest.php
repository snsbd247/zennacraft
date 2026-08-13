<?php

namespace Tests\Feature\Checkout;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Buy now" links carry only a product_id. Variant products keep stock on the
 * variants (base stock 0), so checkout must default to the first available
 * variant and render — not 404.
 */
class BuyNowCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, int $stock): Product
    {
        return Product::create(['name' => $name, 'slug' => Str::slug($name), 'sku' => strtoupper(Str::random(6)), 'price' => 900, 'status' => 'active', 'stock' => $stock]);
    }

    public function test_buy_now_on_variant_product_reaches_checkout(): void
    {
        $product = $this->product('Kantha Shawl', 0); // stock lives on the variant
        ProductVariant::create([
            'product_id' => $product->id, 'name' => 'Red', 'sku' => 'KS-RED', 'price' => 1200,
            'stock' => 5, 'status' => 'active', 'show_on_storefront' => true, 'sort_order' => 0,
        ]);

        $this->get(route('checkout', ['product_id' => $product->id, 'quantity' => 1]))
            ->assertOk()
            ->assertSee('Kantha Shawl');
    }

    public function test_buy_now_on_simple_product_reaches_checkout(): void
    {
        $product = $this->product('Pure Ghee', 10);

        $this->get(route('checkout', ['product_id' => $product->id, 'quantity' => 1]))
            ->assertOk()
            ->assertSee('Pure Ghee');
    }

    public function test_fully_out_of_stock_product_still_404s(): void
    {
        $product = $this->product('Sold Out Item', 0);
        ProductVariant::create([
            'product_id' => $product->id, 'name' => 'Only', 'sku' => 'SO-1', 'price' => 500,
            'stock' => 0, 'status' => 'active', 'show_on_storefront' => true, 'sort_order' => 0,
        ]);

        $this->get(route('checkout', ['product_id' => $product->id, 'quantity' => 1]))->assertNotFound();
    }
}
