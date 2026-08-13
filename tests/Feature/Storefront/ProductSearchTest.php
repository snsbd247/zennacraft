<?php

namespace Tests\Feature\Storefront;

use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_page_filters_by_search_query(): void
    {
        Product::create(['name' => 'Blue Hoodie', 'slug' => 'blue-hoodie', 'sku' => 'HOOD-1', 'price' => 1000, 'status' => 'active', 'stock' => 5]);
        Product::create(['name' => 'Red Cap', 'slug' => 'red-cap', 'sku' => 'CAP-1', 'price' => 500, 'status' => 'active', 'stock' => 5]);

        $this->get('/products?q=Hoodie')->assertOk()
            ->assertSee('Blue Hoodie')
            ->assertDontSee('Red Cap');
    }

    public function test_products_page_can_match_by_sku(): void
    {
        Product::create(['name' => 'Silk Panjabi', 'slug' => 'silk-panjabi', 'sku' => 'PANJ-9', 'price' => 2000, 'status' => 'active', 'stock' => 5]);
        Product::create(['name' => 'Cotton Tote', 'slug' => 'cotton-tote', 'sku' => 'TOTE-2', 'price' => 800, 'status' => 'active', 'stock' => 5]);

        $this->get('/products?q=PANJ-9')->assertOk()
            ->assertSee('Silk Panjabi')
            ->assertDontSee('Cotton Tote');
    }

    public function test_no_query_shows_all_products(): void
    {
        Product::create(['name' => 'Item One', 'slug' => 'item-one', 'sku' => 'IT-1', 'price' => 100, 'status' => 'active', 'stock' => 5]);
        Product::create(['name' => 'Item Two', 'slug' => 'item-two', 'sku' => 'IT-2', 'price' => 200, 'status' => 'active', 'stock' => 5]);

        $this->get('/products')->assertOk()->assertSee('Item One')->assertSee('Item Two');
    }

    public function test_search_suggest_returns_matching_products_as_json(): void
    {
        Product::create(['name' => 'Handwoven Basket', 'slug' => 'hw-basket', 'sku' => 'BSK-1', 'price' => 900, 'status' => 'active', 'stock' => 5]);
        Product::create(['name' => 'Ceramic Mug', 'slug' => 'ceramic-mug', 'sku' => 'MUG-1', 'price' => 300, 'status' => 'active', 'stock' => 5]);

        $this->getJson('/search/suggest?q=Handwoven')
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Handwoven Basket')
            ->assertJsonMissing(['name' => 'Ceramic Mug']);
    }

    public function test_search_suggest_requires_two_characters(): void
    {
        Product::create(['name' => 'Anything', 'slug' => 'anything', 'sku' => 'A-1', 'price' => 100, 'status' => 'active', 'stock' => 5]);

        $this->getJson('/search/suggest?q=a')->assertOk()->assertJson(['results' => []]);
    }
}
