<?php

namespace Tests\Feature\Product;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductAttribute;
use App\Modules\Product\Models\ProductAttributeValue;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrandAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'pb-owner@zennacraft.test', 'phone' => '+8801700000055', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid(), 'status' => 'active']);
    }

    public function test_default_colour_and_size_options_are_seeded_and_shown(): void
    {
        // The seed migration turns the old hard-coded lists into editable data.
        $this->assertTrue(ProductAttribute::whereRaw('LOWER(name) = ?', ['colour'])->exists());
        $this->assertTrue(ProductAttribute::whereRaw('LOWER(name) = ?', ['size'])->exists());

        $this->actingAs($this->owner(), 'staff')->get(route('products.create'))
            ->assertOk()->assertSee('WHITE')->assertSee('FREE SIZE');
    }

    public function test_product_can_be_created_with_a_brand(): void
    {
        $brand = Brand::create(['name' => 'Zenna Signature', 'slug' => 'zenna-signature', 'status' => 'active']);
        $cat = $this->category();

        $this->actingAs($this->owner(), 'staff')->post(route('products.store'), [
            'name' => 'Kantha Deluxe', 'category_id' => $cat->id, 'brand_id' => $brand->id,
            'list_price' => 2500, 'stock' => 8, 'status' => 'active',
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Kantha Deluxe')->firstOrFail();
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Zenna Signature', $product->brand->name);
    }

    public function test_brand_shows_on_the_storefront_product_page(): void
    {
        $brand = Brand::create(['name' => 'Jamalpur House', 'slug' => 'jamalpur-house', 'status' => 'active']);
        $product = Product::create([
            'category_id' => $this->category()->id, 'brand_id' => $brand->id,
            'name' => 'Nakshi Throw', 'slug' => 'nakshi-throw', 'sku' => 'NT-1',
            'price' => 1900, 'stock' => 5, 'status' => 'active',
        ]);

        $this->get(route('storefront.product.show', $product->slug))
            ->assertOk()->assertSee('Jamalpur House');
    }

    public function test_variant_keeps_a_custom_sku_when_provided(): void
    {
        $cat = $this->category();

        $this->actingAs($this->owner(), 'staff')->post(route('products.store'), [
            'name' => 'Colour Kantha', 'category_id' => $cat->id, 'list_price' => 1200, 'stock' => 10, 'status' => 'active',
            'variants' => [
                ['label' => 'White', 'color' => 'White', 'size' => '', 'sku' => 'ZC-WHITE-001', 'price' => 1200, 'stock' => 4],
            ],
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Colour Kantha')->firstOrFail();
        $this->assertSame('ZC-WHITE-001', $product->variants()->first()->sku);
    }

    public function test_add_and_remove_attribute_options(): void
    {
        $owner = $this->owner();

        // Add a size option.
        $res = $this->actingAs($owner, 'staff')->postJson(route('products.attr-options.store'), ['group' => 'size', 'name' => '5FT X 7FT']);
        $res->assertOk()->assertJson(['ok' => true, 'name' => '5FT X 7FT']);
        $id = $res->json('id');
        $this->assertDatabaseHas('product_attribute_values', ['id' => $id, 'name' => '5FT X 7FT']);

        // It shows on the product form.
        $this->actingAs($owner, 'staff')->get(route('products.create'))->assertOk()->assertSee('5FT X 7FT');

        // Remove it.
        $this->actingAs($owner, 'staff')->deleteJson(route('products.attr-options.destroy', ['attributeValue' => $id]))
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('product_attribute_values', ['id' => $id]);
    }

    public function test_adding_an_attribute_option_requires_permission(): void
    {
        // A staff member without product.update cannot add options.
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Viewer', 'email' => 'viewer@zennacraft.test', 'phone' => '+8801700000066', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'staff')->firstOrFail());

        $this->actingAs($staff, 'staff')->postJson(route('products.attr-options.store'), ['group' => 'size', 'name' => 'X'])
            ->assertForbidden();
    }
}
