<?php

namespace App\Modules\Deployment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DeploymentHealthService
{
    public function checks(): array
    {
        return [
            'production_environment' => $this->productionEnvironmentChecks(),
            'queue_readiness' => $this->queueReadinessChecks(),
            'scheduler_readiness' => $this->schedulerReadinessChecks(),
            'cache_optimization' => $this->cacheOptimizationChecks(),
            'config_optimization' => $this->configOptimizationChecks(),
            'route_optimization' => $this->routeOptimizationChecks(),
            'view_optimization' => $this->viewOptimizationChecks(),
            'storage_health' => $this->storageHealthChecks(),
            'redis_readiness' => $this->redisReadinessChecks(),
            'production_security' => $this->productionSecurityChecks(),
        ];
    }

    public function flattenedChecks(): array
    {
        return collect($this->checks())
            ->flatMap(fn (array $checks) => $checks)
            ->values()
            ->all();
    }

    public function summary(): array
    {
        $checks = $this->flattenedChecks();
        $warnings = collect($checks)->where('status', 'warning')->count();

        return [
            'total' => count($checks),
            'passing' => count($checks) - $warnings,
            'warnings' => $warnings,
            'ready' => $warnings === 0,
        ];
    }

    protected function productionEnvironmentChecks(): array
    {
        return [
            $this->check(
                'Application Environment',
                app()->environment('production'),
                app()->environment(),
                'APP_ENV should be production before launch.'
            ),
            $this->check(
                'Debug Mode',
                ! (bool) config('app.debug'),
                config('app.debug') ? 'Enabled' : 'Disabled',
                'APP_DEBUG must be false in production.'
            ),
            $this->check(
                'Application Key',
                filled(config('app.key')),
                filled(config('app.key')) ? 'Configured' : 'Missing',
                'APP_KEY must be set for encrypted cookies and signed data.'
            ),
            $this->check(
                'Application URL',
                Str::startsWith((string) config('app.url'), 'https://'),
                (string) config('app.url'),
                'Production APP_URL should use HTTPS.'
            ),
            $this->check(
                'Database Connection',
                $this->databaseReachable(),
                $this->databaseReachable() ? DB::connection()->getDriverName() : 'Unavailable',
                'The configured production database must be reachable.'
            ),
        ];
    }

    protected function queueReadinessChecks(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver", $connection);

        return [
            $this->check(
                'Queue Connection',
                $driver !== 'sync',
                (string) $driver,
                'Production should use an async queue driver with a worker process.'
            ),
            $this->check(
                'Jobs Table',
                $driver !== 'database' || $this->tableExists((string) config("queue.connections.{$connection}.table", 'jobs')),
                (string) config("queue.connections.{$connection}.table", 'jobs'),
                'Database queue requires the jobs table.'
            ),
            $this->check(
                'Failed Jobs Table',
                $this->tableExists((string) config('queue.failed.table', 'failed_jobs')),
                (string) config('queue.failed.table', 'failed_jobs'),
                'Failed jobs should be persisted for operational review.'
            ),
            $this->check(
                'Queue Retry Window',
                (int) config("queue.connections.{$connection}.retry_after", 0) > 0,
                (string) config("queue.connections.{$connection}.retry_after", 'n/a'),
                'retry_after should be set for queue drivers that support retries.'
            ),
        ];
    }

    protected function schedulerReadinessChecks(): array
    {
        $consolePath = base_path('routes/console.php');
        $consoleContents = is_file($consolePath) ? (string) file_get_contents($consolePath) : '';
        $hasScheduleDefinitions = str_contains($consoleContents, 'Schedule::')
            || str_contains($consoleContents, '->every')
            || str_contains($consoleContents, '->daily')
            || str_contains($consoleContents, '->hourly');

        return [
            $this->check(
                'Console Routes File',
                is_file($consolePath),
                is_file($consolePath) ? 'Present' : 'Missing',
                'Scheduler definitions normally live in routes/console.php.'
            ),
            $this->check(
                'Scheduled Tasks',
                $hasScheduleDefinitions,
                $hasScheduleDefinitions ? 'Detected' : 'None detected',
                'If production needs scheduled work, define it and configure cron outside the app.'
            ),
            $this->check(
                'Schedule Cache Directory',
                is_dir(storage_path('framework/cache')),
                storage_path('framework/cache'),
                'Scheduler mutex/cache files require framework cache storage.'
            ),
        ];
    }

    protected function cacheOptimizationChecks(): array
    {
        $defaultStore = config('cache.default');
        $driver = config("cache.stores.{$defaultStore}.driver", $defaultStore);

        return [
            $this->check(
                'Default Cache Store',
                ! in_array($driver, ['array', 'null'], true),
                (string) $driver,
                'Production cache should use a persistent store.'
            ),
            $this->check(
                'Cache Table',
                $driver !== 'database' || $this->tableExists((string) config("cache.stores.{$defaultStore}.table", 'cache')),
                (string) config("cache.stores.{$defaultStore}.table", 'cache'),
                'Database cache requires the cache table.'
            ),
            $this->check(
                'Cache Data Directory',
                is_dir(storage_path('framework/cache')),
                storage_path('framework/cache'),
                'File-based framework cache path must exist.'
            ),
            $this->check(
                'Named Rate Limiters',
                $this->rateLimitersReady(),
                $this->rateLimitersReady() ? 'Configured' : 'Missing',
                'Checkout, OTP, tracking, and API limiters should be registered.'
            ),
        ];
    }

