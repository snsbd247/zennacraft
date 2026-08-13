<?php

namespace Tests\Feature\Order;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Order\Models\Order;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCourierTransitionTest extends TestCase
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
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'courier-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    private function courier(): CourierProvider
    {
        return CourierProvider::create(['name' => 'Steadfast', 'slug' => 'steadfast-'.uniqid(), 'status' => 'active']);
    }

    private function order(string $status = 'confirmed'): Order
    {
        return Order::create([
            'order_number' => 'ZC-CR-'.uniqid(), 'customer_name' => 'B', 'customer_phone' => '017'.rand(10000000, 99999999),
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 80, 'total' => 1080, 'status' => $status, 'source' => 'website',
        ]);
    }

    private function ajax()
    {
        return $this->actingAs($this->owner(), 'staff')->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    }

    public function test_marking_shipped_auto_creates_a_courier_entry(): void
    {
        $this->courier();
        $order = $this->order('confirmed');

        $this->ajax()->post(route('orders.status', $order), ['status' => 'shipped'])->assertOk();

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $shipment = Shipment::where('order_id', $order->id)->first();
        $this->assertNotNull($shipment, 'A courier shipment should be auto-created on ship.');
        $this->assertNotNull($shipment->courier_provider_id);
        $this->assertSame('shipped', $shipment->status);
    }

    public function test_ship_with_picked_courier_assigns_and_dispatches(): void
    {
        $pathao = CourierProvider::create(['name' => 'Pathao', 'slug' => 'p-'.uniqid(), 'status' => 'active']);
        $order = $this->order('confirmed');

        $this->ajax()->post(route('orders.ship-with', $order), ['courier_provider_id' => $pathao->id])->assertOk();

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $shipment = Shipment::where('order_id', $order->id)->first();
        $this->assertNotNull($shipment);
        $this->assertSame($pathao->id, $shipment->courier_provider_id);
        $this->assertSame('shipped', $shipment->status);
    }

    public function test_list_shows_a_courier_picker_when_no_courier_is_assigned(): void
    {
        $this->courier();               // an active courier exists
        $this->order('confirmed');      // an order with no shipment yet

        $this->actingAs($this->owner(), 'staff')->get(route('orders.index'))
            ->assertOk()
            ->assertSee('ship-with', false)   // Shipment posts to the pick-courier endpoint
            ->assertSee('Select courier');
    }

    public function test_auto_ship_prefers_the_enabled_courier_over_manual(): void
    {
        // "Manual" sorts first alphabetically, but only Pathao is enabled in
        // Courier API Setup — the auto-shipment must pick Pathao.
        CourierProvider::create(['name' => 'Manual', 'slug' => 'manual-'.uniqid(), 'status' => 'active']);
        $pathao = CourierProvider::create(['name' => 'Pathao', 'slug' => 'pathao-'.uniqid(), 'status' => 'active']);
        app(\App\Modules\Settings\Services\SettingService::class)->set('courier', 'pathao_enabled', true, 'boolean');

        $order = $this->order('confirmed');
        $this->ajax()->post(route('orders.status', $order), ['status' => 'shipped'])->assertOk();

        $shipment = Shipment::where('order_id', $order->id)->first();
        $this->assertNotNull($shipment);
        $this->assertSame($pathao->id, $shipment->courier_provider_id);
    }

    public function test_marking_delivered_syncs_the_shipment_and_order(): void
    {
        $this->courier();
        $order = $this->order('confirmed');
        // First ship (auto courier entry), then deliver.
        $this->ajax()->post(route('orders.status', $order), ['status' => 'shipped'])->assertOk();

        $this->ajax()->post(route('orders.status', $order), ['status' => 'delivered'])->assertOk();

        $order->refresh();
        $shipment = Shipment::where('order_id', $order->id)->first();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
    }

    public function test_marking_returned_syncs_the_shipment_and_order(): void
    {
        $this->courier();
        $order = $this->order('confirmed');
        $this->ajax()->post(route('orders.status', $order), ['status' => 'shipped'])->assertOk();

        $this->ajax()->post(route('orders.status', $order), ['status' => 'returned'])->assertOk();

        $order->refresh();
        $shipment = Shipment::where('order_id', $order->id)->first();
        $this->assertSame('returned', $order->status);
        $this->assertSame('returned', $shipment->status);
        $this->assertNotNull($shipment->returned_at);
    }

    public function test_direct_delivered_without_a_courier_still_works(): void
    {
        // No courier provider configured, no shipment — a direct "Delivered".
        $order = $this->order('confirmed');

        $this->ajax()->post(route('orders.status', $order), ['status' => 'delivered'])->assertOk();

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNull(Shipment::where('order_id', $order->id)->first());
    }
}
