<?php

namespace Database\Seeders;

use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GeneralSettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'site_name'],
            ['value' => 'Zenna Craft', 'data_type' => 'string', 'is_public' => true]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'site_email'],
            ['value' => 'hello@example.com', 'data_type' => 'string', 'is_public' => true]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'currency'],
            ['value' => 'BDT', 'data_type' => 'string', 'is_public' => true]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'timezone'],
            ['value' => 'Asia/Dhaka', 'data_type' => 'string', 'is_public' => true]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'facebook_pixel_enabled'],
            ['value' => false, 'data_type' => 'boolean', 'is_public' => true]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'facebook_pixel_id'],
            ['value' => '', 'data_type' => 'string', 'is_public' => true]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'facebook_capi_enabled'],
            ['value' => false, 'data_type' => 'boolean', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'facebook_capi_access_token'],
            ['value' => '', 'data_type' => 'string', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'facebook_capi_test_event_code'],
            ['value' => '', 'data_type' => 'string', 'is_public' => false]
        );

        foreach ([
            'public_anti_copy_enabled',
            'public_disable_right_click',
            'public_disable_text_selection',
            'public_disable_copy_shortcuts',
            'public_disable_devtool_shortcuts',
        ] as $key) {
            Setting::updateOrCreate(
                ['setting_group' => 'general', 'setting_key' => $key],
                ['value' => false, 'data_type' => 'boolean', 'is_public' => true]
            );
        }

        foreach ([
            'block_blacklisted_checkout' => true,
            'auto_reject_critical_fraud' => false,
            'duplicate_order_block_enabled' => true,
            'phone_checkout_cooldown_enabled' => true,
            'coupon_abuse_lock_enabled' => true,
        ] as $key => $value) {
            Setting::updateOrCreate(
                ['setting_group' => 'general', 'setting_key' => $key],
                ['value' => $value, 'data_type' => 'boolean', 'is_public' => false]
            );
        }

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'watermark_enabled'],
            ['value' => false, 'data_type' => 'boolean', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'watermark_text'],
            ['value' => 'Zenna Craft', 'data_type' => 'string', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'watermark_position'],
            ['value' => 'bottom_right', 'data_type' => 'string', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'watermark_opacity'],
            ['value' => 35, 'data_type' => 'integer', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'watermark_apply_to'],
            ['value' => 'product_images', 'data_type' => 'string', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'behavior_event_retention_days'],
            ['value' => 365, 'data_type' => 'integer', 'is_public' => false]
        );

        foreach ([
            'email_enabled' => false,
            'sms_enabled' => true,
            'whatsapp_enabled' => false,
            'internal_enabled' => true,
        ] as $key => $value) {
            Setting::updateOrCreate(
                ['setting_group' => 'communication', 'setting_key' => $key],
                ['value' => $value, 'data_type' => 'boolean', 'is_public' => false]
            );
        }
    }
}
