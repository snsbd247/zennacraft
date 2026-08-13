<?php

namespace App\Modules\Settings\Services;

use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Settings\Repositories\SettingRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SettingService
{
    public function __construct(
        protected SettingRepository $repository,
        protected CacheService $cacheService,
    ) {}

    /**
     * For third-party write credentials (currently: the Facebook CAPI
     * access token) that must not sit in a raw DB backup as plaintext.
     * Not the default for settings in general — most settings (site
     * name, currency, feature toggles) have no reason to be encrypted,
     * and encrypting everything would only make them harder to inspect
     * for no security benefit.
     */
    public function getEncrypted(string $group, string $key, mixed $default = null): mixed
    {
        $value = $this->get($group, $key);

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // A value saved before encryption was added is still
            // plaintext. Return it as-is so access isn't lost; it gets
            // encrypted the next time it's saved via setEncrypted().
            return $value;
        } catch (Throwable) {
            return $default;
        }
    }

    public function setEncrypted(string $group, string $key, mixed $value, bool $isPublic = false): mixed
    {
        $stored = ($value === null || $value === '') ? $value : Crypt::encryptString((string) $value);

        return $this->set($group, $key, $stored, 'encrypted', $isPublic);
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->cacheService->remember($this->cacheKey($group, $key), function () use ($group, $key, $default) {
            return $this->repository->get($group, $key, $default);
        });
    }

    public function set(string $group, string $key, mixed $value, string $dataType = 'string', bool $isPublic = false): mixed
    {
        $setting = $this->repository->set($group, $key, $value, $dataType, $isPublic);

        $this->clearCache($group, $key);

        return $setting;
    }

    public function has(string $group, string $key): bool
    {
        return $this->cacheService->remember($this->cacheKey($group, $key), function () use ($group, $key) {
            return $this->repository->has($group, $key);
        });
    }

    public function remove(string $group, string $key): bool
    {
        $removed = $this->repository->remove($group, $key);

        if ($removed) {
            $this->clearCache($group, $key);
        }

        return $removed;
    }

    public function getGroup(string $group): array
    {
        return $this->cacheService->remember($this->groupCacheKey($group), function () use ($group) {
            return $this->repository->getGroup($group);
        });
    }

    public function defaults(): array
    {
        return [
            'general.site_name' => 'Zenna Craft',
            'general.site_email' => 'hello@example.com',
            'general.currency' => 'BDT',
            'general.timezone' => 'Asia/Dhaka',
            'general.facebook_pixel_enabled' => false,
            'general.facebook_pixel_id' => '',
            'general.facebook_capi_enabled' => false,
            'general.facebook_capi_access_token' => '',
            'general.facebook_capi_test_event_code' => '',
            'general.public_anti_copy_enabled' => false,
            'general.public_disable_right_click' => false,
            'general.public_disable_text_selection' => false,
            'general.public_disable_copy_shortcuts' => false,
            'general.public_disable_devtool_shortcuts' => false,
            'general.block_blacklisted_checkout' => true,
            'general.auto_reject_critical_fraud' => false,
            'general.duplicate_order_block_enabled' => true,
            'general.phone_checkout_cooldown_enabled' => true,
            'general.coupon_abuse_lock_enabled' => true,
            'general.watermark_enabled' => false,
            'general.watermark_text' => 'Zenna Craft',
            'general.watermark_position' => 'bottom_right',
            'general.watermark_opacity' => 35,
            'general.watermark_apply_to' => 'product_images',
            'general.behavior_event_retention_days' => 365,
            'theme.site_logo' => '',
            'theme.site_favicon' => '',
            'theme.brand_name' => 'Zenna Craft',
            'theme.brand_slogan' => 'Premium handmade craft for thoughtful homes.',
            'theme.primary_color' => '#1e1b4b',
            'theme.secondary_color' => '#f5f0e6',
            'theme.hero_title' => 'Premium Nokshi Kantha, crafted for modern homes.',
            'theme.hero_subtitle' => 'Discover a clean, curated collection of handmade Bengali craft pieces from the Zenna Craft catalog.',
            'theme.hero_button_text' => 'Shop the Collection',
            'theme.hero_button_url' => '/products',
            'theme.hero_image_id' => '',
            'theme.homepage_show_hero_slider' => true,
            'theme.homepage_show_categories' => true,
            'theme.homepage_show_top_selling' => true,
            'theme.homepage_show_collections' => true,
            'theme.homepage_show_artisan_story' => true,
            'theme.homepage_show_craft_process' => true,
            'theme.homepage_show_reviews' => true,
            'theme.homepage_show_faq' => true,
            'theme.homepage_show_newsletter' => false,
            'theme.footer_description' => 'Premium handmade craft, curated for thoughtful homes and meaningful gifting.',
            'theme.footer_copyright' => 'Zenna Craft. All rights reserved.',
            'theme.footer_menu' => "About Us|/pages/about-us\nContact Us|/pages/contact-us\nReturn Policy|/pages/return-policy\nPrivacy Policy|/pages/privacy-policy\nTerms & Conditions|/pages/terms-and-conditions",
            'theme.show_search' => true,
            'theme.show_wishlist' => false,
            'theme.show_tracking' => true,
            'theme.show_account' => true,
            'theme.show_newsletter' => false,
            'theme.social_facebook' => '',
            'theme.social_instagram' => '',
            'theme.social_tiktok' => '',
            'theme.social_youtube' => '',
            'theme.social_whatsapp' => '',
            'theme.social_telegram' => '',
            'theme.social_messenger' => '',
            'theme.social_pinterest' => '',
            'theme.social_linkedin' => '',
            'theme.contact_phone' => '',
            'theme.contact_whatsapp' => '',
            'theme.contact_email' => '',
            'theme.contact_address' => '',
            'theme.contact_business_hours' => '',
            'communication.email_enabled' => false,
            'communication.sms_enabled' => true,
            'communication.whatsapp_enabled' => false,
            'communication.internal_enabled' => true,
        ];
    }

    protected function cacheKey(string $group, string $key): string
    {
        return CacheKeyRegistry::settingValue($group, $key);
    }

    protected function groupCacheKey(string $group): string
    {
        return CacheKeyRegistry::settingsGroup($group);
    }

    protected function clearCache(string $group, string $key): void
    {
        $this->cacheService->invalidateSettings($group, $key);
    }
}