    protected function configOptimizationChecks(): array
    {
        return [
            $this->check(
                'Configuration Cache',
                app()->configurationIsCached(),
                app()->configurationIsCached() ? 'Cached' : 'Not cached',
                'Run config cache during deployment, not from this dashboard.'
            ),
            $this->check(
                'Bootstrap Cache Directory',
                is_dir(base_path('bootstrap/cache')) && is_writable(base_path('bootstrap/cache')),
                base_path('bootstrap/cache'),
                'Deployment user must be able to write optimized cache files.'
            ),
            $this->check(
                'Services Manifest',
                is_file(base_path('bootstrap/cache/services.php')),
                is_file(base_path('bootstrap/cache/services.php')) ? 'Present' : 'Missing',
                'Composer/Laravel discovery should generate services metadata.'
            ),
        ];
    }

    protected function routeOptimizationChecks(): array
    {
        return [
            $this->check(
                'Route Cache',
                app()->routesAreCached(),
                app()->routesAreCached() ? 'Cached' : 'Not cached',
                'Run route cache during deployment after route changes are complete.'
            ),
            $this->check(
                'Studio Route',
                Route::has('studio.dashboard'),
                Route::has('studio.dashboard') ? route('studio.dashboard', [], false) : 'Missing',
                'Studio dashboard route must remain available.'
            ),
            $this->check(
                '/admin Route Absence',
                $this->adminRouteUris() === [],
                $this->adminRouteUris() === [] ? 'No /admin routes detected' : implode(', ', $this->adminRouteUris()),
                'Production admin surface must stay on /studio.'
            ),
        ];
    }

    protected function viewOptimizationChecks(): array
    {
        $viewPath = storage_path('framework/views');
        $compiledViews = is_dir($viewPath) ? glob($viewPath.'/*.php') ?: [] : [];

        return [
            $this->check(
                'Compiled View Directory',
                is_dir($viewPath) && is_writable($viewPath),
                $viewPath,
                'Blade compiled views require a writable storage/framework/views directory.'
            ),
            $this->check(
                'Compiled Views',
                count($compiledViews) > 0,
                (string) count($compiledViews),
                'Run view cache during deployment when appropriate.'
            ),
        ];
    }

    protected function storageHealthChecks(): array
    {
        $paths = [
            'Storage Root' => storage_path(),
            'Logs Directory' => storage_path('logs'),
            'Framework Directory' => storage_path('framework'),
            'Public Media Directory' => storage_path('app/public'),
        ];

        $checks = [];

        foreach ($paths as $label => $path) {
            $checks[] = $this->check(
                $label,
                is_dir($path) && is_writable($path),
                $path,
                $label.' must exist and be writable by the app user.'
            );
        }

        $checks[] = $this->check(
            'Public Storage Link',
            is_link(public_path('storage')) || is_dir(public_path('storage')),
            public_path('storage'),
            'The public storage link should exist for storefront media.'
        );

        return $checks;
    }

    protected function redisReadinessChecks(): array
    {
        $usesRedis = collect([
            config('cache.stores.'.config('cache.default').'.driver'),
            config('queue.connections.'.config('queue.default').'.driver'),
            config('session.driver'),
        ])->contains('redis');

        return [
            $this->check(
                'Redis Required By Config',
                true,
                $usesRedis ? 'Yes' : 'No',
                'Redis readiness is required only when cache, queue, or session uses Redis.'
            ),
            $this->check(
                'Redis Extension / Client',
                ! $usesRedis || extension_loaded('redis') || class_exists(\Predis\Client::class),
                extension_loaded('redis') ? 'phpredis' : (class_exists(\Predis\Client::class) ? 'predis' : 'Unavailable'),
                'Configured Redis usage needs a Redis PHP client.'
            ),
            $this->check(
                'Redis Connection',
                ! $usesRedis || $this->redisReachable(),
                $usesRedis ? ($this->redisReachable() ? 'Reachable' : 'Unavailable') : 'Not required',
                'Read-only Redis ping is attempted only when Redis is configured for app services.'
            ),
        ];
    }

    protected function productionSecurityChecks(): array
    {
        return [
            $this->check(
                'Secure Session Cookie',
                (bool) config('session.secure'),
                config('session.secure') ? 'Enabled' : 'Disabled',
                'SESSION_SECURE_COOKIE should be true behind HTTPS.'
            ),
            $this->check(
                'HTTP Only Session Cookie',
                (bool) config('session.http_only'),
                config('session.http_only') ? 'Enabled' : 'Disabled',
                'Session cookies should be HTTP only.'
            ),
            $this->check(
                'SameSite Cookie',
                filled(config('session.same_site')),
                (string) config('session.same_site'),
                'SameSite reduces cross-site request risk.'
            ),
            $this->check(
                'Maintenance Mode',
                ! app()->isDownForMaintenance(),
                app()->isDownForMaintenance() ? 'Enabled' : 'Disabled',
                'Production should not be left in maintenance mode after deployment.'
            ),
            $this->check(
                'API Public Rate Limit',
                RateLimiter::limiter('api-public') !== null,
                RateLimiter::limiter('api-public') !== null ? 'Configured' : 'Missing',
                'Public API should remain rate limited.'
            ),
        ];
    }

    protected function check(string $label, bool $passes, string $value, string $details): array
    {
        return [
            'label' => $label,
            'status' => $passes ? 'pass' : 'warning',
            'value' => $value,
            'details' => $details,
        ];
    }

    protected function databaseReachable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function rateLimitersReady(): bool
    {
        foreach (['api-public', 'api-customer', 'api-staff', 'checkout', 'otp-request', 'otp-verify', 'tracking-lookup', 'staff-login'] as $name) {
            if (RateLimiter::limiter($name) === null) {
                return false;
            }
        }

        return true;
    }

    protected function adminRouteUris(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => $uri === 'admin' || str_starts_with($uri, 'admin/'))
            ->values()
            ->all();
    }

    protected function redisReachable(): bool
    {
        try {
            app('redis')->connection()->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
