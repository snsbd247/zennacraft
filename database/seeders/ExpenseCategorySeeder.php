<?php

namespace Database\Seeders;

use App\Modules\Expense\Models\ExpenseCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            'packaging' => 'Packaging',
            'salary' => 'Salary',
            'office_rent' => 'Office Rent',
            'internet' => 'Internet',
            'electricity' => 'Electricity',
            'software' => 'Software',
            'domain_hosting' => 'Domain Hosting',
            'transport' => 'Transport',
            'miscellaneous' => 'Miscellaneous',
        ];

        foreach ($categories as $slug => $name) {
            ExpenseCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'status' => 'active',
                    'notes' => null,
                ]
            );
        }
    }
}
