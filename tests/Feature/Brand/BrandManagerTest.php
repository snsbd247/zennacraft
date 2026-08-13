<?php

namespace Tests\Feature\Brand;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Brand;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Brand Owner', 'email' => 'brand-owner@zennacraft.test',
            'phone' => '+8801700000088', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_index_and_create_pages_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('brands.index'))->assertOk()->assertSee('Brands');
        $this->actingAs($owner, 'staff')->get(route('brands.create'))->assertOk()->assertSee('Add Brand');
    }

    public function test_store_creates_brand_with_slug_and_image(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('brands.store'), [
            'name' => 'Ariyan', 'position' => 100, 'status' => 'active',
            'image' => UploadedFile::fake()->image('logo.png', 300, 200),
        ])->assertRedirect(route('brands.index'));

        $brand = Brand::where('name', 'Ariyan')->firstOrFail();
        $this->assertSame('ariyan', $brand->slug);
        $this->assertSame(100, $brand->position);
        $this->assertSame('active', $brand->status);
        $this->assertNotNull($brand->image_id);
    }

    public function test_slug_stays_unique(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('brands.store'), ['name' => 'Twelve', 'status' => 'active']);
        $this->actingAs($owner, 'staff')->post(route('brands.store'), ['name' => 'Twelve', 'status' => 'active']);

        $this->assertSame(['twelve', 'twelve-2'], Brand::orderBy('id')->pluck('slug')->all());
    }

    public function test_ajax_search_partial_filters_by_name(): void
    {
        $owner = $this->owner();
        Brand::create(['name' => 'Smart Panjabi', 'slug' => 'smart-panjabi', 'status' => 'active', 'position' => 5]);
        Brand::create(['name' => 'Easy Fashion', 'slug' => 'easy-fashion', 'status' => 'active', 'position' => 4]);

        $this->actingAs($owner, 'staff')->get(route('brands.index', ['partial' => 1, 'q' => 'smart']))
            ->assertOk()->assertSee('Smart Panjabi')->assertDontSee('Easy Fashion');
    }

    public function test_toggle_status_via_ajax(): void
    {
        $owner = $this->owner();
        $brand = Brand::create(['name' => 'Robe', 'slug' => 'robe', 'status' => 'active', 'position' => 6]);

        $this->actingAs($owner, 'staff')->postJson(route('brands.toggle', $brand))
            ->assertOk()->assertJson(['status' => 'inactive']);
        $this->assertSame('inactive', $brand->fresh()->status);
    }

    public function test_update_and_destroy(): void
    {
        $owner = $this->owner();
        $brand = Brand::create(['name' => 'Old Name', 'slug' => 'old-name', 'status' => 'active', 'position' => 1]);

        $this->actingAs($owner, 'staff')->put(route('brands.update', $brand), [
            'name' => 'New Name', 'position' => 9, 'status' => 'inactive',
        ])->assertRedirect(route('brands.index'));
        $this->assertSame('New Name', $brand->fresh()->name);
        $this->assertSame('new-name', $brand->fresh()->slug);

        $this->actingAs($owner, 'staff')->deleteJson(route('brands.destroy', $brand))->assertOk();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}
