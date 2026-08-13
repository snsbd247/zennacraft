<?php

namespace Tests\Feature\Purchase;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\Supplier;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create([
            'name' => 'Buyer', 'email' => 'purchaser@zennacraft.test',
            'phone' => '+8801700000077', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid(), 'status' => 'active']);

        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'Honey 1kg', 'slug' => 'honey-'.uniqid(),
            'sku' => 'HNY-'.strtoupper(uniqid()), 'price' => 1200, 'cost_price' => 0, 'stock' => 5, 'status' => 'active',
        ], $overrides));
    }

    public function test_index_and_create_pages_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('purchases.index'))->assertOk()->assertSee('Purchase');
        $this->actingAs($owner, 'staff')->get(route('purchases.create'))->assertOk()->assertSee('Add Purchase');
    }

    public function test_supplier_quick_add_redirects_to_create_selected(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('suppliers.store'), ['name' => 'Creative'])
            ->assertRedirect();
        $this->assertDatabaseHas('suppliers', ['name' => 'Creative']);
    }

    public function test_purchase_is_created_with_items_total_and_increments_stock(): void
    {
        $owner = $this->owner();
        $supplier = Supplier::create(['name' => 'Lora']);
        $product = $this->product(['sku' => 'ABC', 'stock' => 5]);

        $this->actingAs($owner, 'staff')->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-01-10',
            'invoice_no' => '45',
            'paid_amount' => 20,
            'items' => [
                ['product_code' => 'ABC', 'purchase_price' => 10, 'quantity' => 3],
                ['product_code' => 'NOMATCH', 'purchase_price' => 5, 'quantity' => 2],
            ],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::firstOrFail();
        $this->assertSame(2, $purchase->items()->count());
        $this->assertEquals(40.0, (float) $purchase->total_amount); // 3*10 + 2*5
        $this->assertEquals(20.0, (float) $purchase->paid_amount);
        $this->assertEquals(20.0, $purchase->due_amount);
        $this->assertSame(8, (int) $product->fresh()->stock); // 5 + 3 purchased
        $this->assertSame($product->id, $purchase->items()->where('product_code', 'ABC')->first()->product_id);
    }

    public function test_deleting_a_purchase_reverses_the_stock(): void
    {
        $owner = $this->owner();
        $product = $this->product(['sku' => 'XYZ', 'stock' => 5]);

        $this->actingAs($owner, 'staff')->post(route('purchases.store'), [
            'purchase_date' => '2026-01-10',
            'items' => [['product_code' => 'XYZ', 'purchase_price' => 10, 'quantity' => 4]],
        ]);
        $this->assertSame(9, (int) $product->fresh()->stock);

        $purchase = Purchase::firstOrFail();
        $this->actingAs($owner, 'staff')->delete(route('purchases.destroy', $purchase))->assertRedirect();
        $this->assertSame(5, (int) $product->fresh()->stock);
    }
}
