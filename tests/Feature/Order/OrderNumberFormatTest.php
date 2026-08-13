<?php

namespace Tests\Feature\Order;

use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    private function generate(): string
    {
        return app(CheckoutService::class)->generateOrderNumber();
    }

    public function test_default_format_is_prefix_date_random(): void
    {
        $this->assertMatchesRegularExpression('/^ZC-\d{8}-[A-Z0-9]{6}$/', $this->generate());
    }

    public function test_custom_prefix_and_random_format(): void
    {
        $s = app(SettingService::class);
        $s->set('order', 'prefix', 'trend');   // lower-case + sanitised to TREND
        $s->set('order', 'format', 'random');

        $this->assertMatchesRegularExpression('/^TREND-[A-Z0-9]{8}$/', $this->generate());
    }

    public function test_sequential_format_starts_at_configured_number_and_increments(): void
    {
        $s = app(SettingService::class);
        $s->set('order', 'prefix', 'ZC');
        $s->set('order', 'format', 'sequential');
        $s->set('order', 'start', 1000);

        $first = $this->generate();
        $this->assertSame('ZC-1000', $first);

        // Persist an order with that number; the next one increments.
        Order::create(['order_number' => $first, 'customer_name' => 'A', 'customer_phone' => '01700000000', 'address' => 'X', 'subtotal' => 100, 'delivery_fee' => 0, 'total' => 100, 'status' => 'pending']);

        $this->assertSame('ZC-1001', $this->generate());
    }
}
