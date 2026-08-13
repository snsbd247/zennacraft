<?php

namespace Tests\Feature\Storefront;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Settings\Services\SettingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_protection_script_by_default(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee("addEventListener('contextmenu'", false)
            ->assertDontSee("if (e.key === 'F12')", false);
    }

    public function test_protection_injected_only_for_enabled_toggles(): void
    {
        $s = app(SettingService::class);
        $s->set('general', 'public_anti_copy_enabled', true, 'boolean');
        $s->set('general', 'public_disable_right_click', true, 'boolean');
        $s->set('general', 'public_disable_devtool_shortcuts', true, 'boolean');
        // copy-shortcuts left OFF — must not appear.

        $this->get('/')->assertOk()
            ->assertSee("addEventListener('contextmenu'", false)  // right-click blocked
            ->assertSee("if (e.key === 'F12')", false);            // devtools shortcuts blocked
    }

    public function test_content_protection_config_page_renders(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $owner = StaffUser::create(['name' => 'Owner', 'email' => 'cp-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $owner->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        $this->actingAs($owner, 'staff')->get(route('config.protection'))
            ->assertOk()->assertSee('Content Protection')->assertSee('Disable right-click menu');
    }
}
