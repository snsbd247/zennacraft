<?php

namespace Tests\Feature\Storefront;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Storefront\Models\StorefrontSlider;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Studio manager for the storefront homepage banners. Each slider targets one
 * placement (hero / side / promo) and carries a single responsive image. Uses
 * theme.view (read) + theme.update (mutations); saving flushes storefront cache.
 */
class SliderManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Slider Owner', 'email' => 'slider-owner@zennacraft.test',
            'phone' => '+8801700000077', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_every_placement_index_and_create_page_renders(): void
    {
        $owner = $this->owner();
        foreach (['hero' => 'Hero Slider', 'side' => 'Side Banner', 'promo' => 'Promo Banner'] as $seg => $label) {
            $this->actingAs($owner, 'staff')->get(route("sliders.$seg.index"))->assertOk()->assertSee($label);
            $this->actingAs($owner, 'staff')->get(route("sliders.$seg.create"))->assertOk()->assertSee($label);
        }
    }

    public function test_store_creates_slider_with_placement_and_single_image(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('sliders.store'), [
            'placement' => 'home_side',
            'title' => 'Free delivery banner',
            'subtitle' => 'Cash on delivery',
            'button_text' => 'Order now',
            'button_url' => '/products',
            'sort_order' => 1,
            'active' => '1',
            'image' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        ])->assertRedirect(route('sliders.side.index'));

        $slider = StorefrontSlider::where('title', 'Free delivery banner')->firstOrFail();
        $this->assertSame('home_side', $slider->placement);
        $this->assertTrue((bool) $slider->active);
        $this->assertNotNull($slider->desktop_image_id, 'The single image should be linked.');
    }

    public function test_store_allows_an_image_only_slider_with_no_title(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('sliders.store'), [
            'placement' => 'home_hero',
            // no title — a clean image-only banner
            'sort_order' => 0,
            'active' => '1',
            'image' => UploadedFile::fake()->image('banner.jpg', 720, 300),
        ])->assertRedirect(route('sliders.hero.index'));

        $slider = StorefrontSlider::where('placement', 'home_hero')->latest('id')->firstOrFail();
        $this->assertSame('', (string) $slider->title);
        $this->assertNotNull($slider->desktop_image_id);
    }

    public function test_update_can_move_a_slider_to_another_placement(): void
    {
        $owner = $this->owner();
        $slider = StorefrontSlider::create(['placement' => 'home_hero', 'title' => 'Old', 'active' => true, 'sort_order' => 0]);

        $this->actingAs($owner, 'staff')->put(route('sliders.update', $slider), [
            'placement' => 'home_promo', 'title' => 'New title', 'sort_order' => 5, 'active' => '1',
        ])->assertRedirect(route('sliders.promo.index'));

        $fresh = $slider->fresh();
        $this->assertSame('home_promo', $fresh->placement);
        $this->assertSame('New title', $fresh->title);
        $this->assertSame(5, $fresh->sort_order);
    }

    public function test_index_only_lists_its_own_placement(): void
    {
        $owner = $this->owner();
        StorefrontSlider::create(['placement' => 'home_hero', 'title' => 'Hero one', 'active' => true, 'sort_order' => 0]);
        StorefrontSlider::create(['placement' => 'home_side', 'title' => 'Side one', 'active' => true, 'sort_order' => 0]);

        $this->actingAs($owner, 'staff')->get(route('sliders.hero.index'))->assertSee('Hero one')->assertDontSee('Side one');
        $this->actingAs($owner, 'staff')->get(route('sliders.side.index'))->assertSee('Side one')->assertDontSee('Hero one');
    }

    public function test_toggle_status_via_ajax(): void
    {
        $owner = $this->owner();
        $slider = StorefrontSlider::create(['placement' => 'home_hero', 'title' => 'Toggle me', 'active' => true, 'sort_order' => 0]);

        $this->actingAs($owner, 'staff')->postJson(route('sliders.toggle', $slider))
            ->assertOk()->assertJson(['active' => false]);
        $this->assertFalse((bool) $slider->fresh()->active);
    }

    public function test_destroy_via_ajax(): void
    {
        $owner = $this->owner();
        $slider = StorefrontSlider::create(['placement' => 'home_hero', 'title' => 'Delete me', 'active' => true, 'sort_order' => 0]);

        $this->actingAs($owner, 'staff')->deleteJson(route('sliders.destroy', $slider))->assertOk();
        $this->assertDatabaseMissing('storefront_sliders', ['id' => $slider->id]);
    }

    public function test_uncheck_active_deactivates_slider(): void
    {
        $owner = $this->owner();
        $slider = StorefrontSlider::create(['placement' => 'home_hero', 'title' => 'Was active', 'active' => true, 'sort_order' => 0]);

        // No "active" key in the payload — the checkbox was unticked.
        $this->actingAs($owner, 'staff')->put(route('sliders.update', $slider), [
            'placement' => 'home_hero', 'title' => 'Was active', 'sort_order' => 0,
        ])->assertRedirect(route('sliders.hero.index'));

        $this->assertFalse((bool) $slider->fresh()->active);
    }
}
