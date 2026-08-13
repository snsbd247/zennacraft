<?php

namespace Database\Seeders;

use App\Modules\Courier\Models\CourierProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourierProviderSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (['steadfast', 'pathao', 'redx', 'paperfly', 'manual'] as $slug) {
            CourierProvider::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline($slug),
                    'status' => 'active',
                    'api_enabled' => false,
                ]
            );
        }
    }
}
