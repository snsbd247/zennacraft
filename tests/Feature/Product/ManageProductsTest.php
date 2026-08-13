<?php

namespace Tests\Feature\Product;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Manage Products" list page (Products sidebar group), added
 * 2026-07-25 as the first Products page in the Studio rebuild.
 */
class ManageProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Product Owner', 'email' => 'prod-owner@zennacraft.test',
            'phone' => '+8801700000044', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid(), 'status' => 'active']);

        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'Hoodie', 'slug' => 'hoodie-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 1050, 'compare_price' => 1250,
            'cost_price' => 200, 'stock' => 5000, 'status' => 'active',
        ], $overrides));
    }

    public function test_manage_products_lists_products(): void
    {
        $this->product(['name' => 'Premium China Hoodie']);

        $response = $this->actingAs($this->owner(), 'staff')->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Product Manage');
        $response->assertSee('Premium China Hoodie');
        $response->assertSee('Publish');
    }

    public function test_manage_products_search_and_stock_filter(): void
    {
        $this->product(['name' => 'Findable Tee', 'sku' => 'FIND-1', 'stock' => 5000]);
        $this->product(['name' => 'Other Product', 'stock' => 3]); // low stock

        $owner = $this->owner();

        $search = $this->actingAs($owner, 'staff')->get(route('products.index', ['q' => 'Findable']));
        $search->assertOk()->assertSee('Findable Tee')->assertDontSee('Other Product');

        $low = $this->actingAs($owner, 'staff')->get(route('products.index', ['stock' => 'low']));
        $low->assertOk()->assertSee('Other Product')->assertDontSee('Findable Tee');
    }

    public function test_edit_page_renders_and_update_saves_including_short_description(): void
    {
        $owner = $this->owner();
        $product = $this->product(['name' => 'Editable Hoodie']);

        $this->actingAs($owner, 'staff')->get(route('products.edit', $product))->assertOk()->assertSee('Short Description');

        $response = $this->actingAs($owner, 'staff')->put(route('products.update', $product), [
            'name' => 'Editable Hoodie',
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'list_price' => 1500,
            'discount' => 100,
            'stock' => 4321,
            'short_description' => 'A cosy winter hoodie.',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('products.index'));
        $product->refresh();
        // sale price = list 1500 - discount 100
        $this->assertEquals(1400.0, (float) $product->price);
        $this->assertEquals(1500.0, (float) $product->compare_price);
        $this->assertSame('A cosy winter hoodie.', $product->short_description);
        $this->assertSame(4321, (int) $product->stock);
    }

    public function test_add_product_page_and_store_with_variants(): void
    {
        $owner = $this->owner();
        $category = Category::create(['name' => 'Winter', 'slug' => 'winter-'.uniqid(), 'status' => 'active']);

        $this->actingAs($owner, 'staff')->get(route('products.create'))->assertOk()->assertSee('Basic Information');

        $response = $this->actingAs($owner, 'staff')->post(route('products.store'), [
            'name' => 'Brand New Hoodie',
            'category_id' => $category->id,
            'list_price' => 1250,
            'discount' => 200,
            'stock' => 500,
            'description' => '<p>Warm hoodie.</p>',
            'status' => 'active',
            'variants' => [
                ['label' => 'M', 'price' => 1050, 'type' => 'Size'],
                ['label' => 'L', 'price' => 1050, 'type' => 'Size'],
            ],
        ]);

        $response->assertRedirect(route('products.index'));
        $product = Product::where('name', 'Brand New Hoodie')->first();
        $this->assertNotNull($product);
        $this->assertEquals(1050.0, (float) $product->price);       // 1250 - 200
        $this->assertEquals(1250.0, (float) $product->compare_price);
        $this->assertSame(2, $product->variants()->count());
        $this->assertNotNull($product->sku); // auto-generated
    }

    public function test_store_creates_colour_size_combination_variants(): void
    {
        $owner = $this->owner();
        $category = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid(), 'status' => 'active']);

        $this->actingAs($owner, 'staff')->post(route('products.store'), [
            'name' => 'Kantha Throw',
            'category_id' => $category->id,
            'list_price' => 1500,
            'stock' => 0,
            'status' => 'active',
            'variants' => [
                ['label' => 'White / M', 'color' => 'White', 'size' => 'M', 'price' => 1500, 'stock' => 5],
                ['label' => 'White / L', 'color' => 'White', 'size' => 'L', 'price' => 1600, 'stock' => 3],
                ['label' => 'Red / M', 'color' => 'Red', 'size' => 'M', 'price' => 1550, 'stock' => 0],
            ],
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Kantha Throw')->firstOrFail();
        $this->assertSame(3, $product->variants()->count());

        $wm = $product->variants()->where('name', 'White / M')->firstOrFail();
        $this->assertSame(['Color' => 'White', 'Size' => 'M'], $wm->option_values);
        $this->assertEquals(1500, (float) $wm->price);
        $this->assertSame(5, (int) $wm->stock);

        $wl = $product->variants()->where('name', 'White / L')->firstOrFail();
        $this->assertEquals(1600, (float) $wl->price);
        $this->assertSame(3, (int) $wl->stock);

        // PDP shows the combination options.
        $this->get(route('storefront.product.show', $product->slug))->assertOk()
            ->assertSee('White / M')->assertSee('White / L')->assertSee('Red / M');
    }

    public function test_toggle_status_publishes_and_unpublishes(): void
    {
        $owner = $this->owner();
        $product = $this->product(['status' => 'active']);

        $this->actingAs($owner, 'staff')->post(route('products.toggle-status', $product))->assertRedirect();
        $this->assertSame('inactive', $product->fresh()->status);

        $this->actingAs($owner, 'staff')->post(route('products.toggle-status', $product))->assertRedirect();
        $this->assertSame('active', $product->fresh()->status);
    }

    public function test_copy_product_clones_it_unpublished(): void
    {
        $owner = $this->owner();
        $product = $this->product(['name' => 'Original']);
        $product->variants()->create(['name' => 'White', 'sku' => 'V-'.uniqid(), 'price' => 1050, 'stock' => 10, 'status' => 'active']);

        $response = $this->actingAs($owner, 'staff')->post(route('products.duplicate', $product));

        $response->assertRedirect();
        $copy = Product::where('name', 'Original (Copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame('inactive', $copy->status);
        $this->assertSame(1, $copy->variants()->count());
    }

    public function test_delete_product(): void
    {
        $owner = $this->owner();
        $product = $this->product();

        $this->actingAs($owner, 'staff')->delete(route('products.destroy', $product))->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_print_label_and_export_customers(): void
    {
        $owner = $this->owner();
        $product = $this->product();

        $this->actingAs($owner, 'staff')->get(route('products.print', $product))->assertOk()->assertSee('ZENNA CRAFT');
        $this->actingAs($owner, 'staff')->get(route('products.export-customers', $product))->assertOk();
    }

    public function test_add_product_stores_a_per_variant_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $owner = $this->owner();
        $category = Category::create(['name' => 'Tops', 'slug' => 'tops-'.uniqid(), 'status' => 'active']);

        $response = $this->actingAs($owner, 'staff')->post(route('products.store'), [
            'name' => 'Imaged Product',
            'category_id' => $category->id,
            'list_price' => 900,
            'stock' => 10,
            'description' => 'x',
            'status' => 'active',
            'variants' => [
                ['label' => 'GREEN', 'price' => 900, 'type' => 'Color', 'image' => \Illuminate\Http\UploadedFile::fake()->image('green.jpg', 200, 200)],
            ],
        ]);

        $response->assertRedirect();
        $variant = \App\Modules\Product\Models\ProductVariant::where('name', 'GREEN')->first();
        $this->assertNotNull($variant);
        $this->assertNotNull($variant->image_id, 'The uploaded variant image should be linked.');
    }

    public function test_manage_products_requires_product_view_permission(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'No Priv', 'email' => 'noprod@zennacraft.test',
            'phone' => '+8801700000045', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'staff')->firstOrFail());

        // staff role has no product.view.
        $this->actingAs($staff, 'staff')->get(route('products.index'))->assertForbidden();
    }
}
