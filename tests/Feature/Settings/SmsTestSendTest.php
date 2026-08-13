<?php

namespace Tests\Feature\Settings;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Settings\Services\SettingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsTestSendTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'sms-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_test_sms_sends_via_log_driver_and_returns_result_region(): void
    {
        app(SettingService::class)->set('sms', 'provider', 'log');

        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('config.sms.test'), ['phone' => '01722528677']);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertArrayHasKey('sms-test-result', $res->json('regions'));
        $this->assertStringContainsString('sent successfully', $res->json('regions.sms-test-result'));
    }

    public function test_test_sms_requires_a_phone(): void
    {
        $res = $this->actingAs($this->owner(), 'staff')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('config.sms.test'), []);

        $res->assertStatus(422);
    }
}
