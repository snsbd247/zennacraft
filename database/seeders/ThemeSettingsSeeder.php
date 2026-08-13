<?php

namespace Database\Seeders;

use App\Modules\Theme\Models\ThemeSetting;
use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ThemeSettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $settings = [
            'site_logo' => null,
            'site_favicon' => null,
            'primary_color' => '#0f172a',
            'secondary_color' => '#f5f5f4',
            'hero_title' => 'Crafted pieces for thoughtful spaces.',
            'hero_subtitle' => 'Browse a focused collection of active products, materials, and finishes from the Zenna Craft catalog.',
            'hero_button_text' => 'View Products',
            'hero_button_url' => '/products',
            'hero_image_id' => null,
            'footer_text' => 'Zenna Craft. All rights reserved.',
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
        ];

        foreach ($settings as $key => $value) {
            ThemeSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        foreach ($this->themeSettings() as $key => $config) {
            Setting::updateOrCreate(
                ['setting_group' => 'theme', 'setting_key' => $key],
                [
                    'value' => $config['value'],
                    'data_type' => $config['type'],
                    'is_public' => true,
                ]
            );
        }
    }

    protected function themeSettings(): array
    {
        return [
            'site_logo' => ['value' => '', 'type' => 'string'],
            'site_favicon' => ['value' => '', 'type' => 'string'],
            'brand_name' => ['value' => 'Zenna Craft', 'type' => 'string'],
            'brand_slogan' => ['value' => 'Premium handmade craft for thoughtful homes.', 'type' => 'string'],
            'primary_color' => ['value' => '#1e1b4b', 'type' => 'string'],
            'secondary_color' => ['value' => '#f5f0e6', 'type' => 'string'],
            'hero_title' => ['value' => 'Premium Nokshi Kantha, crafted for modern homes.', 'type' => 'string'],
            'hero_subtitle' => ['value' => 'Discover a clean, curated collection of handmade Bengali craft pieces from the Zenna Craft catalog.', 'type' => 'string'],
            'hero_button_text' => ['value' => 'Shop the Collection', 'type' => 'string'],
            'hero_button_url' => ['value' => '/products', 'type' => 'string'],
            'hero_image_id' => ['value' => '', 'type' => 'string'],
            'homepage_show_hero_slider' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_categories' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_top_selling' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_collections' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_artisan_story' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_craft_process' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_reviews' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_faq' => ['value' => true, 'type' => 'boolean'],
            'homepage_show_newsletter' => ['value' => false, 'type' => 'boolean'],
            'footer_description' => ['value' => 'Premium handmade craft, curated for thoughtful homes and meaningful gifting.', 'type' => 'string'],
            'footer_copyright' => ['value' => 'Zenna Craft. All rights reserved.', 'type' => 'string'],
            'footer_menu' => ['value' => "About Us|/pages/about-us\nContact Us|/pages/contact-us\nReturn Policy|/pages/return-policy\nPrivacy Policy|/pages/privacy-policy\nTerms & Conditions|/pages/terms-and-conditions", 'type' => 'string'],
            'show_search' => ['value' => true, 'type' => 'boolean'],
            'show_wishlist' => ['value' => false, 'type' => 'boolean'],
            'show_tracking' => ['value' => true, 'type' => 'boolean'],
            'show_account' => ['value' => true, 'type' => 'boolean'],
            'show_newsletter' => ['value' => false, 'type' => 'boolean'],
            'social_facebook' => ['value' => '', 'type' => 'string'],
            'social_instagram' => ['value' => '', 'type' => 'string'],
            'social_tiktok' => ['value' => '', 'type' => 'string'],
            'social_youtube' => ['value' => '', 'type' => 'string'],
            'social_whatsapp' => ['value' => '', 'type' => 'string'],
            'social_telegram' => ['value' => '', 'type' => 'string'],
            'social_messenger' => ['value' => '', 'type' => 'string'],
            'social_pinterest' => ['value' => '', 'type' => 'string'],
            'social_linkedin' => ['value' => '', 'type' => 'string'],
            'contact_phone' => ['value' => '', 'type' => 'string'],
            'contact_whatsapp' => ['value' => '', 'type' => 'string'],
            'contact_email' => ['value' => '', 'type' => 'string'],
            'contact_address' => ['value' => '', 'type' => 'string'],
            'contact_business_hours' => ['value' => '', 'type' => 'string'],
        ];
    }
}
