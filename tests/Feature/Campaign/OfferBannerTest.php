<?php

namespace Tests\Feature\Campaign;

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

class OfferBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'OB Owner', 'email' => 'ob-owner@zennacraft.test', 'phone' => '+8801700000204', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_index_and_create_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('offer-banners.index'))->assertOk()->assertSee('Banners');
        $this->actingAs($owner, 'staff')->get(route('offer-banners.create'))->assertOk()->assertSee('Banner placement');
    }

    public function test_store_creates_a_banner_on_an_offer_placement(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('offer-banners.store'), [
            'placement' => 'before_footer',
            'title' => 'Eid Mega Offer',
            'subtitle' => 'Up to 40% off',
            'button_text' => 'Shop Now', 'button_url' => '/shop',
            'active' => '1',
            'image' => UploadedFile::fake()->image('offer.jpg', 1200, 400),
        ])->assertRedirect(route('offer-banners.index'));

        $banner = StorefrontSlider::where('title', 'Eid Mega Offer')->firstOrFail();
        $this->assertSame('before_footer', $banner->placement);
        $this->assertTrue((bool) $banner->active);
        $this->assertNotNull($banner->desktop_image_id);
    }

    public function test_store_rejects_a_non_offer_placement(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('offer-banners.store'), [
            'placement' => 'home_hero', // a slider placement, not an offer one
            'title' => 'Should Fail',
            'image' => UploadedFile::fake()->image('x.jpg', 100, 100),
        ])->assertSessionHasErrors('placement');
    }

    public function test_index_only_lists_offer_placements(): void
    {
        $owner = $this->owner();
        $hero = StorefrontSlider::create(['placement' => 'home_hero', 'title' => 'A Homepage Hero', 'active' => true, 'sort_order' => 0]);
        $offer = StorefrontSlider::create(['placement' => 'after_top_selling_1', 'title' => 'An Offer Banner', 'active' => true, 'sort_order' => 0]);

        // The list shows placement labels + a per-row edit link (not titles),
        // so exclusion is proven by the hero's edit link being absent.
        $this->actingAs($owner, 'staff')->get(route('offer-banners.index'))
            ->assertOk()
            ->assertSee('after-top-selling-first-banner')
            ->assertSee('offer-banners/'.$offer->id.'/edit')
            ->assertDontSee('offer-banners/'.$hero->id.'/edit');
    }

    public function test_toggle_and_delete(): void
    {
        $owner = $this->owner();
        $banner = StorefrontSlider::create(['placement' => 'before_footer', 'title' => 'Toggle Me', 'active' => true, 'sort_order' => 0]);

        $this->actingAs($owner, 'staff')->postJson(route('offer-banners.toggle', $banner))->assertOk()->assertJson(['active' => false]);
        $this->assertFalse((bool) $banner->fresh()->active);

        $this->actingAs($owner, 'staff')->deleteJson(route('offer-banners.destroy', $banner))->assertOk();
        $this->assertDatabaseMissing('storefront_sliders', ['id' => $banner->id]);
    }
}
