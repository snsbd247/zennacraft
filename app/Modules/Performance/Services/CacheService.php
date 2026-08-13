<?php

namespace App\Modules\Performance\Services;

use App\Modules\Performance\Support\CacheKeyRegistry;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheService
{
    public const SHORT_TTL = 60;
    public const DEFAULT_TTL = 3600;

    public function remember(string $key, Closure $callback, ?int $seconds = null, array $tags = []): mixed
    {
        $ttl = $seconds ?? self::DEFAULT_TTL;
        $store = $tags !== [] && $this->supportsTags()
            ? Cache::tags($tags)
            : Cache::store();
        $missing = new class {};

        $cached = $store->get($key, $missing);

        if ($cached !== $missing) {
            if (! $this->containsObject($cached)) {
                return $cached;
            }

            $store->forget($key);
        }

        $value = $callback();

        if (! $this->containsObject($value)) {
            $store->put($key, $value, $ttl);
        }

        return $value;
    }

    public function forget(string $key, array $tags = []): void
    {
        if ($tags !== [] && $this->supportsTags()) {
            Cache::tags($tags)->forget($key);

            return;
        }

        Cache::forget($key);
    }

    public function flushTags(array $tags): void
    {
        if ($tags !== [] && $this->supportsTags()) {
            Cache::tags($tags)->flush();
        }
    }

    public function invalidateSettings(string $group = 'general', ?string $key = null): void
    {
        if ($key) {
            $this->forget(CacheKeyRegistry::settingValue($group, $key));
        }

        $this->forget(CacheKeyRegistry::settingsGroup($group));
    }

    public function invalidateTheme(): void
    {
        $this->forget(CacheKeyRegistry::THEME_SETTINGS);
        $this->invalidateSettings('theme');
        $this->invalidateStorefrontContent();

        foreach ([
            'site_logo',
            'site_favicon',
            'hero_image_id',
            'seo_default_og_image',
        ] as $key) {
            $this->forget(CacheKeyRegistry::themeMedia($key));
        }
    }

    public function invalidateCategories(): void
    {
        $this->invalidateStorefrontCategories();
    }

    public function invalidateProducts(): void
    {
        $this->invalidateStorefrontProducts();
    }

    public function invalidateLandingPages(): void
    {
        $this->invalidateStorefrontLandingPages();
    }

    public function invalidateStorefrontProducts(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::STOREFRONT_PRODUCT_TAG,
            CacheKeyRegistry::knownStorefrontKeys()
        );
    }

    public function invalidateStorefrontCategories(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::STOREFRONT_CATEGORY_TAG,
            [CacheKeyRegistry::STOREFRONT_ACTIVE_CATEGORIES]
        );
    }

    public function invalidateStorefrontLandingPages(): void
    {
        $this->flushOrForget(CacheKeyRegistry::STOREFRONT_LANDING_PAGE_TAG);
    }

    public function invalidateStorefrontContent(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::STOREFRONT_CONTENT_TAG,
            [
                CacheKeyRegistry::STOREFRONT_ACTIVE_SLIDERS,
                CacheKeyRegistry::STOREFRONT_FOOTER_CMS_PAGES,
            ]
        );
    }

    public function invalidateCustomerDashboards(): void
    {
        $this->forget(CacheKeyRegistry::CUSTOMER_INTELLIGENCE_DASHBOARD);
        $this->forget(CacheKeyRegistry::CUSTOMER_LIFECYCLE_PROFILES);
        $this->forget(CacheKeyRegistry::CUSTOMER_LIFECYCLE_SUMMARY);
        $this->forget(CacheKeyRegistry::CUSTOMER_PERSONALIZATION_MARKETING_INTENT);
    }

    public function invalidateFraudDashboard(): void
    {
        $this->forget(CacheKeyRegistry::CUSTOMER_FRAUD_DASHBOARD);
    }

    public function invalidateAnalyticsDashboard(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::ANALYTICS_TAG,
            [
                CacheKeyRegistry::ANALYTICS_DASHBOARD,
                CacheKeyRegistry::CUSTOMER_PERSONALIZATION_MARKETING_INTENT,
            ]
        );
    }

    public function invalidateFinanceDashboard(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::FINANCE_TAG,
            [CacheKeyRegistry::FINANCE_COMMAND_CENTER]
        );
    }

    public function invalidateCommunicationDashboard(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::COMMUNICATION_TAG,
            [CacheKeyRegistry::COMMUNICATION_DASHBOARD]
        );
    }

    public function invalidateAutomation(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::AUTOMATION_TAG,
            [
                CacheKeyRegistry::AUTOMATION_SUMMARY,
                CacheKeyRegistry::AUTOMATION_COMMAND_CENTER,
            ]
        );
    }

    public function invalidateReports(): void
    {
        $this->flushOrForget(CacheKeyRegistry::REPORTS_TAG);

        try {
            if (! is_numeric(Cache::get(CacheKeyRegistry::REPORTS_VERSION))) {
                Cache::forever(CacheKeyRegistry::REPORTS_VERSION, 1);
            }

            Cache::increment(CacheKeyRegistry::REPORTS_VERSION);
        } catch (Throwable) {
            Cache::forget(CacheKeyRegistry::REPORTS_VERSION);
        }
    }

    public function reportCacheVersion(): int
    {
        $version = Cache::get(CacheKeyRegistry::REPORTS_VERSION);

        if (! is_numeric($version)) {
            Cache::forever(CacheKeyRegistry::REPORTS_VERSION, 1);

            return 1;
        }

        return (int) $version;
    }

    public function invalidateMarketingSegments(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::MARKETING_SEGMENTS_TAG,
            [
                CacheKeyRegistry::MARKETING_SEGMENT_SUMMARY,
                CacheKeyRegistry::ANALYTICS_DASHBOARD,
                CacheKeyRegistry::MARKETING_COMMAND_CENTER,
            ]
        );

        $this->invalidateMarketingCommandCenter();
        $this->invalidateAnalyticsDashboard();
        $this->invalidateFinanceDashboard();
        $this->invalidateReports();
    }

    public function invalidateMarketingCampaigns(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::MARKETING_CAMPAIGNS_TAG,
            [
                CacheKeyRegistry::MARKETING_CAMPAIGN_DASHBOARD,
                CacheKeyRegistry::ANALYTICS_DASHBOARD,
                CacheKeyRegistry::MARKETING_COMMAND_CENTER,
            ]
        );

        $this->invalidateMarketingCommandCenter();
        $this->invalidateAnalyticsDashboard();
        $this->invalidateFinanceDashboard();
        $this->invalidateReports();
    }

    public function invalidateMarketingCommandCenter(): void
    {
        $this->flushOrForget(
            CacheKeyRegistry::MARKETING_CAMPAIGNS_TAG,
            [CacheKeyRegistry::MARKETING_COMMAND_CENTER]
        );
    }

    public function invalidateReviews(?int $productId = null): void
    {
        $fallbackKeys = [CacheKeyRegistry::FEATURED_PRODUCT_REVIEWS];

        if ($productId) {
            $fallbackKeys[] = CacheKeyRegistry::productReviewSummary($productId);
        }

        $this->flushOrForget(CacheKeyRegistry::REVIEWS_TAG, $fallbackKeys);
        $this->invalidateStorefrontProducts();
        $this->invalidateAnalyticsDashboard();
        $this->invalidateMarketingCommandCenter();
    }

    public function supportsTags(): bool
    {
        try {
            Cache::tags(['performance_probe'])->get('tags_supported_probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function flushOrForget(string $tag, array $fallbackKeys = []): void
    {
        if ($this->supportsTags()) {
            Cache::tags([$tag])->flush();

            return;
        }

        foreach ($fallbackKeys as $key) {
            $this->forget($key);
        }
    }

    protected function containsObject(mixed $value): bool
    {
        if (is_object($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsObject($item)) {
                return true;
            }
        }

        return false;
    }
}
