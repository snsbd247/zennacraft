<?php

namespace Tests\Feature\Account;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The staff self-service Account page (profile + change password),
 * reachable from the topbar profile menu, added 2026-07-25.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Acct Owner', 'email' => 'acct@zennacraft.test',
            'phone' => '+8801700000055', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_account_page_shows_profile(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff, 'staff')->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('Acct Owner');
        $response->assertSee('acct@zennacraft.test');
        $response->assertSee('Change Password');
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff, 'staff')->post(route('account.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewStrongPass123',
            'password_confirmation' => 'NewStrongPass123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Password123!', $staff->fresh()->password));
    }

    public function test_staff_can_change_their_password(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff, 'staff')->post(route('account.password'), [
            'current_password' => 'Password123!',
            'password' => 'NewStrongPass123',
            'password_confirmation' => 'NewStrongPass123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('NewStrongPass123', $staff->fresh()->password));
    }

    public function test_account_requires_authentication(): void
    {
        $this->get(route('account.show'))->assertRedirect('/'.config('admin.path').'/login');
    }
}
