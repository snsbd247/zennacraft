<?php

namespace Tests\Feature\License;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\License\Services\LicenseService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseClientTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'lic-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function service(): LicenseService
    {
        return app(LicenseService::class);
    }

    private function configurePanel(): void
    {
        config(['license.server' => 'https://panel.test', 'license.product' => 'zenna-craft', 'license.version' => '1.0.0']);
    }

    public function test_page_renders_in_developer_mode_when_no_server_configured(): void
    {
        config(['license.server' => '']);

        $this->actingAs($this->owner(), 'staff')->get(route('license.index'))
            ->assertOk()
            ->assertSee('License &amp; Updates', false)
            ->assertSee('developer mode')
            ->assertSee('LICENSE_SERVER_URL');

        // Nothing was contacted, and the effective status is a safe "unlicensed".
        $this->assertSame('unlicensed', $this->service()->effectiveStatus());
    }

    public function test_activate_stores_the_key_and_active_state(): void
    {
        $this->configurePanel();
        Http::fake(['*/api/v1/activate' => Http::response([
            'status' => 'active',
            'expires_at' => now()->addYear()->toDateString(),
            'message' => 'Activated',
        ], 200)]);

        $this->actingAs($this->owner(), 'staff')
            ->post(route('license.activate'), ['key' => 'ZENNA-TEST-KEY-0001'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $svc = $this->service();
        $this->assertSame('ZENNA-TEST-KEY-0001', $svc->key());
        $this->assertSame('active', $svc->effectiveStatus());
    }

    public function test_activate_rejects_an_invalid_key_without_storing_it(): void
    {
        $this->configurePanel();
        Http::fake(['*/api/v1/activate' => Http::response(['status' => 'invalid', 'message' => 'Unknown key'], 200)]);

        $this->actingAs($this->owner(), 'staff')
            ->post(route('license.activate'), ['key' => 'BAD-KEY'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->service()->key());
    }

    public function test_check_update_reports_an_available_newer_version(): void
    {
        $this->configurePanel();
        Http::fake(['*/api/v1/version*' => Http::response([
            'latest_version' => '1.2.0',
            'changelog' => 'New POS receipt layout',
            'mandatory' => false,
        ], 200)]);

        $res = $this->actingAs($this->owner(), 'staff')->getJson(route('license.check-update'));

        $res->assertOk()->assertJson([
            'ok' => true,
            'current' => '1.0.0',
            'latest' => '1.2.0',
            'has_update' => true,
        ]);
    }

    public function test_expired_license_reports_expired_but_never_hard_stops(): void
    {
        $this->configurePanel();
        // Simulate a previously-activated key that has now lapsed.
        Http::fake(['*/api/v1/activate' => Http::response(['status' => 'active', 'expires_at' => now()->subDay()->toDateString()], 200)]);
        $this->service()->activate('LAPSED-KEY');

        $this->assertSame('expired', $this->service()->effectiveStatus());

        // The Studio still loads (no fatal gate); the storefront is unaffected too.
        $this->actingAs($this->owner(), 'staff')->get(route('license.index'))->assertOk();
        $this->get(route('storefront.home'))->assertOk();
    }

    public function test_unreachable_panel_keeps_the_last_good_status(): void
    {
        $this->configurePanel();
        Http::fake(['*/api/v1/activate' => Http::response(['status' => 'active', 'expires_at' => now()->addYear()->toDateString()], 200)]);
        $this->service()->activate('GOOD-KEY');
        $this->assertSame('active', $this->service()->effectiveStatus());

        // Panel goes down: a validate call throws. The client must NOT downgrade.
        Http::fake(['*/api/v1/validate' => fn () => throw new ConnectionException('down')]);
        $result = $this->service()->refresh();

        $this->assertFalse($result['ok']);
        $this->assertSame('active', $this->service()->effectiveStatus());
    }
}
