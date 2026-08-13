<?php

namespace Tests\Feature\Storefront;

use App\Modules\Theme\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_button_renders_when_number_is_configured(): void
    {
        // BD local number should normalise to an international wa.me target.
        app(ThemeService::class)->set('social_whatsapp', '01814802802');

        $this->get('/')->assertOk()
            ->assertSee('https://wa.me/8801814802802?text=', false) // click-to-chat link w/ prefilled message
            ->assertSee('Chat with us on WhatsApp')                  // floating (desktop) button
            ->assertSee('Chat on WhatsApp');                         // mobile bottom-nav item
    }

    public function test_number_with_country_code_is_used_as_is(): void
    {
        app(ThemeService::class)->set('social_whatsapp', '+880 1814-802802');

        $this->get('/')->assertOk()->assertSee('https://wa.me/8801814802802?text=', false);
    }

    public function test_a_custom_default_message_is_prefilled_when_set(): void
    {
        app(ThemeService::class)->set('social_whatsapp', '01814802802');
        app(ThemeService::class)->set('social_whatsapp_message', 'Hi, I want to order.');

        // rawurlencode turns spaces into %20.
        $this->get('/')->assertOk()->assertSee('?text=Hi%2C%20I%20want%20to%20order.', false);
    }

    public function test_no_whatsapp_button_and_search_stays_when_number_is_blank(): void
    {
        // Nothing configured → no WhatsApp anywhere; the bottom nav keeps Search.
        $this->get('/')->assertOk()
            ->assertDontSee('wa.me', false)              // no click-to-chat link rendered
            ->assertDontSee('Chat on WhatsApp')          // no nav item
            ->assertSee('data-msearch-open', false);     // Search fallback still present
    }
}
