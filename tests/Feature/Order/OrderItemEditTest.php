<?php

namespace Tests\Feature\Order;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemEditTest extends TestCase
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
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'edit-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    private function product(float $price = 500, float $cost = 200): Product
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid(), 'status' => 'active']);

        return Product::create(['category_id' => $cat->id, 'name' => 'Widget', 'slug' => 'w-'.uniqid(), 'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => $price, 'cost_price' => $cost, 'stock' => 50, 'status' => 'active']);
    }

    private function order(string $status = 'pending'): Order
    {
        $order = Order::create([
            'order_number' => 'ZC-ED-'.uniqid(), 'customer_name' => 'B', 'customer_phone' => '017'.rand(10000000, 99999999),
            'address' => 'Dhaka', 'subtotal' => 500, 'delivery_fee' => 80, 'total' => 580, 'status' => $status, 'source' => 'website',
        ]);
        $order->items()->create(['product_name' => 'Seed', 'sku' => 'S1', 'price' => 500, 'unit_cost' => 200, 'quantity' => 1, 'subtotal' => 500]);

        return $order;
    }

    private function ajax()
    {
        return $this->actingAs($this->owner(), 'staff')->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    }

    public function test_add_item_recalculates_and_returns_regions(): void
    {
        $order = $this->order();
        $p = $this->product(300, 100);

        $res = $this->ajax()->post(route('orders.items.store', $order), ['product_id' => $p->id, 'quantity' => 2]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertArrayHasKey('order-items', $res->json('regions'));
        $this->assertArrayHasKey('order-summary', $res->json('regions'));

        $order->refresh();
        $this->assertSame(2, $order->items()->count());
        // subtotal 500 + (300*2)=1100 ; total 1100 - 0 + 80 = 1180
        $this->assertEquals(1100, (float) $order->subtotal);
        $this->assertEquals(1180, (float) $order->total);
        // profit = total - product_cost(200 + 100*2=400) = 1180 - 400
        $this->assertEquals(780, (float) $order->gross_profit);
    }

    public function test_update_item_price_and_qty_recalculates(): void
    {
        $order = $this->order();
        $item = $order->items()->first();

        $res = $this->ajax()->patch(route('orders.items.update', [$order, $item]), ['quantity' => 3, 'price' => 400]);

        $res->assertOk()->assertJson(['success' => true]);
        $order->refresh();
        $this->assertEquals(1200, (float) $order->items()->first()->subtotal);
        $this->assertEquals(1200, (float) $order->subtotal);
        $this->assertEquals(1280, (float) $order->total);
    }

    public function test_discount_reduces_total_and_is_capped_at_subtotal(): void
    {
        $order = $this->order();

        $this->ajax()->post(route('orders.discount', $order), ['discount' => 100])->assertOk();
        $order->refresh();
        $this->assertEquals(100, (float) $order->manual_discount_amount);
        $this->assertEquals(480, (float) $order->total); // 500 - 100 + 80

        // Over-cap discount is clamped to subtotal (total floor at delivery only).
        $this->ajax()->post(route('orders.discount', $order), ['discount' => 99999])->assertOk();
        $order->refresh();
        $this->assertEquals(500, (float) $order->manual_discount_amount);
        $this->assertEquals(80, (float) $order->total);
    }

    public function test_cannot_remove_last_item(): void
    {
        $order = $this->order();
        $item = $order->items()->first();

        $this->ajax()->delete(route('orders.items.destroy', [$order, $item]))->assertStatus(422);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_remove_item_when_multiple(): void
    {
        $order = $this->order();
        $p = $this->product();
        $this->ajax()->post(route('orders.items.store', $order), ['product_id' => $p->id, 'quantity' => 1])->assertOk();
        $this->assertSame(2, $order->fresh()->items()->count());

        $extra = $order->items()->latest('id')->first();
        $this->ajax()->delete(route('orders.items.destroy', [$order, $extra]))->assertOk();
        $this->assertSame(1, $order->fresh()->items()->count());
    }

    public function test_shipped_order_is_not_editable(): void
    {
        $order = $this->order('shipped');
        $p = $this->product();

        $this->ajax()->post(route('orders.items.store', $order), ['product_id' => $p->id, 'quantity' => 1])->assertStatus(422);
        $this->assertSame(1, $order->fresh()->items()->count());
    }
}
