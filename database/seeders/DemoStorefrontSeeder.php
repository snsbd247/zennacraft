<?php

namespace Database\Seeders;

use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Storefront\Models\StorefrontSlider;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoStorefrontSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedSliders();
        $this->seedMissingCmsPages();
    }

    protected function seedSliders(): void
    {
        if (StorefrontSlider::query()->where('active', true)->exists()) {
            return;
        }

        $slides = [
            [
                'title' => 'Demo Premium Nokshi Kantha',
                'subtitle' => 'Handmade Bengali Heritage',
                'description' => 'A production-like QA slider for testing the luxury handmade storefront experience.',
                'button_text' => 'Shop Demo Collection',
                'button_url' => '/products',
                'badge_text' => 'Demo QA',
                'accent' => '#1d2147',
            ],
            [
                'title' => 'Demo Gift Ready Crafts',
                'subtitle' => 'Artisan Packages',
                'description' => 'Use this demo slide to verify mobile imagery, CTA styling, and homepage conversion sections.',
                'button_text' => 'Explore Gifts',
                'button_url' => '/products',
                'badge_text' => 'Gift Ready',
                'accent' => '#b88a2f',
            ],
        ];

        foreach ($slides as $index => $slide) {
            $desktop = $this->demoMedia('storefront-slider-'.$index.'-desktop', $slide['title'], $slide['accent'], 1600, 720);
            $mobile = $this->demoMedia('storefront-slider-'.$index.'-mobile', $slide['title'].' mobile', $slide['accent'], 800, 1000);

            StorefrontSlider::updateOrCreate(
                ['title' => $slide['title']],
                [
                    'subtitle' => $slide['subtitle'],
                    'description' => $slide['description'],
                    'button_text' => $slide['button_text'],
                    'button_url' => $slide['button_url'],
                    'desktop_image_id' => $desktop->id,
                    'mobile_image_id' => $mobile->id,
                    'badge_text' => $slide['badge_text'],
                    'active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    protected function seedMissingCmsPages(): void
    {
        $pages = [
            'about-us' => ['About Us', 'Demo QA About Us page for Zenna Craft storefront verification.'],
            'contact-us' => ['Contact Us', 'Demo QA Contact page for storefront and footer verification.'],
            'return-policy' => ['Return Policy', 'Demo QA return policy content.'],
            'refund-policy' => ['Refund Policy', 'Demo QA refund policy content.'],
            'shipping-policy' => ['Shipping Policy', 'Demo QA shipping policy content.'],
            'privacy-policy' => ['Privacy Policy', 'Demo QA privacy policy content.'],
            'terms-and-conditions' => ['Terms & Conditions', 'Demo QA terms content.'],
        ];

        foreach ($pages as $slug => [$title, $content]) {
            CmsPage::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'content' => '<p>'.$content.'</p>',
                    'meta_title' => $title.' | Zenna Craft Demo',
                    'meta_description' => $content,
                    'active' => true,
                ]
            );
        }
    }
}
