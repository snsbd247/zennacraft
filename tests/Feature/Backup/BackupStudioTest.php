<?php

namespace Tests\Feature\Backup;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Backup\Models\BackupRun;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackupStudioTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'backup-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_backups_page_loads_for_an_authorised_staff_member(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Run a backup now')
            ->assertSee('Automatic daily backup');
    }

    public function test_settings_can_be_saved_and_the_token_is_stored_encrypted(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->put(route('backups.settings.update'), [
            'enabled' => '1',
            'schedule_time' => '04:30',
            'local_retention_days' => 5,
            'dropbox_retention_days' => 20,
            'dropbox_token' => 'test-secret-token',
        ])->assertRedirect();

        $service = app(\App\Modules\Backup\Services\BackupService::class);
        $this->assertTrue($service->isScheduleEnabled());
        $this->assertSame('04:30', $service->scheduleTime());
        $this->assertSame(5, $service->localRetentionDays());
        $this->assertSame(20, $service->dropboxRetentionDays());
        $this->assertSame('test-secret-token', $service->dropboxToken());

        // Never stored in plaintext in the DB.
        $raw = \App\Modules\Settings\Models\Setting::where('setting_group', 'backup')->where('setting_key', 'dropbox_token')->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('test-secret-token', (string) $raw->value);
    }

    public function test_run_now_creates_a_backup_and_uploads_to_dropbox_when_configured(): void
    {
        $owner = $this->owner();
        app(\App\Modules\Backup\Services\BackupService::class)->updateSettings(['dropbox_token' => 'fake-token']);

        Http::fake([
            'https://content.dropboxapi.com/*' => Http::response(['name' => 'ok'], 200),
            'https://api.dropboxapi.com/*' => Http::response(['entries' => []], 200),
        ]);

        // 'files' only — the SQLite :memory: connection this suite runs
        // against can't be file-copied for a database dump (a real, correct
        // guard in BackupService, not a bug); production always runs MySQL.
        $this->actingAs($owner, 'staff')->postJson(route('backups.run'), ['scopes' => ['files']])->assertOk()->assertJson(['success' => true]);

        $backup = BackupRun::latest('id')->first();
        $this->assertNotNull($backup);
        $this->assertSame('completed', $backup->status);
        $this->assertSame('uploaded', $backup->offsite_status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'content.dropboxapi.com/2/files/upload'));
    }

    public function test_run_now_without_dropbox_configured_still_completes_locally(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->postJson(route('backups.run'), ['scopes' => ['files']])
            ->assertOk()->assertJson(['success' => true]);

        $backup = BackupRun::latest('id')->first();
        $this->assertSame('completed', $backup->status);
        $this->assertNull($backup->offsite_status);
    }
}
