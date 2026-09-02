<?php

namespace Tests\Feature\Storefront;

use App\Modules\Theme\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImoButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_imo_button_renders_when_number_is_configured(): void
    {
        app(ThemeService::class)->set('social_imo', '01814802802');

        $this->get('/')->assertOk()
            ->assertSee('imo://chat?phone=8801814802802', false)
            ->assertSee('Chat with us on IMO')
            ->assertSee('Chat on IMO');
    }

    public function test_no_imo_button_when_number_is_blank(): void
    {
        $this->get('/')->assertOk()->assertDontSee('imo://chat', false)->assertDontSee('Chat on IMO');
    }

    public function test_whatsapp_and_imo_coexist_when_both_are_configured(): void
    {
        app(ThemeService::class)->set('social_whatsapp', '01814802802');
        app(ThemeService::class)->set('social_imo', '01911223344');

        $html = $this->get('/')->assertOk()
            ->assertSee('https://wa.me/8801814802802?text=', false)
            ->assertSee('imo://chat?phone=8801911223344', false)
            ->getContent();

        // Regression guard: a stray `class="..." @class([...])` combo on the
        // IMO anchor once produced two class="" attributes on one element —
        // browsers silently drop the second, so zc-imofab--stacked (which
        // pushes IMO above WhatsApp instead of sitting on top of it) never
        // actually applied even though the string was present in the HTML.
        // Assert it lands in ONE merged class attribute instead.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*class="[^"]*\bzc-imofab\b[^"]*\bzc-imofab--stacked\b[^"]*"[^>]*>/',
            $html
        );
        preg_match('/<a[^>]*\bzc-imofab\b[^>]*>/', $html, $tag);
        $this->assertNotEmpty($tag, 'IMO button anchor not found.');
        $this->assertSame(1, substr_count($tag[0], 'class="'), 'IMO button anchor has more than one class="" attribute.');
    }
}
