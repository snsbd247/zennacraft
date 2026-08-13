<?php

namespace Tests\Feature\Inventory;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockPageTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Stock Owner', 'email' => 'stock-owner@zennacraft.test', 'phone' => '+8801700000240', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function product(string $name = 'Tote Bag', int $stock = 10): Product
    {
        return Product::create(['name' => $name, 'slug' => Str::slug($name).'-'.uniqid(), 'sku' => strtoupper(Str::random(6)), 'price' => 900, 'status' => 'active', 'stock' => $stock]);
    }

    public function test_index_lists_products_with_stock(): void
    {
        $owner = $this->owner();
        $this->product('Visible Product', 42);

        $this->actingAs($owner, 'staff')->get(route('stock.index'))
            ->assertOk()->assertSee('Stock')->assertSee('Visible Product');
    }

    public function test_adjusting_a_simple_product_updates_its_stock_and_logs_it(): void
    {
        $owner = $this->owner();
        $product = $this->product('Simple', 10);

        $this->actingAs($owner, 'staff')->postJson(route('stock.update'), ['product_id' => $product->id, 'stock' => 25])
            ->assertOk()->assertJson(['total' => 25, 'status' => 'in']);

        $this->assertSame(25, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_logs', ['product_id' => $product->id, 'new_stock' => 25, 'previous_stock' => 10]);
    }

    public function test_adjusting_a_variant_updates_variant_stock_and_product_total(): void
    {
        $owner = $this->owner();
        $product = Product::create(['name' => 'Variant Product', 'slug' => 'vp-'.uniqid(), 'sku' => 'VP-1', 'price' => 1000, 'status' => 'active', 'stock' => 0]);
        $v1 = ProductVariant::create(['product_id' => $product->id, 'name' => 'Small', 'sku' => 'VP-1-S', 'price' => 1000, 'stock' => 3, 'status' => 'active']);
        $v2 = ProductVariant::create(['product_id' => $product->id, 'name' => 'Large', 'sku' => 'VP-1-L', 'price' => 1000, 'stock' => 5, 'status' => 'active']);

        // set Small to 10 -> product total should be 10 + 5 = 15
        $this->actingAs($owner, 'staff')->postJson(route('stock.update'), ['product_id' => $product->id, 'variant_id' => $v1->id, 'stock' => 10])
            ->assertOk()->assertJson(['stock' => 10, 'total' => 15]);

        $this->assertSame(10, (int) $v1->fresh()->stock);
        $this->assertDatabaseHas('variant_inventory_logs', ['product_variant_id' => $v1->id, 'new_stock' => 10, 'previous_stock' => 3]);
    }

    public function test_base_stock_edit_is_rejected_for_a_variant_product(): void
    {
        $owner = $this->owner();
        $product = Product::create(['name' => 'Has Variants', 'slug' => 'hv-'.uniqid(), 'sku' => 'HV-1', 'price' => 1000, 'status' => 'active', 'stock' => 0]);
        ProductVariant::create(['product_id' => $product->id, 'name' => 'Only', 'sku' => 'HV-1-O', 'price' => 1000, 'stock' => 4, 'status' => 'active']);

        $this->actingAs($owner, 'staff')->postJson(route('stock.update'), ['product_id' => $product->id, 'stock' => 99])
            ->assertStatus(422);
    }

    public function test_staff_without_product_permission_is_blocked(): void
    {
        $this->owner();
        $stranger = StaffUser::create(['name' => 'No Perm', 'email' => 'noperm-stock@zennacraft.test', 'password' => 'Password123!', 'status' => 'active']);

        $this->actingAs($stranger, 'staff')->get(route('stock.index'))->assertForbidden();
        $this->actingAs($stranger, 'staff')->postJson(route('stock.update'), ['product_id' => 1, 'stock' => 1])->assertForbidden();
    }
}
