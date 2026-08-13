<?php

namespace Tests\Feature\Courier;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Courier\Services\Api\CourierApiManager;
use App\Modules\Courier\Services\Api\PathaoCourierClient;
use App\Modules\Courier\Services\Api\SteadfastCourierClient;
use App\Modules\Courier\Services\CourierService;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ?StaffUser $ownerUser = null;

    private function owner(): StaffUser
    {
        if ($this->ownerUser) {
            return $this->ownerUser;
        }
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'courier-api-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ZC-CRAPI-'.uniqid(),
            'customer_name' => 'Jane Customer',
            'customer_phone' => '01712345678',
            'address' => 'House 12, Road 5, Gulshan, Dhaka',
            'district' => 'Dhaka',
            'subtotal' => 1000,
            'delivery_fee' => 80,
            'total' => 1080,
            'status' => 'confirmed',
            'source' => 'website',
        ], $overrides));
    }

    private function steadfastProvider(): CourierProvider
    {
        return CourierProvider::create(['name' => 'Steadfast', 'slug' => 'steadfast', 'status' => 'active']);
    }

    private function pathaoProvider(): CourierProvider
    {
        return CourierProvider::create(['name' => 'Pathao', 'slug' => 'pathao', 'status' => 'active']);
    }

    private function configureSteadfast(): void
    {
        $settings = app(SettingService::class);
        $settings->set('courier', 'steadfast_enabled', true, 'boolean');
        $settings->setEncrypted('courier', 'steadfast_api_key', 'API_KEY');
        $settings->setEncrypted('courier', 'steadfast_secret_key', 'SECRET_KEY');
    }

    private function configurePathao(): void
    {
        $settings = app(SettingService::class);
        $settings->set('courier', 'pathao_enabled', true, 'boolean');
        $settings->set('courier', 'pathao_store_id', '99');
        $settings->set('courier', 'pathao_client_id', 'CID');
        $settings->setEncrypted('courier', 'pathao_client_secret', 'CSECRET');
        $settings->set('courier', 'pathao_client_email', 'merchant@example.com');
        $settings->setEncrypted('courier', 'pathao_client_password', 'PASS');
    }

    // --- CourierApiManager ---

    public function test_manager_resolves_no_client_when_provider_not_enabled(): void
    {
        $this->assertNull(app(CourierApiManager::class)->clientFor('steadfast'));
        $this->assertNull(app(CourierApiManager::class)->clientFor(null));
    }

    public function test_manager_resolves_the_configured_and_enabled_client(): void
    {
        $this->configureSteadfast();

        $client = app(CourierApiManager::class)->clientFor('steadfast');

        $this->assertInstanceOf(SteadfastCourierClient::class, $client);
        $this->assertTrue($client->isConfigured());
    }

    // --- Steadfast client ---

    public function test_steadfast_create_order_parses_the_documented_response_shape(): void
    {
        $this->configureSteadfast();
        Http::fake([
            'portal.packzy.com/*' => Http::response([
                'status' => 200,
                'message' => 'Consignment has been created successfully.',
                'consignment' => ['consignment_id' => 1424107, 'tracking_code' => '15BAEB8A'],
            ]),
        ]);

        $order = $this->order();
        $shipment = Shipment::create(['order_id' => $order->id, 'courier_provider_id' => $this->steadfastProvider()->id, 'status' => 'assigned', 'cod_amount' => 1080]);

        $result = app(SteadfastCourierClient::class)->createOrder($shipment->fresh(['order.items', 'courierProvider']));

        $this->assertSame('1424107', $result['consignment_id']);
        $this->assertSame('15BAEB8A', $result['tracking_number']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/create_order')
            && $request->hasHeader('Api-Key', 'API_KEY')
            && $request->hasHeader('Secret-Key', 'SECRET_KEY')
            && $request['invoice'] === $order->order_number);
    }

    public function test_assigning_a_configured_steadfast_shipment_auto_pushes_and_fills_tracking(): void
    {
        $this->configureSteadfast();
        Http::fake([
            'portal.packzy.com/*' => Http::response([
                'status' => 200,
                'message' => 'ok',
                'consignment' => ['consignment_id' => 555, 'tracking_code' => 'TRACK555'],
            ]),
        ]);
        $order = $this->order();
        $provider = $this->steadfastProvider();

        $shipment = app(CourierService::class)->assignShipment($order, ['courier_provider_id' => $provider->id]);

        $this->assertSame('555', $shipment->consignment_id);
        $this->assertSame('TRACK555', $shipment->tracking_number);
    }

    public function test_manual_tracking_number_skips_the_auto_push(): void
    {
        $this->configureSteadfast();
        Http::fake();
        $order = $this->order();
        $provider = $this->steadfastProvider();

        $shipment = app(CourierService::class)->assignShipment($order, [
            'courier_provider_id' => $provider->id,
            'tracking_number' => 'MANUAL-123',
        ]);

        $this->assertSame('MANUAL-123', $shipment->tracking_number);
        Http::assertNothingSent();
    }

    public function test_steadfast_rejection_surfaces_the_providers_message(): void
    {
        $this->configureSteadfast();
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 400, 'message' => 'Invalid recipient phone number'], 400)]);
        $order = $this->order();
        $shipment = Shipment::create(['order_id' => $order->id, 'courier_provider_id' => $this->steadfastProvider()->id, 'status' => 'assigned', 'cod_amount' => 1080]);

        $this->expectExceptionMessage('Invalid recipient phone number');
        app(SteadfastCourierClient::class)->createOrder($shipment->fresh(['order.items', 'courierProvider']));
    }

    // --- Pathao client ---

    public function test_pathao_create_order_resolves_city_and_zone_then_creates(): void
    {
        $this->configurePathao();
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'TOKEN', 'refresh_token' => 'R', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            '*/aladdin/api/v1/city-list' => Http::response(['data' => ['data' => [['city_id' => 1, 'city_name' => 'Dhaka']]]]),
            '*/aladdin/api/v1/cities/1/zone-list' => Http::response(['data' => ['data' => [['zone_id' => 10, 'zone_name' => 'Gulshan']]]]),
            '*/aladdin/api/v1/orders' => Http::response(['data' => ['consignment_id' => 'PATHCONS1']]),
        ]);
        $order = $this->order();
        $shipment = Shipment::create(['order_id' => $order->id, 'courier_provider_id' => $this->pathaoProvider()->id, 'status' => 'assigned', 'cod_amount' => 1080]);

        $result = app(PathaoCourierClient::class)->createOrder($shipment->fresh(['order.items', 'courierProvider']));

        $this->assertSame('PATHCONS1', $result['consignment_id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/aladdin/api/v1/orders')
            && ($request['recipient_city'] ?? null) === 1
            && ($request['recipient_zone'] ?? null) === 10);
    }

    public function test_pathao_throws_a_clear_error_when_the_district_has_no_matching_city(): void
    {
        $this->configurePathao();
        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 3600]),
            '*/aladdin/api/v1/city-list' => Http::response(['data' => ['data' => [['city_id' => 1, 'city_name' => 'Chattogram']]]]),
        ]);
        $order = $this->order(['district' => 'Sylhet']);
        $shipment = Shipment::create(['order_id' => $order->id, 'courier_provider_id' => $this->pathaoProvider()->id, 'status' => 'assigned', 'cod_amount' => 1080]);

        $this->expectExceptionMessage('no matching city');
        app(PathaoCourierClient::class)->createOrder($shipment->fresh(['order.items', 'courierProvider']));
    }

    // --- Studio push/sync actions ---

    public function test_studio_push_button_pushes_and_saves_tracking_number(): void
    {
        $this->configureSteadfast();
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 7, 'tracking_code' => 'T7']])]);
        $order = $this->order();
        $shipment = Shipment::create(['order_id' => $order->id, 'courier_provider_id' => $this->steadfastProvider()->id, 'status' => 'assigned', 'cod_amount' => 1080]);

        $this->actingAs($this->owner(), 'staff')
            ->post(route('courier.shipments.push', $shipment))
            ->assertRedirect();

        $this->assertSame('T7', $shipment->fresh()->tracking_number);
    }

    public function test_studio_push_button_flashes_the_error_on_failure(): void
    {
        $this->configureSteadfast();
        Http::fake(['portal.packzy.com/*' => Http::response(['status' => 400, 'message' => 'Duplicate invoice'], 400)]);
        $order = $this->order();
        $shipment = Shipment::create(['order_id' => $order->id, 'courier_provider_id' => $this->steadfastProvider()->id, 'status' => 'assigned', 'cod_amount' => 1080]);

        $response = $this->actingAs($this->owner(), 'staff')->post(route('courier.shipments.push', $shipment));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Duplicate invoice', session('error'));
    }

    public function test_studio_sync_status_updates_shipment_and_order_status(): void
    {
        $this->configureSteadfast();
        Http::fake(['*/status_by_cid/*' => Http::response(['status' => 200, 'delivery_status' => 'delivered'])]);
        $order = $this->order();
        $shipment = Shipment::create([
            'order_id' => $order->id, 'courier_provider_id' => $this->steadfastProvider()->id,
            'status' => 'shipped', 'consignment_id' => 'CID9', 'cod_amount' => 1080,
        ]);

        $this->actingAs($this->owner(), 'staff')
            ->post(route('courier.shipments.sync-status', $shipment))
            ->assertRedirect();

        $this->assertSame('delivered', $shipment->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->status);
    }

    // --- Webhook ---

    public function test_webhook_rejects_a_missing_or_wrong_secret(): void
    {
        $this->post(route('webhooks.steadfast', ['secret' => 'wrong']), ['consignment_id' => '1', 'status' => 'delivered'])
            ->assertForbidden();
    }

    public function test_webhook_updates_the_matching_shipment_status(): void
    {
        app(SettingService::class)->setEncrypted('courier', 'steadfast_webhook_secret', 'whsecret');
        $order = $this->order();
        $shipment = Shipment::create([
            'order_id' => $order->id, 'courier_provider_id' => $this->steadfastProvider()->id,
            'status' => 'shipped', 'consignment_id' => 'WHCID1', 'cod_amount' => 1080,
        ]);

        $this->postJson(route('webhooks.steadfast', ['secret' => 'whsecret']), [
            'consignment_id' => 'WHCID1',
            'status' => 'delivered',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('delivered', $shipment->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->status);
    }
}
