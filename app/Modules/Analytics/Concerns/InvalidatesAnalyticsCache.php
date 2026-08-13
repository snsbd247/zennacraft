<?php

namespace App\Modules\Analytics\Concerns;

use App\Modules\Performance\Services\CacheService;
use Throwable;

trait InvalidatesAnalyticsCache
{
    protected static function bootInvalidatesAnalyticsCache(): void
    {
        static::saved(fn (): null => static::flushAnalyticsCache());
        static::deleted(fn (): null => static::flushAnalyticsCache());
    }

    protected static function flushAnalyticsCache(): null
    {
        try {
            $cacheService = app(CacheService::class);
            $cacheService->invalidateAnalyticsDashboard();
            $cacheService->invalidateFinanceDashboard();
            $cacheService->invalidateMarketingCommandCenter();
            $cacheService->invalidateReports();
        } catch (Throwable) {
            // Analytics cache invalidation must never interrupt business writes.
        }

        return null;
    }
}
