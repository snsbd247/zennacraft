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

class PosTest extends TestCase
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
        $staff = StaffUser::create(['name' => 'Cashier', 'email' => 'pos-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $this->ownerUser = $staff;
    }

    private function product(int $stock = 10, float $price = 500): Product
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid(), 'status' => 'active']);

        return Product::create(['category_id' => $cat->id, 'name' => 'Kantha', 'slug' => 'k-'.uniqid(), 'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => $price, 'stock' => $stock, 'status' => 'active']);
    }

    public function test_pos_terminal_page_renders(): void
    {
        $this->actingAs($this->owner(), 'staff')->get(route('pos.index'))
            ->assertOk()->assertSee('Point of Sale')->assertSee('Complete sale');
    }

    public function test_pos_sale_creates_a_delivered_order_with_pos_source(): void
    {
        $product = $this->product(10, 500);

        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('pos.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'customer_name' => 'Rahim',
                'discount' => 100,
                'paid' => 1000,
            ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($res->json('order_number'));

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('pos', $order->source);
        $this->assertSame('delivered', $order->status);
        $this->assertEquals(900, (float) $order->total);     // 1000 - 100 discount
        $this->assertEquals(900, (float) $order->paid_amount); // paid in full
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Rahim', $order->customer_name);
    }

    public function test_pos_product_search_returns_matches(): void
    {
        $product = $this->product();

        $res = $this->actingAs($this->owner(), 'staff')
            ->getJson(route('pos.products.search', ['q' => 'Kantha']));

        $res->assertOk();
        $this->assertNotEmpty($res->json('results'));
        $this->assertSame($product->id, $res->json('results.0.id'));
    }
}
