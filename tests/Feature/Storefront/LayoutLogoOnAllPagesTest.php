<?php

namespace Tests\Feature\Storefront;

use App\Modules\Theme\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutLogoOnAllPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_branding_is_shared_to_pages_not_rendered_by_storefront_controller(): void
    {
        // brand_name lives in theme settings — only StorefrontController used to
        // pass those, so the tracking page (TrackingController) would miss it.
        app(ThemeService::class)->set('brand_name', 'Trendqjx Store');

        // /track is rendered by TrackingController, not StorefrontController.
        $this->get('/track')->assertOk()->assertSee('Trendqjx Store');
    }
}
