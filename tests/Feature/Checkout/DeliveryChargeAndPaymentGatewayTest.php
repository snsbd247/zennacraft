<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Checkout\Services\DeliveryChargeService;
use App\Modules\Checkout\Services\PaymentGatewayService;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 1 replaced CartService/CheckoutService's hardcoded
 * `deliveryFee(): float { return 0.00; }` stubs with real,
 * Settings-backed tiers (spec §6.12.5). Every test here would have
 * failed against the old stub — a suburban/outside order always priced
 * at 0 regardless of zone, and free-delivery-all-orders had nothing to
 * override.
 */
class DeliveryChargeAndPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function makeProduct(float $price = 1000.00): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'status' => 'active']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Test Product', 'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => $price, 'stock' => 10, 'status' => 'active',
        ]);
    }

    public function test_delivery_charge_service_returns_the_configured_tier_per_zone(): void
    {
        $settings = app(SettingService::class);
        $settings->set('delivery', 'inside_dhaka_charge', 0, 'string', true);
        $settings->set('delivery', 'suburban_charge', 130, 'string', true);
        $settings->set('delivery', 'outside_dhaka_charge', 170, 'string', true);
        $settings->set('delivery', 'free_delivery_threshold', 0, 'string', true);

        $service = app(DeliveryChargeService::class);

        $this->assertEquals(0.0, $service->feeFor(DeliveryChargeService::ZONE_INSIDE_DHAKA, 500));
        $this->assertEquals(130.0, $service->feeFor(DeliveryChargeService::ZONE_SUBURBAN, 500));
        $this->assertEquals(170.0, $service->feeFor(DeliveryChargeService::ZONE_OUTSIDE_DHAKA, 500));
    }

    public function test_free_delivery_threshold_waives_the_fee_above_the_configured_amount(): void
    {
        $settings = app(SettingService::class);
        $settings->set('delivery', 'outside_dhaka_charge', 170, 'string', true);
        $settings->set('delivery', 'free_delivery_threshold', 5000, 'string', true);

        $service = app(DeliveryChargeService::class);

        $this->assertEquals(170.0, $service->feeFor(DeliveryChargeService::ZONE_OUTSIDE_DHAKA, 4999));
        $this->assertEquals(0.0, $service->feeFor(DeliveryChargeService::ZONE_OUTSIDE_DHAKA, 5000));
    }

    public function test_free_delivery_all_orders_overrides_every_zone_to_free(): void
    {
        $settings = app(SettingService::class);
        $settings->set('delivery', 'outside_dhaka_charge', 170, 'string', true);
        $settings->set('delivery', 'free_delivery_threshold', 0, 'string', true);
        $settings->set('delivery', 'free_delivery_all_orders', true, 'boolean', true);

        $service = app(DeliveryChargeService::class);

        $this->assertEquals(0.0, $service->feeFor(DeliveryChargeService::ZONE_OUTSIDE_DHAKA, 100));
    }

    public function test_real_checkout_charges_the_configured_delivery_fee_for_the_chosen_zone(): void
    {
        app(SettingService::class)->set('delivery', 'outside_dhaka_charge', 170, 'string', true);
        $product = $this->makeProduct(1000.00);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer', 'phone' => '01712345688',
            'address' => '123 Test Road, Outside Dhaka', 'delivery_zone' => 'outside_dhaka',
            'product_id' => $product->id, 'quantity' => 1,
        ]);
        $response->assertRedirect();

        $order = Order::where('customer_phone', '01712345688')->firstOrFail();
        $this->assertEquals(170.0, (float) $order->delivery_fee);
        $this->assertEquals('outside_dhaka', $order->delivery_zone);
        $this->assertEquals(1170.0, (float) $order->total);
    }

    public function test_payment_gateway_service_reports_nothing_enabled_by_default(): void
    {
        $service = app(PaymentGatewayService::class);

        $this->assertSame([], $service->enabledGateways());
        $this->assertTrue($service->isValidMethod('cod'));
        $this->assertFalse($service->isValidMethod('bkash'));
    }

    public function test_real_checkout_persists_the_selected_enabled_gateway(): void
    {
        app(SettingService::class)->set('payment', 'bkash_enabled', true, 'boolean', false);
        $product = $this->makeProduct(1000.00);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer', 'phone' => '01712345689',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka',
            'payment_method' => 'bkash',
            'product_id' => $product->id, 'quantity' => 1,
        ]);
        $response->assertRedirect();

        $order = Order::where('customer_phone', '01712345689')->firstOrFail();
        $this->assertEquals('bkash', $order->payment_method);
    }

    public function test_checkout_rejects_a_gateway_that_is_not_currently_enabled(): void
    {
        // bkash is NOT enabled here — a tampered/stale form submitting it
        // anyway must be rejected at validation, not silently accepted.
        $product = $this->makeProduct(1000.00);

        $response = $this->post('/checkout', [
            'name' => 'Jane Customer', 'phone' => '01712345690',
            'address' => '123 Test Road, Dhaka', 'delivery_zone' => 'inside_dhaka',
            'payment_method' => 'bkash',
            'product_id' => $product->id, 'quantity' => 1,
        ]);

        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseMissing('orders', ['customer_phone' => '01712345690']);
    }
}
