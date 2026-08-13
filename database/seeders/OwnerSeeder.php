<?php

namespace Database\Seeders;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OwnerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = (string) (env('OWNER_EMAIL') ?: 'owner@zennacraft.local');
        $password = env('OWNER_PASSWORD');

        if (blank($password)) {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException(
                    'OWNER_PASSWORD must be set in the environment before seeding outside local/testing. Refusing to seed a default owner credential.'
                );
            }

            $password = Str::password(20);

            $this->command?->warn("No OWNER_PASSWORD set — generated a random owner password for {$email}:");
            $this->command?->warn($password);
            $this->command?->warn('Store this password now. It will not be shown again.');
        }

        $owner = StaffUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Owner',
                'phone' => '+8801000000000',
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );

        $role = Role::where('slug', 'owner')->first();

        if ($role) {
            $owner->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
