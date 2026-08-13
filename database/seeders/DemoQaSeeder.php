<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoQaSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $this->ensureDemoSeedingAllowed();

        $this->call([
            DemoCatalogSeeder::class,
            DemoStorefrontSeeder::class,
            DemoCustomerSeeder::class,
            DemoOrderSeeder::class,
            DemoVerificationSeeder::class,
            DemoCourierSeeder::class,
            DemoRecoverySeeder::class,
            DemoReviewSeeder::class,
            DemoBehaviorEventSeeder::class,
            DemoMarketingSeeder::class,
            DemoFinanceSeeder::class,
            DemoAutomationSeeder::class,
        ]);
    }
}
