<?php

namespace Database\Seeders;

use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Support\SystemMarketingSegments;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MarketingSegmentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (SystemMarketingSegments::definitions() as $segment) {
            MarketingSegment::updateOrCreate(
                ['slug' => $segment['slug']],
                $segment
            );
        }
    }
}
