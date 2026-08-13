<?php

namespace Tests\Feature\LandingPage;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingPageManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'LP Owner', 'email' => 'lp-owner@zennacraft.test',
            'phone' => '+8801700000111', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_manage_and_create_pages_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('landing.index'))->assertOk()->assertSee('Landing page Table');
        $this->actingAs($owner, 'staff')->get(route('landing.create'))->assertOk()->assertSee('Choose a template style');
    }

    public function test_store_creates_page_with_template_and_hero_image(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('landing.store'), [
            'title' => 'Winter Shawl Promo', 'template' => 'bold', 'status' => 'active',
            'hero_title' => 'Stay warm in style', 'hero_subtitle' => 'New winter collection',
            'content' => '<p>Best shawls in town</p>', 'cta_text' => 'Order Now', 'cta_url' => '/products',
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1200, 600),
        ])->assertRedirect(route('landing.index'));

        $page = LandingPage::where('title', 'Winter Shawl Promo')->firstOrFail();
        $this->assertSame('bold', $page->template);
        $this->assertSame('winter-shawl-promo', $page->slug);
        $this->assertNotNull($page->hero_image_id);
    }

    public function test_toggle_status_does_not_regenerate_slug(): void
    {
        $owner = $this->owner();
        $page = LandingPage::create(['title' => 'Summer Sale', 'slug' => 'buy-now', 'status' => 'active', 'template' => 'classic']);

        $this->actingAs($owner, 'staff')->postJson(route('landing.toggle', $page))
            ->assertOk()->assertJson(['status' => 'inactive']);

        $fresh = $page->fresh();
        $this->assertSame('inactive', $fresh->status);
        $this->assertSame('buy-now', $fresh->slug, 'Custom slug must survive a status toggle.');
    }

    public function test_update_keeps_custom_slug_and_destroy_removes_page(): void
    {
        $owner = $this->owner();
        $page = LandingPage::create(['title' => 'Old', 'slug' => 'special-deal', 'status' => 'active', 'template' => 'classic']);

        $this->actingAs($owner, 'staff')->put(route('landing.update', $page), [
            'title' => 'New Title', 'slug' => 'special-deal', 'status' => 'active', 'template' => 'minimal',
        ])->assertRedirect(route('landing.index'));
        $fresh = $page->fresh();
        $this->assertSame('New Title', $fresh->title);
        $this->assertSame('special-deal', $fresh->slug);
        $this->assertSame('minimal', $fresh->template);

        $this->actingAs($owner, 'staff')->deleteJson(route('landing.destroy', $page))->assertOk();
        $this->assertDatabaseMissing('landing_pages', ['id' => $page->id]);
    }

    public function test_product_search_finds_by_name_and_sku_and_returns_url(): void
    {
        $owner = $this->owner();
        $shawl = Product::create(['name' => 'Winter Shawl', 'slug' => 'winter-shawl', 'sku' => 'WS-100', 'price' => 1200, 'status' => 'active']);
        Product::create(['name' => 'Summer Cap', 'slug' => 'summer-cap', 'sku' => 'SC-9', 'price' => 300, 'status' => 'active']);

        $this->actingAs($owner, 'staff')->getJson(route('landing.products.search', ['q' => 'shawl']))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'WS-100'])
            // Picking a product links the button to that product's checkout (Buy now), not its details page.
            ->assertJsonPath('results.0.url', route('checkout', ['product_id' => $shawl->id, 'quantity' => 1]))
            ->assertJsonMissing(['sku' => 'SC-9']);

        // search by SKU works too
        $this->actingAs($owner, 'staff')->getJson(route('landing.products.search', ['q' => 'WS-100']))
            ->assertOk()->assertJsonFragment(['name' => 'Winter Shawl']);

        // blank query returns nothing
        $this->actingAs($owner, 'staff')->getJson(route('landing.products.search', ['q' => '']))
            ->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_suggested_products_persist_and_render_in_embedded_order_form(): void
    {
        $owner = $this->owner();
        $p1 = Product::create(['name' => 'Kantha Shawl', 'slug' => 'kantha-shawl', 'sku' => 'K-1', 'price' => 1500, 'status' => 'active', 'stock' => 5]);
        $p2 = Product::create(['name' => 'Nakshi Bag', 'slug' => 'nakshi-bag', 'sku' => 'N-1', 'price' => 900, 'status' => 'active', 'stock' => 5]);

        $this->actingAs($owner, 'staff')->post(route('landing.store'), [
            'title' => 'Combo Promo', 'template' => 'classic', 'status' => 'active',
            'suggested_products' => [$p1->id, $p2->id],
        ])->assertRedirect(route('landing.index'));

        $page = LandingPage::where('title', 'Combo Promo')->firstOrFail();
        $this->assertSame([$p1->id, $p2->id], $page->suggested_products);

        // Products now render inside the on-page order form; the CTA scrolls to it (no separate checkout).
        $this->get('/'.$page->slug)->assertOk()
            ->assertSee('zc-order', false)
            ->assertSee('Confirm order')
            ->assertSee('Kantha Shawl')->assertSee('Nakshi Bag')
            ->assertSee('href="#zc-order"', false);
    }

    public function test_landing_without_curated_products_falls_back_to_catalogue_order_form(): void
    {
        // A page with NO suggested_products and NO cta_url should still show the
        // one-page order form, filled from the live catalogue, so the CTA scrolls
        // to it and shoppers can pick any product and order — no separate checkout.
        Product::create(['name' => 'Fallback Kantha', 'slug' => 'fallback-kantha', 'sku' => 'FK-1', 'price' => 1200, 'status' => 'active', 'stock' => 4]);
        LandingPage::create(['title' => 'Bare LP', 'slug' => 'bare-lp', 'status' => 'active', 'template' => 'sales']);

        $this->get('/bare-lp')->assertOk()
            ->assertSee('zc-order', false)
            ->assertSee('Confirm order')
            ->assertSee('Fallback Kantha')
            ->assertSee('href="#zc-order"', false)   // hero/close CTAs scroll to the form
            ->assertDontSee('checkout?product_id', false);  // not the separate checkout page
    }

    public function test_rich_sections_persist_and_render_on_storefront(): void
    {
        $owner = $this->owner();
        $product = Product::create(['name' => 'Hoodie X', 'slug' => 'hoodie-x', 'sku' => 'HX-1', 'price' => 1050, 'status' => 'active', 'stock' => 5]);

        $this->actingAs($owner, 'staff')->post(route('landing.store'), [
            'title' => 'Rich LP', 'template' => 'classic', 'status' => 'active',
            'suggested_products' => [$product->id],
            'video_url' => 'https://www.youtube.com/watch?v=abc12345',
            'features' => "100% cotton fabric\nCash on delivery",
            'contact_phone' => '01814802802', 'whatsapp_number' => '8801814802802', 'show_reviews' => '1',
        ])->assertRedirect(route('landing.index'));

        $page = LandingPage::where('title', 'Rich LP')->firstOrFail();
        $this->assertSame('https://www.youtube.com/watch?v=abc12345', $page->video_url);
        $this->assertTrue($page->show_reviews);

        $this->get('/'.$page->slug)->assertOk()
            ->assertSee('youtube.com/embed/abc12345', false)   // video embed
            ->assertSee('100% cotton fabric')                   // feature checklist
            ->assertSee('tel:01814802802', false)               // call button
            ->assertSee('wa.me/8801814802802', false);          // WhatsApp button
    }

    public function test_product_link_landing_renders_the_one_page_order_form(): void
    {
        // Older pages stored the product's details-page URL in cta_url. Those now
        // become one-page too: the referenced product is featured inside the
        // embedded order form and the CTA scrolls to it (no separate checkout).
        Product::create(['name' => 'Pure Ghee', 'slug' => 'pure-ghee', 'sku' => 'G-1', 'price' => 1650, 'status' => 'active', 'stock' => 10]);
        LandingPage::create([
            'title' => 'Ghee LP', 'slug' => 'ghee-lp', 'status' => 'active', 'template' => 'classic',
            'cta_text' => 'Buy now', 'cta_url' => route('storefront.product.show', 'pure-ghee'),
        ]);

        $this->get('/ghee-lp')->assertOk()
            ->assertSee('zc-order', false)
            ->assertSee('Confirm order')
            ->assertSee('Pure Ghee')
            ->assertSee('href="#zc-order"', false)          // CTA scrolls to the form
            ->assertDontSee('checkout?product_id', false);  // not a separate checkout redirect
    }

    public function test_checkout_link_cta_features_the_product_in_the_one_page_form(): void
    {
        // Real-world case: cta_url is a checkout link carrying ?product_id=N.
        // That product should be featured inside the one-page order form.
        $product = Product::create(['name' => 'Nakshi Kantha', 'slug' => 'nakshi-kantha', 'sku' => 'NK-1', 'price' => 2400, 'status' => 'active', 'stock' => 6]);
        LandingPage::create([
            'title' => 'Kantha LP', 'slug' => 'kantha-lp', 'status' => 'active', 'template' => 'sales',
            'cta_text' => 'Order now', 'cta_url' => route('checkout', ['product_id' => $product->id, 'quantity' => 1]),
        ]);

        $this->get('/kantha-lp')->assertOk()
            ->assertSee('zc-order', false)
            ->assertSee('Confirm order')
            ->assertSee('Nakshi Kantha')
            ->assertSee('href="#zc-order"', false);
    }

    public function test_external_cta_link_landing_keeps_its_link_and_no_form(): void
    {
        // A genuinely external CTA link (not one of our product pages) is left as
        // a plain link — no on-page form is forced onto it.
        Product::create(['name' => 'Some Product', 'slug' => 'some-product', 'sku' => 'SP-1', 'price' => 500, 'status' => 'active', 'stock' => 3]);
        LandingPage::create([
            'title' => 'Ext LP', 'slug' => 'ext-lp', 'status' => 'active', 'template' => 'classic',
            'cta_text' => 'Learn more', 'cta_url' => 'https://example.com/promo',
        ]);

        $this->get('/ext-lp')->assertOk()
            ->assertSee('https://example.com/promo', false)
            ->assertDontSee('id="zc-order"', false);
    }

    public function test_storefront_renders_selected_template_and_hides_inactive(): void
    {
        $this->owner();
        $bold = LandingPage::create(['title' => 'Bold Page', 'slug' => 'bold-page', 'status' => 'active', 'template' => 'bold', 'hero_title' => 'Big News']);
        $sales = LandingPage::create(['title' => 'Sales Page', 'slug' => 'sales-page', 'status' => 'active', 'template' => 'sales', 'hero_title' => 'Deal']);
        $off = LandingPage::create(['title' => 'Hidden', 'slug' => 'hidden-page', 'status' => 'inactive', 'template' => 'classic']);

        $this->get('/bold-page')->assertOk()->assertSee('zc-lpb', false)->assertSee('Big News');
        $this->get('/sales-page')->assertOk()->assertSee('zc-lps__badge', false);
        $this->get('/hidden-page')->assertNotFound();
    }
}
