<?php

namespace Tests\Feature\Admin;

use App\Modules\AdminAuth\Models\Permission;
use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Panel Owner', 'email' => 'panel-owner@zennacraft.test', 'phone' => '+8801700000230', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_owner_can_add_an_admin(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('admins.store'), [
            'name' => 'New Manager', 'email' => 'new-manager@zennacraft.test',
            'phone' => '01712345678', 'password' => 'secret1234', 'status' => 'active',
        ])->assertRedirect(route('admins.index'));

        $admin = StaffUser::where('email', 'new-manager@zennacraft.test')->firstOrFail();
        $this->assertSame('New Manager', $admin->name);
        $this->assertTrue(Hash::check('secret1234', $admin->password));
    }

    public function test_manage_list_renders_admins(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('admins.index'))
            ->assertOk()->assertSee('Add Admin')->assertSee('Panel Owner')->assertSee('panel-owner@zennacraft.test');
    }

    public function test_toggle_status_and_self_guard(): void
    {
        $owner = $this->owner();
        $other = StaffUser::create(['name' => 'Toggle Me', 'email' => 'toggle@zennacraft.test', 'password' => 'Password123!', 'status' => 'active']);

        $this->actingAs($owner, 'staff')->postJson(route('admins.toggle', $other))
            ->assertOk()->assertJson(['status' => 'inactive']);
        $this->assertSame('inactive', $other->fresh()->status);

        // can't disable your own account
        $this->actingAs($owner, 'staff')->postJson(route('admins.toggle', $owner))->assertStatus(422);
    }

    public function test_reset_password(): void
    {
        $owner = $this->owner();
        $admin = StaffUser::create(['name' => 'Reset Me', 'email' => 'reset@zennacraft.test', 'password' => 'OldPass123!', 'status' => 'active']);

        $this->actingAs($owner, 'staff')->postJson(route('admins.reset-password', $admin), ['password' => 'BrandNew123'])
            ->assertOk();
        $this->assertTrue(Hash::check('BrandNew123', $admin->fresh()->password));
    }

    public function test_assigning_permissions_grants_direct_access(): void
    {
        $owner = $this->owner();
        $admin = StaffUser::create(['name' => 'Perm User', 'email' => 'perm@zennacraft.test', 'password' => 'Password123!', 'status' => 'active']);
        $permId = Permission::where('slug', 'customer.view')->firstOrFail()->id;

        // has nothing to start with
        $this->assertFalse($admin->hasPermission('customer.view'));

        $this->actingAs($owner, 'staff')->postJson(route('admins.permissions.save', $admin), ['permissions' => [$permId]])
            ->assertOk();

        $this->assertDatabaseHas('staff_user_permission', ['staff_user_id' => $admin->id, 'permission_id' => $permId]);
        $this->assertTrue($admin->fresh()->hasPermission('customer.view'));

        // the permissions grid page renders
        $this->actingAs($owner, 'staff')->get(route('admins.permissions', $admin))
            ->assertOk()->assertSee('Assign Permissions')->assertSee('Perm User');
    }

    public function test_staff_without_staff_view_is_blocked(): void
    {
        $this->owner();
        $stranger = StaffUser::create(['name' => 'No Perm', 'email' => 'noperm-admin@zennacraft.test', 'password' => 'Password123!', 'status' => 'active']);

        $this->actingAs($stranger, 'staff')->get(route('admins.index'))->assertForbidden();
    }
}
