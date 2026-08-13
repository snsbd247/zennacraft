<?php

namespace App\Modules\Performance\Support;

class CacheKeyRegistry
{
    public const SETTINGS_GROUP_PREFIX = 'settings.group.';
    public const SETTINGS_VALUE_PREFIX = 'settings.value.';
    public const THEME_SETTINGS = 'theme.settings';
    public const THEME_MEDIA_PREFIX = 'theme.media.';
    public const STOREFRONT_PRODUCT_TAG = 'storefront.products';
    public const STOREFRONT_CATEGORY_TAG = 'storefront.categories';
    public const STOREFRONT_LANDING_PAGE_TAG = 'storefront.landing_pages';
    public const STOREFRONT_CONTENT_TAG = 'storefront.content';
    public const STOREFRONT_ACTIVE_CATEGORIES = 'storefront.active_categories';
    public const STOREFRONT_ACTIVE_SLIDERS = 'storefront.active_sliders';
    public const STOREFRONT_FOOTER_CMS_PAGES = 'storefront.footer_cms_pages';
    public const STOREFRONT_CMS_PAGE_PREFIX = 'storefront.cms_page.';
    public const STOREFRONT_LATEST_PRODUCTS_PREFIX = 'storefront.latest_products.';
    public const STOREFRONT_PRODUCT_PREFIX = 'storefront.product.';
    public const STOREFRONT_CATEGORY_PREFIX = 'storefront.category.';
    public const STOREFRONT_LANDING_PAGE_PREFIX = 'storefront.landing_page.';
    public const CUSTOMER_INTELLIGENCE_DASHBOARD = 'studio.customer_intelligence.dashboard';
    public const CUSTOMER_LIFECYCLE_PROFILES = 'studio.customer_lifecycle.profiles';
    public const CUSTOMER_LIFECYCLE_SUMMARY = 'studio.customer_lifecycle.summary';
    public const CUSTOMER_FRAUD_DASHBOARD = 'studio.customer_fraud.dashboard';
    public const CUSTOMER_PERSONALIZATION_MARKETING_INTENT = 'studio.customer.personalization.marketing_intent';
    public const ANALYTICS_TAG = 'studio.analytics';
    public const ANALYTICS_DASHBOARD = 'studio.analytics.dashboard';
    public const FINANCE_TAG = 'studio.finance';
    public const FINANCE_COMMAND_CENTER = 'studio.finance.command_center';
    public const COMMUNICATION_TAG = 'studio.communication';
    public const COMMUNICATION_DASHBOARD = 'studio.communication.dashboard';
    public const AUTOMATION_TAG = 'studio.automation';
    public const AUTOMATION_SUMMARY = 'studio.automation.summary';
    public const AUTOMATION_COMMAND_CENTER = 'studio.automation.command_center';
    public const MARKETING_SEGMENTS_TAG = 'marketing.segments';
    public const MARKETING_SEGMENT_SUMMARY = 'marketing.segment.summary';
    public const MARKETING_SEGMENT_PREFIX = 'marketing.segment.';
    public const MARKETING_SEGMENT_CUSTOMERS_PREFIX = 'marketing.segment.customers.';
    public const MARKETING_CAMPAIGNS_TAG = 'marketing.campaigns';
    public const MARKETING_CAMPAIGN_STATS_PREFIX = 'marketing.campaign.stats.';
    public const MARKETING_CAMPAIGN_DASHBOARD = 'marketing.campaign.dashboard';
    public const MARKETING_COMMAND_CENTER = 'marketing.command_center';
    public const REVIEWS_TAG = 'storefront.reviews';
    public const FEATURED_PRODUCT_REVIEWS = 'storefront.reviews.featured';
    public const PRODUCT_REVIEW_SUMMARY_PREFIX = 'storefront.reviews.product.';
    public const REPORTS_TAG = 'studio.reports';
    public const REPORTS_VERSION = 'studio.reports.version';
    public const REPORTS_DASHBOARD_PREFIX = 'studio.reports.dashboard.';
    public const STUDIO_DASHBOARD_TAG = 'studio.dashboard';
    public const STUDIO_DASHBOARD_PREFIX = 'studio.dashboard.';

    public static function settingValue(string $group, string $key): string
    {
        return self::SETTINGS_VALUE_PREFIX.$group.'.'.$key;
    }

    public static function settingsGroup(string $group): string
    {
        return self::SETTINGS_GROUP_PREFIX.$group;
    }

    public static function themeMedia(string $key): string
    {
        return self::THEME_MEDIA_PREFIX.$key;
    }

    public static function latestProducts(int $limit): string
    {
        return self::STOREFRONT_LATEST_PRODUCTS_PREFIX.$limit;
    }

    public static function productDetail(int $id, ?string $updatedAt = null): string
    {
        return self::STOREFRONT_PRODUCT_PREFIX.$id.'.'.($updatedAt ?: 'current');
    }

    public static function categoryDetail(int $id, ?string $updatedAt = null): string
    {
        return self::STOREFRONT_CATEGORY_PREFIX.$id.'.'.($updatedAt ?: 'current');
    }

    public static function landingPageDetail(int $id, ?string $updatedAt = null): string
    {
        return self::STOREFRONT_LANDING_PAGE_PREFIX.$id.'.'.($updatedAt ?: 'current');
    }

    public static function cmsPageDetail(int $id, ?string $updatedAt = null): string
    {
        return self::STOREFRONT_CMS_PAGE_PREFIX.$id.'.'.($updatedAt ?: 'current');
    }

    public static function studioDashboard(string $key, ?int $staffId = null): string
    {
        return self::STUDIO_DASHBOARD_PREFIX.($staffId ?? 0).'.'.$key;
    }

    public static function marketingSegment(int $id): string
    {
        return self::MARKETING_SEGMENT_PREFIX.$id;
    }

    public static function marketingSegmentCustomers(int $id, int $page = 1): string
    {
        return self::MARKETING_SEGMENT_CUSTOMERS_PREFIX.$id.'.'.$page;
    }

    public static function marketingCampaignStats(int $id): string
    {
        return self::MARKETING_CAMPAIGN_STATS_PREFIX.$id;
    }

    public static function productReviewSummary(int $productId): string
    {
        return self::PRODUCT_REVIEW_SUMMARY_PREFIX.$productId;
    }

    public static function reportDashboard(string $report, ?string $from = null, ?string $to = null, int|string $version = 1): string
    {
        return self::REPORTS_DASHBOARD_PREFIX.$version.'.'.$report.'.'.($from ?: 'default').'.'.($to ?: 'default');
    }

    public static function knownStorefrontKeys(): array
    {
        return [
            self::STOREFRONT_ACTIVE_CATEGORIES,
            self::STOREFRONT_ACTIVE_SLIDERS,
            self::STOREFRONT_FOOTER_CMS_PAGES,
            self::latestProducts(8),
            self::latestProducts(12),
        ];
    }
}
