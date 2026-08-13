<?php

namespace Tests\Feature\Order;

use App\Modules\Catalog\Models\Category;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 3 / spec §3.8: before this phase there was no `source` column on
 * `orders` at all — every order was attributed only via a derived,
 * non-persisted `attribution_source` computed at render time. These tests
 * would fail against that old code (the column, and the session-based
 * landing-page attribution, didn't exist).
 */
class OrderSourceTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'status' => 'active']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 1000, 'stock' => 10, 'status' => 'active',
        ]);
    }

    protected function checkoutData(Product $product, string $phone): array
    {
        return [
            'name' => 'Test Customer',
            'phone' => $phone,
            'address' => '123 Test Road, Dhaka',
            'product_id' => $product->id,
            'quantity' => 1,
        ];
    }

    public function test_existing_orders_backfilled_to_website_by_the_migration(): void
    {
        $order = Order::create([
            'order_number' => 'ZC-SRC-BACKFILL',
            'customer_name' => 'Backfill Customer',
            'customer_phone' => '01711111111',
            'address' => 'Dhaka',
            'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'status' => 'pending',
        ]);

        $this->assertSame('website', $order->fresh()->source);
        $this->assertNull($order->fresh()->source_landing_page_id);
    }

    public function test_normal_checkout_sets_source_to_website(): void
    {
        $order = app(CheckoutService::class)->createOrder($this->checkoutData($this->makeProduct(), '01712000001'));

        $this->assertSame('website', $order->source);
        $this->assertNull($order->source_landing_page_id);
    }

    public function test_checkout_after_visiting_a_landing_page_is_attributed_to_it(): void
    {
        $landingPage = LandingPage::create([
            'title' => 'Summer Sale', 'slug' => 'summer-sale-'.uniqid(), 'status' => 'active',
        ]);

        // What StorefrontController::landingPageShow() sets before rendering.
        session(['zc_source_landing_page_id' => $landingPage->id]);

        $order = app(CheckoutService::class)->createOrder($this->checkoutData($this->makeProduct(), '01712000002'));

        $this->assertSame('landing', $order->source);
        $this->assertSame($landingPage->id, $order->source_landing_page_id);
        $this->assertTrue($order->sourceLandingPage->is($landingPage));

        // Cleared after use so a later, unrelated order in the same session
        // isn't also mis-attributed to this landing page.
        $this->assertNull(session('zc_source_landing_page_id'));
    }

    public function test_visiting_a_landing_page_records_it_in_session(): void
    {
        $landingPage = LandingPage::create([
            'title' => 'Winter Sale', 'slug' => 'winter-sale-'.uniqid(), 'status' => 'active',
        ]);

        $response = $this->get(route('storefront.landing-pages.show', $landingPage));

        $response->assertOk();
        $this->assertSame($landingPage->id, session('zc_source_landing_page_id'));
    }

    public function test_orders_list_is_filterable_by_source(): void
    {
        Order::create([
            'order_number' => 'ZC-SRC-WEB', 'customer_name' => 'A', 'customer_phone' => '01711111112',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'status' => 'pending', 'source' => 'website',
        ]);
        Order::create([
            'order_number' => 'ZC-SRC-CUSTOM', 'customer_name' => 'B', 'customer_phone' => '01711111113',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'status' => 'pending', 'source' => 'custom',
        ]);

        $filtered = app(\App\Modules\Order\Services\OrderManagementService::class)->paginate(['source' => 'custom']);

        $this->assertCount(1, $filtered->items());
        $this->assertSame('ZC-SRC-CUSTOM', $filtered->items()[0]->order_number);
    }
}
