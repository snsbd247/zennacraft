<?php

namespace Database\Seeders;

use App\Modules\Finance\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $accounts = [
            'cash' => 'Cash',
            'city_bank' => 'City Bank',
            'bkash_personal' => 'Bkash Personal',
            'bkash_merchant' => 'Bkash Merchant',
            'dbbl_bank' => 'DBBL Bank',
            'nagad_personal' => 'Nagad Personal',
            'ibbl' => 'IBBL',
        ];

        foreach (array_values($accounts) as $index => $name) {
            $slug = array_keys($accounts)[$index];

            Account::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort_order' => $index]
            );
        }
    }
}
