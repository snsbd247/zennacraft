<?php

namespace Tests\Feature\Settings;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Checkout\Services\DeliveryChargeService;
use App\Modules\Checkout\Services\PaymentGatewayService;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Models\City;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Verification\Services\AutoCallVerificationService;
use Illuminate\Support\Facades\Http;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Cfg Owner', 'email' => 'cfg-owner@zennacraft.test', 'phone' => '+8801700000144', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_all_config_pages_render(): void
    {
        $owner = $this->owner();
        $pages = [
            'marketing' => 'Marketing', 'courier' => 'Courier API Setup', 'payment' => 'Payment Gateway',
            'sms' => 'SMS Gateway', 'email' => 'Email (SMTP)', 'google' => 'Google & reCAPTCHA',
            'verification' => 'Order Verification Call',
            'invoice' => 'Invoice Address', 'delivery' => 'Delivery Charge', 'order' => 'Order Number', 'social' => 'Socialite Login Credentials',
        ];
        foreach ($pages as $pg => $title) {
            $this->actingAs($owner, 'staff')->get(route("config.$pg"))->assertOk()->assertSee($title);
        }
    }

    public function test_payment_gateway_secret_is_saved_encrypted(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->put(route('config.payment.save'), [
            'sslcommerz_store_id' => 'zenna123', 'sslcommerz_store_password' => 'pgpass', 'sslcommerz_sandbox' => '1',
        ])->assertRedirect(route('config.payment'));

        $settings = app(SettingService::class);
        $this->assertSame('zenna123', $settings->get('payment', 'sslcommerz_store_id'));
        $this->assertSame('pgpass', $settings->getEncrypted('payment', 'sslcommerz_store_password'));
        $this->assertTrue(filter_var($settings->get('payment', 'sslcommerz_sandbox'), FILTER_VALIDATE_BOOLEAN));
    }

    public function test_courier_settings_save_and_secret_is_encrypted(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->put(route('config.courier.save'), [
            'pathao_base_url' => 'https://api.pathao.com', 'pathao_store_id' => '178387',
            'pathao_client_id' => 'ABC', 'pathao_client_secret' => 'topsecret',
        ])->assertRedirect(route('config.courier'));

        $settings = app(SettingService::class);
        $this->assertSame('178387', $settings->get('courier', 'pathao_store_id'));
        $this->assertSame('topsecret', $settings->getEncrypted('courier', 'pathao_client_secret'));
        // stored value is not plaintext
        $this->assertNotSame('topsecret', \App\Modules\Settings\Models\Setting::where('key', 'pathao_client_secret')->value('value'));
    }

    public function test_delivery_charge_save_changes_the_fee_engine(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->put(route('config.delivery.save'), [
            'inside_dhaka_charge' => 80, 'suburban_charge' => 100, 'outside_dhaka_charge' => 130,
            'free_delivery_threshold' => 5000, // 'free_delivery_all_orders' omitted => false
        ])->assertRedirect(route('config.delivery'));

        $svc = app(DeliveryChargeService::class);
        $this->assertEquals(80.0, $svc->feeFor('inside_dhaka', 100));
        $this->assertEquals(130.0, $svc->feeFor('outside_dhaka', 100));
    }

    public function test_enabling_a_payment_gateway_activates_it_in_checkout(): void
    {
        $owner = $this->owner();
        // Enable bKash via the config page's per-section switch.
        $this->actingAs($owner, 'staff')->put(route('config.payment.save'), [
            'bkash_enabled' => '1', 'bkash_app_key' => 'k', // card_enabled/nagad_enabled omitted => off
        ])->assertRedirect(route('config.payment'));

        $gateways = app(PaymentGatewayService::class);
        $this->assertTrue($gateways->isEnabled('bkash'), 'Enabling bKash here must enable it in checkout.');
        $this->assertArrayHasKey('bkash', $gateways->enabledGateways());
        $this->assertFalse($gateways->isEnabled('nagad'));
    }

    public function test_autocall_verification_fires_on_order_only_when_enabled(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $svc = app(AutoCallVerificationService::class);
        $settings = app(SettingService::class);
        $order = Order::create(['order_number' => 'ZC-VC-1', 'customer_name' => 'Karim', 'customer_phone' => '01711100000', 'address' => 'Dhaka', 'subtotal' => 100, 'delivery_fee' => 0, 'total' => 100, 'status' => 'pending']);

        // Disabled by default -> nothing sent.
        $this->assertFalse($svc->requestCall($order));
        Http::assertNothingSent();

        // Enable + configure -> fires the call to the configured endpoint.
        $settings->set('verification', 'autocall_enabled', true, 'boolean');
        $settings->set('verification', 'autocall_api_url', 'https://ivr.example.com/call');
        $this->assertTrue($svc->requestCall($order));
        Http::assertSent(fn ($req) => $req->url() === 'https://ivr.example.com/call' && $req['order_number'] === 'ZC-VC-1' && $req['phone'] === '01711100000');
    }

    public function test_city_crud_and_subcity_create(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('cities.store'), ['name' => 'Dhaka', 'status' => 'active'])->assertRedirect(route('cities.index'));
        $city = City::where('name', 'Dhaka')->firstOrFail();

        $this->actingAs($owner, 'staff')->postJson(route('cities.toggle', $city))->assertOk()->assertJson(['status' => 'inactive']);

        $this->actingAs($owner, 'staff')->post(route('subcities.store'), ['city_id' => $city->id, 'name' => 'Mirpur', 'status' => 'active'])->assertRedirect(route('subcities.index'));
        $this->assertDatabaseHas('sub_cities', ['city_id' => $city->id, 'name' => 'Mirpur']);
    }
}
