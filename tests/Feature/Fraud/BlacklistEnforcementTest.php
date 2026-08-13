<?php

namespace Tests\Feature\Fraud;

use App\Modules\Catalog\Models\Category;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Product\Models\Product;
use Database\Seeders\GeneralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression guard for A4: a blacklisted phone with
 * block_blacklisted_checkout=true is refused at checkout entirely — no
 * order is ever created for it.
 *
 * A4 also enforced a second rule — an order with an active risk hold can't
 * be assigned to a courier — via the Studio Shipments page. That page was
 * removed with the rest of the Studio admin panel on 2026-07-24 (no route
 * currently assigns couriers at all), so that half of the guard is gone
 * until Shipments is rebuilt; it should be restored alongside that page.
 */
class BlacklistEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        // block_blacklisted_checkout defaults to true only once seeded
        // (A4) — seed the real baseline seeder to prove the fresh-install
        // path actually enforces it, not a hand-set flag.
        $this->seed(GeneralSettingsSeeder::class);
    }

    public function test_blacklisted_customer_is_blocked_at_checkout(): void
    {
        $phone = '01712345651';

        CustomerBlacklist::create([
            'phone' => $phone,
            'reason' => 'Test blacklist entry',
            'active' => true,
        ]);

        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-'.uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price' => 1000.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        $checkoutResponse = $this->post('/checkout', [
            'name' => 'Blacklisted Customer',
            'phone' => $phone,
            'address' => '123 Test Road, Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $checkoutResponse->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('orders', ['customer_phone' => $phone]);
    }
}
