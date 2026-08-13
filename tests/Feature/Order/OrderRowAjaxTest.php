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

class OrderRowAjaxTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'row-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function order(string $status = 'pending'): Order
    {
        return Order::create([
            'order_number' => 'ZC-ROW-'.uniqid(),
            'customer_name' => 'Buyer', 'customer_phone' => '017'.rand(10000000, 99999999),
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'status' => $status, 'source' => 'website',
        ]);
    }

    public function test_ajax_verify_returns_updated_row_region(): void
    {
        $order = $this->order();

        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('orders.verify', $order), ['outcome' => 'verified']);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertArrayHasKey('order-row-'.$order->id, $res->json('regions'));
        $this->assertSame('verified', $order->fresh()->verification_status);
        // A single verify must render the row as VERIFIED (not still offering
        // "Call & Verify") — otherwise it looks like the first click did nothing.
        $rowHtml = $res->json('regions.order-row-'.$order->id);
        $this->assertStringContainsString('Verified', $rowHtml);
        $this->assertStringNotContainsString('Call &amp; Verify', $rowHtml);
    }

    public function test_ajax_status_change_returns_updated_row_region(): void
    {
        $order = $this->order();

        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('orders.status', $order), ['status' => 'confirmed']);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('order-row-'.$order->id, implode('', array_keys($res->json('regions'))));
        // The refreshed row must reflect the NEW status, not the stale one.
        $this->assertStringContainsString('Approved', $res->json('regions.order-row-'.$order->id));
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_order_detail_page_renders(): void
    {
        $order = $this->order();
        $order->items()->create(['product_name' => 'Nokshi Placemat', 'sku' => 'ZC-1', 'price' => 1550, 'quantity' => 1, 'subtotal' => 1550]);

        $this->actingAs($this->owner(), 'staff')->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Nokshi Placemat')
            ->assertSee('Fulfillment')
            ->assertSee('Update status');
    }

    public function test_detail_status_update_returns_detail_regions_not_row(): void
    {
        $order = $this->order();

        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('orders.status', $order), ['status' => 'shipped', 'detail' => 1]);

        $res->assertOk()->assertJson(['success' => true]);
        $regions = array_keys($res->json('regions'));
        $this->assertContains('order-status-badge', $regions);
        $this->assertContains('order-fulfillment', $regions);
        $this->assertStringContainsString('Shipped', $res->json('regions.order-status-badge'));
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_detail_note_add_returns_notes_region(): void
    {
        $order = $this->order();

        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('orders.comment', $order), ['note' => 'Called customer', 'detail' => 1]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertArrayHasKey('order-notes', $res->json('regions'));
        $this->assertStringContainsString('Called customer', $res->json('regions.order-notes'));
    }
}
