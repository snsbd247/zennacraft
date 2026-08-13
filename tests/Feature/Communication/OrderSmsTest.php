<?php

namespace Tests\Feature\Communication;

use App\Modules\Communication\Services\OrderSmsService;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderSmsTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $number): Order
    {
        return Order::create(['order_number' => $number, 'customer_name' => 'Karim', 'customer_phone' => '01711100000', 'address' => 'Dhaka', 'subtotal' => 1650, 'delivery_fee' => 0, 'total' => 1650, 'status' => 'pending']);
    }

    public function test_confirmation_sms_sends_with_the_order_number_when_enabled(): void
    {
        Http::fake(['*' => Http::response(['error' => 0, 'msg' => 'ok'], 200)]);
        $settings = app(SettingService::class);
        $settings->set('sms', 'provider', 'alpha');
        $settings->setEncrypted('sms', 'api_key', 'test-key');
        $settings->set('sms', 'order_confirm_enabled', true, 'boolean');
        $settings->set('sms', 'order_confirm_template', 'Order {order} of Tk {total} confirmed. - {store}');

        $order = $this->order('ZC-SMS-1');
        $this->assertTrue(app(OrderSmsService::class)->sendConfirmation($order));

        Http::assertSent(fn ($req) => str_contains($req->url(), 'sms.net.bd') && str_contains((string) ($req['msg'] ?? ''), 'ZC-SMS-1'));
    }

    public function test_no_sms_when_order_confirmation_is_off(): void
    {
        Http::fake();
        // order_confirm_enabled not set -> off
        $this->assertFalse(app(OrderSmsService::class)->sendConfirmation($this->order('ZC-SMS-2')));
        Http::assertNothingSent();
    }
}
