<?php

namespace Database\Seeders;

use App\Modules\Storefront\Models\CmsPage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->pages() as $slug => $title) {
            CmsPage::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'content' => '',
                    'meta_title' => $title,
                    'meta_description' => null,
                    'active' => true,
                ]
            );
        }
    }

    protected function pages(): array
    {
        return [
            'about-us' => 'About Us',
            'contact-us' => 'Contact Us',
            'return-policy' => 'Return Policy',
            'refund-policy' => 'Refund Policy',
            'shipping-policy' => 'Shipping Policy',
            'privacy-policy' => 'Privacy Policy',
            'terms-and-conditions' => 'Terms & Conditions',
        ];
    }
}
