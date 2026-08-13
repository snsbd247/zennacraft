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

class OrderCustomerEditTest extends TestCase
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
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'cust-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ZC-CU-'.uniqid(), 'customer_name' => 'Old Name', 'customer_phone' => '01711111111',
            'address' => 'Old address', 'subtotal' => 500, 'delivery_fee' => 80, 'total' => 580, 'status' => 'pending', 'source' => 'website',
        ]);
    }

    private function ajax()
    {
        return $this->actingAs($this->owner(), 'staff')->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    }

    public function test_updates_name_phone_and_address_and_returns_customer_payload(): void
    {
        $order = $this->order();

        $res = $this->ajax()->post(route('orders.customer.update', $order), [
            'customer_name' => '  Rahim Uddin  ',
            'customer_phone' => ' 01822223333 ',
            'address' => "House 5, Road 2\nDhanmondi, Dhaka",
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        // Values are trimmed and echoed back for the in-place DOM patch.
        $this->assertSame('Rahim Uddin', $res->json('customer.name'));
        $this->assertSame('01822223333', $res->json('customer.phone'));
        $this->assertSame('8801822223333', $res->json('customer.phone_digits'));

        $order->refresh();
        $this->assertSame('Rahim Uddin', $order->customer_name);
        $this->assertSame('01822223333', $order->customer_phone);
        $this->assertStringContainsString('Dhanmondi', $order->address);
    }

    public function test_validation_rejects_blank_fields(): void
    {
        $order = $this->order();

        $this->ajax()->post(route('orders.customer.update', $order), [
            'customer_name' => '', 'customer_phone' => '', 'address' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_name', 'customer_phone', 'address']);

        // Untouched on a failed request.
        $this->assertSame('Old Name', $order->fresh()->customer_name);
    }

    public function test_requires_authentication(): void
    {
        $order = $this->order();

        // Staff guard redirects guests to the login screen.
        $this->post(route('orders.customer.update', $order), ['customer_name' => 'X', 'customer_phone' => '019', 'address' => 'Y'])
            ->assertRedirect(route('staff.login'));
        $this->assertSame('Old Name', $order->fresh()->customer_name);
    }
}
