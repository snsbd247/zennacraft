<?php

namespace Tests\Feature\Order;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Order\Models\Order;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewOrderWatchTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'now-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function order(string $number): Order
    {
        return Order::create([
            'order_number' => $number, 'customer_name' => 'C '.$number, 'customer_phone' => '01711111111',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 2430, 'status' => 'pending',
        ]);
    }

    public function test_new_check_counts_orders_created_after_the_baseline(): void
    {
        $owner = $this->owner();
        $o1 = $this->order('ZC-1');
        $o2 = $this->order('ZC-2');

        $res = $this->actingAs($owner, 'staff')->getJson(route('orders.new-check', ['after' => $o1->id]));

        $res->assertOk()->assertJson(['count' => 1, 'latest_id' => $o2->id]);
        $this->assertSame('ZC-2', $res->json('order.number'));
        $this->assertEquals(2430, $res->json('order.total'));
    }

    public function test_new_check_reports_zero_when_nothing_is_newer(): void
    {
        $owner = $this->owner();
        $o1 = $this->order('ZC-1');

        $this->actingAs($owner, 'staff')->getJson(route('orders.new-check', ['after' => $o1->id]))
            ->assertOk()->assertJson(['count' => 0, 'order' => null]);
    }

    public function test_new_check_is_not_captured_as_an_order_route(): void
    {
        // Guards the route ordering: orders/new-check must resolve to newCheck,
        // not orders/{order} treating "new-check" as an id.
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->getJson(route('orders.new-check'))
            ->assertOk()->assertJsonStructure(['count', 'latest_id', 'order']);
    }
}
