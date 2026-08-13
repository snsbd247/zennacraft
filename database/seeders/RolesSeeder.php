<?php

namespace Database\Seeders;

use App\Modules\AdminAuth\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'slug' => 'owner', 'description' => 'Full system owner'],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Administrative manager'],
            ['name' => 'Staff', 'slug' => 'staff', 'description' => 'Standard staff user'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
