<?php

namespace Tests\Feature\LandingPage;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoLandingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'al-owner@zennacraft.test', 'phone' => '+8801700000077', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function category(): Category
    {
        return Category::create(['name' => 'C', 'slug' => 'c-'.uniqid(), 'status' => 'active']);
    }

    public function test_creating_a_product_auto_creates_a_linked_landing_page(): void
    {
        $this->actingAs($this->owner(), 'staff')->post(route('products.store'), [
            'name' => 'Auto LP Kantha', 'category_id' => $this->category()->id,
            'list_price' => 2000, 'stock' => 5, 'status' => 'active',
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Auto LP Kantha')->firstOrFail();
        $lp = LandingPage::where('product_id', $product->id)->first();

        $this->assertNotNull($lp);
        $this->assertSame('active', $lp->status);
        $this->assertSame('sales', $lp->template);
        $this->assertSame([$product->id], $lp->suggested_products);
        $this->assertNotEmpty($lp->slug);
    }

    public function test_no_duplicate_landing_when_service_called_twice(): void
    {
        $product = Product::create(['category_id' => $this->category()->id, 'name' => 'Dup', 'slug' => 'dup', 'sku' => 'D-1', 'price' => 100, 'stock' => 1, 'status' => 'active']);
        $svc = app(\App\Modules\LandingPage\Services\LandingPageService::class);

        $first = $svc->createForProduct($product);
        $second = $svc->createForProduct($product);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LandingPage::where('product_id', $product->id)->count());
    }

    public function test_auto_landing_respects_the_off_toggle(): void
    {
        app(SettingService::class)->set('general', 'auto_landing_for_products', false, 'boolean');

        $this->actingAs($this->owner(), 'staff')->post(route('products.store'), [
            'name' => 'No LP', 'category_id' => $this->category()->id,
            'list_price' => 500, 'stock' => 2, 'status' => 'active',
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'No LP')->firstOrFail();
        $this->assertNull(LandingPage::where('product_id', $product->id)->first());
    }

    public function test_toggle_route_saves_the_setting(): void
    {
        $this->actingAs($this->owner(), 'staff')->post(route('landing.auto-create'), ['auto_create' => '0'])->assertRedirect();
        $this->assertFalse(filter_var(app(SettingService::class)->get('general', 'auto_landing_for_products'), FILTER_VALIDATE_BOOLEAN));
    }
}
