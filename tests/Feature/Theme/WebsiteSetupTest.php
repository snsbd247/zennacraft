<?php

namespace Tests\Feature\Theme;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Theme\Services\ThemeService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Web Owner', 'email' => 'web-owner@zennacraft.test', 'phone' => '+8801700000155', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }


    public function test_store_name_comes_from_settings_not_hardcoded(): void
    {
        // With a brand name set, every page title uses it — never a hardcoded brand.
        app(ThemeService::class)->set('brand_name', 'Trendqix Store');
        $this->get('/')->assertOk()->assertSee('Trendqix Store')->assertDontSee('Zenna Craft');
    }

    public function test_website_setup_pages_render(): void
    {
        $owner = $this->owner();
        foreach (['header' => 'Header', 'footer' => 'Footer', 'homepage' => 'Homepage Text', 'theme' => 'Theme Color', 'font' => 'Font Family', 'promotions' => 'Promotions'] as $pg => $title) {
            $this->actingAs($owner, 'staff')->get(route("website.$pg"))->assertOk()->assertSee($title);
        }
        $this->actingAs($owner, 'staff')->get(route('pages.index'))->assertOk()->assertSee('Pages');
    }

    public function test_promotions_countdown_and_popup_save_and_render_on_storefront(): void
    {
        $owner = $this->owner();
        $endsAt = now()->addDays(2)->format('Y-m-d\TH:i');

        $this->actingAs($owner, 'staff')->put(route('website.promotions.save'), [
            'countdown_enabled' => '1',
            'countdown_title' => 'Eid Mega Sale ends in',
            'countdown_ends_at' => $endsAt,
            'countdown_cta' => 'Grab it',
            'countdown_link' => '/products',
            'popup_enabled' => '1',
            'popup_title' => 'Eid Special 🎉',
            'popup_text' => 'Up to 40% off today.',
            'popup_cta' => 'Shop now',
            'popup_link' => '/products',
        ])->assertRedirect(route('website.promotions'));

        $this->assertTrue((bool) app(ThemeService::class)->get('countdown_enabled'));

        $home = $this->get('/')->assertOk();
        // Countdown bar present with a live timer + title.
        $home->assertSee('data-countdown', false)->assertSee('Eid Mega Sale ends in')->assertSee('Grab it');
        // Popup markup present with heading + text.
        $home->assertSee('data-popup', false)->assertSee('Eid Special 🎉')->assertSee('Up to 40% off today.');
    }

    public function test_countdown_bar_hidden_when_end_time_is_past(): void
    {
        app(ThemeService::class)->set('countdown_enabled', true, 'boolean');
        app(ThemeService::class)->set('countdown_title', 'EXPIRED-SALE-MARKER');
        app(ThemeService::class)->set('countdown_ends_at', now()->subDay()->format('Y-m-d\TH:i'));

        // The title only appears inside the rendered bar; its absence proves the
        // bar is hidden (the string "data-countdown" itself lives in the always-
        // present JS, so we can't assert on that).
        $this->get('/')->assertOk()->assertDontSee('EXPIRED-SALE-MARKER');
    }

    public function test_theme_color_saves_and_applies_to_storefront(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->put(route('website.theme.save'), [
            'primary_color' => '#ab12cd', 'primary_hover_color' => '#ffcc00', 'menu_bg_color' => '#001122',
            'discount_price_color' => '#123456', 'default_border_color' => '#eeeeee',
            'footer_bg_color' => '#0a0a0a', 'footer_text_color' => '#dddddd', 'cart_bg_color' => '#334455',
        ])->assertRedirect(route('website.theme'));

        $this->assertSame('#ab12cd', app(ThemeService::class)->get('primary_color'));

        $this->get('/')->assertOk()
            ->assertSee('--leaf:#ab12cd', false)       // primary
            ->assertSee('--honey:#ffcc00', false)      // primary hover / accent
            ->assertSee('--leaf-deep:#001122', false)  // menu background
            ->assertSee('--sale:#123456', false)       // discount price
            ->assertSee('.zc-footer{background:#0a0a0a', false)   // footer override
            ->assertSee('.pcard__add{background:#334455', false); // add-to-cart override
    }

    public function test_font_family_saves_and_applies(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->put(route('website.font.save'), ['font_family' => 'Poppins'])->assertRedirect(route('website.font'));
        $this->assertSame('Poppins', app(ThemeService::class)->get('font_family'));
        $this->get('/')->assertOk()->assertSee("font-family:'Poppins'", false);
    }

    public function test_homepage_text_saves_and_shows_on_the_storefront(): void
    {
        $owner = $this->owner();
        // A product so the Top Selling section (and its editable heading) renders.
        \App\Modules\Product\Models\Product::create(['name' => 'Sample', 'slug' => 'sample-hp', 'sku' => 'HP-1', 'price' => 500, 'status' => 'active', 'stock' => 5]);

        $this->actingAs($owner, 'staff')->put(route('website.homepage.save'), [
            'hero_title' => 'MY CUSTOM HERO LINE',
            'hero_kicker' => 'My kicker',
            'heading_top_selling' => 'Hot right now',
            'trust_1_title' => 'Trusted Seller',
        ])->assertRedirect(route('website.homepage'));

        $this->assertSame('MY CUSTOM HERO LINE', app(ThemeService::class)->get('hero_title'));

        $this->get('/')->assertOk()
            ->assertSee('MY CUSTOM HERO LINE')
            ->assertSee('My kicker')
            ->assertSee('Hot right now')
            ->assertSee('Trusted Seller');
    }

    public function test_header_search_and_announcement_are_editable(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->put(route('website.header.save'), [
            'brand_name' => 'My Shop',
            'search_placeholder' => 'Find anything…',
            'announce_1' => 'FLAT 20% OFF THIS WEEK',
            'announce_2' => '', 'announce_3' => '',
        ])->assertRedirect(route('website.header'));

        $this->get('/')->assertOk()
            ->assertSee('Find anything…')
            ->assertSee('FLAT 20% OFF THIS WEEK');
    }

    public function test_cms_page_crud_and_public_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('pages.store'), ['title' => 'About Us', 'content' => '<p>We sell natural food.</p>', 'active' => '1'])
            ->assertRedirect(route('pages.index'));
        $page = CmsPage::where('title', 'About Us')->firstOrFail();
        $this->assertSame('about-us', $page->slug);

        $this->get('/pages/about-us')->assertOk()->assertSee('We sell natural food.', false);

        // toggle off -> hidden (404)
        $this->actingAs($owner, 'staff')->postJson(route('pages.toggle', $page))->assertOk()->assertJson(['active' => false]);
        $this->get('/pages/about-us')->assertNotFound();

        $this->actingAs($owner, 'staff')->deleteJson(route('pages.destroy', $page))->assertOk();
        $this->assertDatabaseMissing('cms_pages', ['id' => $page->id]);
    }
}
