<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Checkout\Services\CartService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product page lets a customer pick several variants (colours/sizes) and
 * order them together. Each variant must land in the cart as its own line with
 * its own distinct SKU so fulfilment is accurate.
 */
class CartMultiAddTest extends TestCase
{
    use RefreshDatabase;

    private function hoodie(): Product
    {
        $category = Category::create(['name' => 'Winter', 'slug' => 'winter-'.uniqid(), 'status' => 'active']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Premium Hoodie', 'slug' => 'hoodie-'.uniqid(),
            'sku' => 'ZC-HOODIE-'.strtoupper(uniqid()), 'price' => 1050, 'stock' => 50, 'status' => 'active',
        ]);
        foreach (['BLACK', 'BLUE', 'GREEN'] as $c) {
            ProductVariant::create([
                'product_id' => $product->id, 'name' => $c, 'sku' => 'SKU-'.$c.'-'.strtoupper(uniqid()),
                'price' => 1050, 'stock' => 20, 'status' => 'active', 'show_on_storefront' => true,
                'option_values' => ['Color' => $c],
            ]);
        }

        return $product;
    }

    public function test_ajax_add_to_cart_returns_the_drawer_fragment_without_a_reload(): void
    {
        $product = $this->hoodie();

        $this->postJson(route('cart.add-many'), [
            'product_id' => $product->id,
            'checkout' => 0,
            'items' => [['variant_id' => $product->variants[0]->id, 'quantity' => 1]],
        ])->assertOk()->assertJsonStructure(['html', 'count'])->assertJson(['count' => 1]);
    }

    public function test_ajax_order_now_still_redirects_to_checkout(): void
    {
        $product = $this->hoodie();

        // checkout=1 ("Order Now") must navigate, not return the drawer fragment.
        $this->post(route('cart.add-many'), [
            'product_id' => $product->id,
            'checkout' => 1,
            'items' => [['variant_id' => $product->variants[0]->id, 'quantity' => 1]],
        ])->assertRedirect(route('checkout', ['cart_checkout' => 1]));
    }

    public function test_customer_can_add_multiple_variants_in_one_request_each_with_its_own_sku(): void
    {
        $product = $this->hoodie();
        [$black, $blue] = [$product->variants[0], $product->variants[1]];

        $response = $this->post(route('cart.add-many'), [
            'product_id' => $product->id,
            'items' => [
                ['variant_id' => $black->id, 'quantity' => 2],
                ['variant_id' => $blue->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect(route('cart.index'));

        $items = collect(app(CartService::class)->items());
        $this->assertSame(2, $items->count(), 'Two distinct variant lines should be in the cart.');

        $skus = $items->pluck('sku')->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$black->sku, $blue->sku], $skus, 'Each line keeps its own variant SKU.');
        $this->assertEqualsCanonicalizing([2, 1], $items->pluck('quantity')->all());
    }

    public function test_order_now_multi_add_redirects_to_checkout(): void
    {
        $product = $this->hoodie();

        $response = $this->post(route('cart.add-many'), [
            'product_id' => $product->id,
            'checkout' => 1,
            'items' => [['variant_id' => $product->variants[0]->id, 'quantity' => 1]],
        ]);

        $response->assertRedirect(route('checkout', ['cart_checkout' => 1]));
        $this->assertSame(1, app(CartService::class)->count());
    }
}
