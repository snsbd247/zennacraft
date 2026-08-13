<?php

namespace Tests\Feature\Product;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductAttribute;
use App\Modules\Product\Models\ProductDamage;
use App\Modules\Review\Models\ProductReview;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Studio "Products" sub-modules: Attribute/Size, Variant, Products Review,
 * Product View Report, Damage Products.
 */
class ProductSubmodulesTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create([
            'name' => 'Owner', 'email' => 'submod-owner@zennacraft.test',
            'phone' => '+8801700000099', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    protected function product(): Product
    {
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid(), 'status' => 'active']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Hoodie', 'slug' => 'hoodie-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 1000, 'cost_price' => 400, 'stock' => 10, 'status' => 'active',
        ]);
    }

    public function test_all_submodule_index_pages_render(): void
    {
        $owner = $this->owner();
        foreach (['products.attributes.index', 'products.variants.index', 'products.reviews.index', 'products.view-report.index', 'products.damages.index'] as $route) {
            $this->actingAs($owner, 'staff')->get(route($route))->assertOk();
        }
    }

    public function test_attribute_and_variant_can_be_created_and_toggled(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('products.attributes.store'), ['name' => 'Colour'])->assertRedirect(route('products.attributes.index'));
        $attribute = ProductAttribute::where('name', 'Colour')->firstOrFail();
        $this->assertSame('active', $attribute->status);

        $this->actingAs($owner, 'staff')->post(route('products.attributes.toggle', $attribute))->assertRedirect();
        $this->assertSame('inactive', $attribute->fresh()->status);

        $this->actingAs($owner, 'staff')->post(route('products.variants.store'), ['attribute_id' => $attribute->id, 'name' => 'MAGENTA'])->assertRedirect(route('products.variants.index'));
        $this->assertDatabaseHas('product_attribute_values', ['attribute_id' => $attribute->id, 'name' => 'MAGENTA']);
    }

    public function test_damage_record_is_created_with_items_and_computed_total(): void
    {
        $owner = $this->owner();
        $product = $this->product();

        $this->actingAs($owner, 'staff')->post(route('products.damages.store'), [
            'damage_date' => '2026-01-10',
            'note' => 'Water damage',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => 100],
                ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 50],
            ],
        ])->assertRedirect(route('products.damages.index'));

        $damage = ProductDamage::firstOrFail();
        $this->assertSame(2, $damage->items()->count());
        $this->assertEquals(400.0, (float) $damage->total_amount); // 3*100 + 2*50
        $this->assertSame('Hoodie', $damage->items()->first()->product_name);
    }

    public function test_review_status_can_be_toggled(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $review = ProductReview::create([
            'product_id' => $product->id, 'rating' => 5, 'status' => 'pending',
            'reviewer_name' => 'Jony', 'body' => 'Great', 'is_verified_purchase' => true,
        ]);

        $this->actingAs($owner, 'staff')->post(route('products.reviews.toggle', $review))->assertRedirect();
        $this->assertSame('approved', $review->fresh()->status);
    }
}
