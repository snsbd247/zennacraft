<?php

namespace Tests\Feature\Accessibility;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against the exact regression Phase C found: a form field shipped
 * with no id=/for= association between its <label> and its control. That
 * class of bug is invisible by inspection (the page looks fine, screen
 * readers silently can't tell what a field is) and cheap to catch here.
 */
class FormLabelAssociationTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_login_page_renders_with_associated_labels_and_skip_link(): void
    {
        $response = $this->get(route('staff.login'));

        $response->assertOk();
        $response->assertSee('for="email"', false);
        $response->assertSee('id="email"', false);
        $response->assertSee('for="password"', false);
        $response->assertSee('id="password"', false);
        $response->assertSee('Skip to content');
        $response->assertSee('id="main-content"', false);
    }

    public function test_storefront_homepage_has_skip_link_and_main_landmark(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Skip to content');
        $response->assertSee('id="main-content"', false);
    }
}
