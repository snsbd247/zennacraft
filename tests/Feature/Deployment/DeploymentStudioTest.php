<?php

namespace Tests\Feature\Deployment;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Deployment\Jobs\RunUpdateJob;
use App\Modules\Deployment\Models\DeploymentRun;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploymentStudioTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'deploy-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function manager(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Manager', 'email' => 'deploy-manager@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'manager')->firstOrFail());

        return $staff;
    }

    public function test_deployment_page_loads_for_the_owner(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->get(route('deployment.index'))
            ->assertOk()
            ->assertSee('Production readiness')
            ->assertSee('Deployment history');
    }

    public function test_manager_can_view_but_not_run_deployments(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'staff')->get(route('deployment.index'))->assertOk();
        $this->actingAs($manager, 'staff')->post(route('deployment.run'))->assertForbidden();
    }

    public function test_run_now_creates_a_pending_run_and_dispatches_the_update_job(): void
    {
        Queue::fake();
        $owner = $this->owner();

        $response = $this->actingAs($owner, 'staff')->postJson(route('deployment.run'))
            ->assertOk()->assertJson(['success' => true]);

        $runId = $response->json('run_id');
        $this->assertNotNull($runId);
        $this->assertSame('pending', DeploymentRun::findOrFail($runId)->status);

        Queue::assertPushed(RunUpdateJob::class, function ($job) use ($runId) {
            return $job->deploymentRun->id === $runId;
        });
    }

    public function test_status_endpoint_reports_progress_for_the_owner(): void
    {
        $owner = $this->owner();
        $run = DeploymentRun::create(['status' => 'running', 'progress' => 45, 'from_commit' => 'abc123']);

        $this->actingAs($owner, 'staff')->getJson(route('deployment.status', $run))
            ->assertOk()
            ->assertJson(['id' => $run->id, 'status' => 'running', 'progress' => 45]);
    }

    public function test_check_updates_reports_not_a_git_repository_outside_a_real_repo(): void
    {
        $owner = $this->owner();

        // This test environment isn't a git checkout, so the service must
        // degrade gracefully rather than throwing.
        $response = $this->actingAs($owner, 'staff')->getJson(route('deployment.check'))->assertOk();
        $response->assertJson(['checked' => false]);
    }

    public function test_clear_history_removes_finished_runs_but_keeps_in_flight_ones(): void
    {
        $owner = $this->owner();
        $completed = DeploymentRun::create(['status' => 'completed']);
        $failed = DeploymentRun::create(['status' => 'failed']);
        $running = DeploymentRun::create(['status' => 'running']);

        $this->actingAs($owner, 'staff')->delete(route('deployment.history.clear'))->assertRedirect();

        $this->assertModelMissing($completed);
        $this->assertModelMissing($failed);
        $this->assertModelExists($running);
    }

    public function test_manager_cannot_clear_history(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'staff')->delete(route('deployment.history.clear'))->assertForbidden();
    }
}
