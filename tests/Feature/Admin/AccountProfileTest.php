<?php

namespace Tests\Feature\Admin;

use App\Modules\AdminAuth\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): StaffUser
    {
        return StaffUser::create(['name' => 'Old Name', 'email' => 'me@zennacraft.test', 'phone' => '+8801700000300', 'password' => 'Password123!', 'status' => 'active']);
    }

    public function test_staff_can_update_name_phone_and_upload_a_profile_picture(): void
    {
        Storage::fake('public');
        $staff = $this->staff();

        $this->actingAs($staff, 'staff')->post(route('account.profile'), [
            'name' => 'New Name',
            'phone' => '01799990000',
            'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame('New Name', $staff->name);
        $this->assertNotEmpty($staff->avatar);
    }

    public function test_profile_page_shows_the_upload_field(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff, 'staff')->get(route('account.show'))
            ->assertOk()->assertSee('Profile picture')->assertSee('Save profile');
    }
}
