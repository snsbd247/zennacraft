<?php

namespace Tests\Feature\Campaign;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Combo\Models\Combo;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComboProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Combo Owner', 'email' => 'combo-owner@zennacraft.test', 'phone' => '+8801700000201', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function product(string $name, float $price): Product
    {
        return Product::create(['name' => $name, 'slug' => Str::slug($name).'-'.uniqid(), 'sku' => strtoupper(Str::random(6)), 'price' => $price, 'status' => 'active', 'stock' => 10]);
    }

    public function test_owner_can_create_a_combo_with_items_and_discount_is_computed(): void
    {
        $owner = $this->owner();
        $p1 = $this->product('Hoodie', 900);
        $p2 = $this->product('Cap', 350);

        $this->actingAs($owner, 'staff')->post(route('combos.store'), [
            'name' => 'Winter Combo', 'code' => 'COMBO-1838', 'status' => 'active',
            'regular_price' => 1250, 'price' => 1050,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('combos.index'));

        $combo = Combo::with('products')->where('name', 'Winter Combo')->firstOrFail();
        $this->assertSame('COMBO-1838', $combo->code);
        $this->assertEquals(1250.0, (float) $combo->regular_price);
        $this->assertEquals(1050.0, (float) $combo->price);
        $this->assertEquals(200.0, $combo->discountAmount());
        $this->assertCount(2, $combo->products);
        $this->assertEquals(2, (int) $combo->products->firstWhere('id', $p1->id)->pivot->quantity);
    }

    public function test_index_renders_and_lists_combos(): void
    {
        $owner = $this->owner();
        $combo = Combo::create(['name' => 'Listed Combo', 'slug' => 'listed-combo', 'price' => 500, 'regular_price' => 700, 'status' => 'active']);
        $combo->products()->sync([$this->product('X', 100)->id => ['quantity' => 1, 'sort_order' => 0]]);

        $this->actingAs($owner, 'staff')->get(route('combos.index'))
            ->assertOk()->assertSee('Combo Products')->assertSee('Listed Combo');
    }

    public function test_update_and_delete_combo(): void
    {
        $owner = $this->owner();
        $p = $this->product('Base', 400);
        $combo = Combo::create(['name' => 'Old', 'slug' => 'old-combo', 'price' => 400, 'regular_price' => 500, 'status' => 'active']);
        $combo->products()->sync([$p->id => ['quantity' => 1, 'sort_order' => 0]]);

        $this->actingAs($owner, 'staff')->put(route('combos.update', $combo), [
            'name' => 'New Name', 'status' => 'inactive', 'regular_price' => 600, 'price' => 450,
            'items' => [['product_id' => $p->id, 'quantity' => 3]],
        ])->assertRedirect(route('combos.index'));

        $combo->refresh()->load('products');
        $this->assertSame('New Name', $combo->name);
        $this->assertEquals(450.0, (float) $combo->price);
        $this->assertEquals(3, (int) $combo->products->first()->pivot->quantity);

        $this->actingAs($owner, 'staff')->deleteJson(route('combos.destroy', $combo))->assertOk();
        $this->assertDatabaseMissing('combos', ['id' => $combo->id]);
    }

    public function test_product_search_returns_matches(): void
    {
        $owner = $this->owner();
        $this->product('Premium Hoodie', 1200);

        $this->actingAs($owner, 'staff')->getJson(route('combos.products.search', ['q' => 'Hoodie']))
            ->assertOk()->assertJsonFragment(['name' => 'Premium Hoodie']);
    }

    public function test_a_staff_user_without_combo_permission_is_blocked(): void
    {
        $this->owner(); // seed roles/permissions
        $stranger = StaffUser::create(['name' => 'No Perms', 'email' => 'noperms@zennacraft.test', 'phone' => '+8801700000202', 'password' => 'Password123!', 'status' => 'active']);

        $this->actingAs($stranger, 'staff')->get(route('combos.index'))->assertForbidden();
    }
}
