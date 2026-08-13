<?php

namespace Database\Seeders;

use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Marketing\Models\AdSpend;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoFinanceSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $this->expenses();
        $this->adSpend();
    }

    protected function expenses(): void
    {
        $categories = collect([
            ['Courier', 'courier'],
            ['Packaging', 'packaging'],
            ['Marketing', 'marketing'],
            ['Staff', 'staff'],
            ['Office', 'office'],
            ['Miscellaneous', 'miscellaneous'],
        ])->mapWithKeys(fn (array $category): array => [
            $category[1] => ExpenseCategory::query()->firstOrCreate(
                ['slug' => $category[1]],
                [
                    'name' => $category[0],
                    'status' => 'active',
                    'notes' => 'DEMO QA category available for finance command center testing.',
                ]
            ),
        ]);

        $rows = [
            ['courier', 1350, 'Courier settlement estimate'],
            ['packaging', 820, 'Gift box and wrap supplies'],
            ['marketing', 2200, 'Creative testing cost'],
            ['staff', 3500, 'Part-time packing support'],
            ['office', 1600, 'Studio utilities'],
            ['miscellaneous', 740, 'Demo miscellaneous operation'],
            ['packaging', 980, 'Premium ribbon inventory'],
            ['courier', 1120, 'Returned parcel handling'],
        ];

        foreach ($rows as $index => [$categorySlug, $amount, $description]) {
            Expense::updateOrCreate(
                ['receipt_number' => 'DEMO-EXP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'expense_category_id' => $categories[$categorySlug]->id,
                    'expense_date' => now()->subDays(12 - $index)->toDateString(),
                    'amount' => $amount,
                    'description' => 'DEMO QA '.$description,
                    'staff_user_id' => $this->demoStaffId(),
                ]
            );
        }
    }

    protected function adSpend(): void
    {
        $platforms = ['facebook', 'instagram', 'google', 'whatsapp', 'direct'];
        $amounts = [1800, 2200, 1650, 900, 500];

        foreach (range(0, 9) as $index) {
            $date = now()->subDays(9 - $index)->toDateString();
            $existing = AdSpend::query()->whereDate('spend_date', $date)->first();

            if ($existing && ! str_starts_with((string) $existing->notes, 'DEMO QA')) {
                continue;
            }

            $attributes = [
                'platform' => $platforms[$index % count($platforms)],
                'amount' => $amounts[$index % count($amounts)] + ($index * 75),
                'notes' => 'DEMO QA manual ad spend for marketing ROI and finance dashboards.',
                'staff_user_id' => $this->demoStaffId(),
            ];

            if ($existing) {
                $existing->forceFill($attributes)->save();

                continue;
            }

            AdSpend::create(['spend_date' => $date] + $attributes);
        }
    }
}
