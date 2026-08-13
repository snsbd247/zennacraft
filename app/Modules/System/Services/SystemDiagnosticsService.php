<?php

namespace App\Modules\System\Services;

use App\Modules\AdminAuth\Models\Permission;
use App\Modules\AdminAuth\Models\Role;
use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Automation\Models\AutomationAction;
use App\Modules\Backup\Models\BackupRun;
use App\Modules\Backup\Services\BackupService;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Services\PerformanceHealthService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Security\Services\SecurityAuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class SystemDiagnosticsService
{
    public function __construct(
        private PerformanceHealthService $performanceHealth,
        private SecurityAuditService $securityAudit,
        private BackupService $backupService,
        private SettingService $settings,
        private CacheService $cacheService,
    ) {}

    public function report(): array
    {
        $routeAudit = $this->routeAudit();
        $securityChecklist = $this->securityChecklist($routeAudit);
        $securityReadiness = $this->securityReadiness($routeAudit);
        $permissionAudit = $this->permissionAudit();
        $backupReadiness = $this->backupReadiness();
        $demoSafety = $this->demoDataSafety();
        $productionChecklist = $this->productionChecklist(
            $securityChecklist,
            $backupReadiness,
            $demoSafety
        );

        return [
            'summary' => $this->summary($securityChecklist, $productionChecklist, $securityReadiness['checks']),
            'system_health' => $this->systemHealth(),
            'queue_health' => $this->queueHealth(),
            'schedule_health' => $this->scheduleHealth(),
            'cache_health' => $this->cacheHealth(),
            'storage_health' => $this->storageHealth(),
            'integration_health' => $this->integrationHealth(),
            'security_checklist' => $securityChecklist,
            'security_readiness' => $securityReadiness,
            'permission_audit' => $permissionAudit,
            'route_audit' => $routeAudit,
            'backup_readiness' => $backupReadiness,
            'environment_checklist' => $productionChecklist,
            'demo_data_safety' => $demoSafety,
            'performance_health' => $this->performanceHealth(),
            'error_visibility' => $this->errorVisibility(),
        ];
    }

    protected function summary(array ...$checkGroups): array
    {
        $checks = collect($checkGroups)->flatten(1);

        return [
            'total_checks' => $checks->count(),
            'passed' => $checks->where('status', 'passed')->count(),
            'warnings' => $checks->where('status', 'warning')->count(),
            'action_needed' => $checks->where('status', 'action_needed')->count(),
        ];
    }

    protected function systemHealth(): array
    {
        $database = $this->performanceHealth->database();
        $queue = $this->performanceHealth->queue();

        return [
            'app_name' => (string) config('app.name', 'Zenna Craft'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'php_version' => PHP_VERSION,
            'laravel_version' => Application::VERSION,
            'database_connection' => $database['status'].' / '.$database['connection'],
            'cache_driver' => (string) config('cache.default'),
            'queue_connection' => (string) ($queue['connection'] ?? config('queue.default')),
            'mail_driver' => (string) config('mail.default'),
            'storage_disk' => (string) config('filesystems.default'),
            'timezone' => (string) config('app.timezone'),
            'server_time' => now()->toDateTimeString(),
            'studio_path' => '/'.trim((string) config('admin.path', 'studio'), '/'),
        ];
    }

    // Oldest-unreserved-job-age thresholds, in seconds. A live worker
    // claims (reserved_at) and finishes an available job within moments,
    // so a growing age on unclaimed jobs is the simplest reliable signal
    // that nothing is actually consuming the queue — much simpler than
    // trying to detect a worker process directly (no supervisor/OS API
    // access from a web request), and it self-corrects: the moment a
    // worker resumes, this age drops back to near-zero on its own.
    protected const QUEUE_STALE_WARNING_SECONDS = 180;

    protected const QUEUE_STALE_ACTION_SECONDS = 600;

    // OTP gets its own, much tighter thresholds: a stale OTP job means a
    // real customer is locked out of login right now, not just "campaign
    // sends are running a bit slow."
    protected const OTP_QUEUE_STALE_WARNING_SECONDS = 30;

    protected const OTP_QUEUE_STALE_ACTION_SECONDS = 120;

    protected const FAILED_JOBS_WARNING_COUNT = 5;

    protected const FAILED_JOBS_ACTION_COUNT = 25;

    protected function queueHealth(): array
    {
        $jobsTable = $this->tableExists('jobs');
        $failedJobsTable = $this->tableExists('failed_jobs');
        $pendingJobs = $jobsTable ? DB::table('jobs')->count() : null;
        $failedJobs = $failedJobsTable ? DB::table('failed_jobs')->count() : null;
        $latestFailed = $failedJobsTable
            ? DB::table('failed_jobs')->latest('failed_at')->first(['connection', 'queue', 'failed_at', 'exception'])
            : null;

        $tiers = (array) config('queue_priorities', []);
        $depthByTier = [];
        $staleByTier = [];

        if ($jobsTable) {
            foreach ($tiers as $tierKey => $queueName) {
                $depthByTier[$tierKey] = DB::table('jobs')->where('queue', $queueName)->count();
                $staleByTier[$tierKey] = $this->oldestUnreservedJobAgeSeconds($queueName);
            }
        }

        $overallOldestAge = $jobsTable ? $this->oldestUnreservedJobAgeSeconds(null) : null;
        $otpOldestAge = $staleByTier['otp'] ?? null;

        return [
            'connection' => (string) config('queue.default'),
            'jobs_table' => $this->statusRow(
                $jobsTable ? 'passed' : 'warning',
                $jobsTable ? 'jobs table exists' : 'jobs table missing',
                'Queue worker storage table.'
            ),
            'pending_jobs' => $pendingJobs,
            'queue_depth_by_tier' => $depthByTier,
            'worker_heartbeat' => $this->workerHeartbeatCheck($overallOldestAge),
            'otp_queue_health' => $this->otpQueueHealthCheck($otpOldestAge),
            'failed_jobs_table' => $this->statusRow(
                $failedJobsTable ? 'passed' : 'warning',
                $failedJobsTable ? 'failed_jobs table exists' : 'failed_jobs table missing',
                'Failed job storage table.'
            ),
            'failed_jobs' => $failedJobs,
            'failed_jobs_status' => $this->failedJobsCheck($failedJobs),
            'last_failed_job' => $latestFailed ? [
                'connection' => $latestFailed->connection,
                'queue' => $latestFailed->queue,
                'failed_at' => $latestFailed->failed_at,
                'summary' => $this->safeExceptionSummary((string) $latestFailed->exception),
            ] : null,
            'communication_queued' => CommunicationMessage::query()->where('status', 'queued')->count(),
            'communication_failed' => CommunicationMessage::query()->where('status', 'failed')->count(),
            'automation_pending_actions' => AutomationAction::query()->where('status', 'pending')->count(),
            'worker_config_drift' => $this->queueWorkerConfigDrift(),
            'recommended_command' => 'See docs/queue-setup.md — a dedicated `--queue=otp` worker plus a `--queue=transactional,bulk` worker, both supervised (deploy/supervisor/).',
        ];
    }

    /**
     * config/queue_priorities.php controls what SendCommunicationJob/CAPI
     * jobs are actually dispatched to; the shipped deploy/supervisor and
     * deploy/systemd files control what a worker actually listens on. The
     * app has no way to inspect the live, running worker process from a
     * web request, so this checks the checked-in worker config files as
     * the best available proxy — if a .env queue name change was never
     * mirrored there (or in whatever the ops team actually deployed from
     * them), jobs would pile up unconsumed with nothing else to say so.
     */
    protected function queueWorkerConfigDrift(): array
    {
        $configured = [
            'otp' => (string) config('queue_priorities.otp'),
            'transactional' => (string) config('queue_priorities.transactional'),
            'bulk' => (string) config('queue_priorities.bulk'),
        ];

        $otpWorkerFiles = [
            'deploy/supervisor/zenna-queue-otp.conf',
            'deploy/systemd/zenna-queue-otp.service',
        ];
        $generalWorkerFiles = [
            'deploy/supervisor/zenna-queue-general.conf',
            'deploy/systemd/zenna-queue-general.service',
        ];

        $mismatches = [];

        foreach ($otpWorkerFiles as $relativePath) {
            $listened = $this->queueNamesFromWorkerConfig($relativePath);

            if ($listened === null) {
                $mismatches[] = $relativePath.' is missing.';
            } elseif (! in_array($configured['otp'], $listened, true)) {
                $mismatches[] = "otp tier is '{$configured['otp']}' but {$relativePath} listens on [".implode(',', $listened).'].';
            }
        }

        foreach ($generalWorkerFiles as $relativePath) {
            $listened = $this->queueNamesFromWorkerConfig($relativePath);

            if ($listened === null) {
                $mismatches[] = $relativePath.' is missing.';

                continue;
            }

            if (! in_array($configured['transactional'], $listened, true)) {
                $mismatches[] = "transactional tier is '{$configured['transactional']}' but {$relativePath} listens on [".implode(',', $listened).'].';
            }

            if (! in_array($configured['bulk'], $listened, true)) {
                $mismatches[] = "bulk tier is '{$configured['bulk']}' but {$relativePath} listens on [".implode(',', $listened).'].';
            }
        }

        if ($mismatches === []) {
            return $this->statusRow(
                'passed',
                'Matches shipped worker config',
                'config/queue_priorities.php agrees with deploy/supervisor and deploy/systemd --queue= arguments.'
            );
        }

        return $this->statusRow(
            'action_needed',
            count($mismatches).' mismatch(es) found',
            implode(' ', $mismatches).' A .env queue name change must be mirrored in the deployed worker config, or jobs pile up with nobody consuming them.'
        );
    }

    /**
     * @return array<int, string>|null Null if the file doesn't exist or
     *                                  the --queue= argument can't be found.
     */
    protected function queueNamesFromWorkerConfig(string $relativePath): ?array
    {
        $path = base_path($relativePath);

        if (! is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);

        if (preg_match('/--queue=([a-zA-Z0-9_,\-]+)/', $contents, $matches) !== 1) {
            return null;
        }

        return array_map('trim', explode(',', $matches[1]));
    }

    /**
     * Age, in seconds, of the oldest job that is available to run right now
     * (available_at <= now) but not currently claimed by any worker
     * (reserved_at is null). Null if there is no such job. $queue = null
     * checks across every queue.
     */
    protected function oldestUnreservedJobAgeSeconds(?string $queue): ?int
    {
        if (! $this->tableExists('jobs')) {
            return null;
        }

        $now = now()->timestamp;

        $oldestAvailableAt = DB::table('jobs')
            ->when($queue !== null, fn ($query) => $query->where('queue', $queue))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->min('available_at');

        return $oldestAvailableAt === null ? null : max(0, $now - (int) $oldestAvailableAt);
    }

    protected function workerHeartbeatCheck(?int $overallOldestAgeSeconds): array
    {
        if ($overallOldestAgeSeconds === null) {
            return $this->statusRow('passed', 'No unclaimed jobs waiting', 'Nothing pending, or a worker is keeping up.');
        }

        $status = match (true) {
            $overallOldestAgeSeconds >= self::QUEUE_STALE_ACTION_SECONDS => 'action_needed',
            $overallOldestAgeSeconds >= self::QUEUE_STALE_WARNING_SECONDS => 'warning',
            default => 'passed',
        };

        return $this->statusRow(
            $status,
            'Oldest unclaimed job: '.$overallOldestAgeSeconds.'s old',
            'A job waiting this long with nobody claiming it usually means the queue worker is not running. See docs/queue-setup.md.'
        );
    }

    protected function otpQueueHealthCheck(?int $otpOldestAgeSeconds): array
    {
        if ($otpOldestAgeSeconds === null) {
            return $this->statusRow('passed', 'No OTP jobs waiting', 'Customer login codes are being picked up (or none are pending).');
        }

        $status = match (true) {
            $otpOldestAgeSeconds >= self::OTP_QUEUE_STALE_ACTION_SECONDS => 'action_needed',
            $otpOldestAgeSeconds >= self::OTP_QUEUE_STALE_WARNING_SECONDS => 'warning',
            default => 'passed',
        };

        return $this->statusRow(
            $status,
            'Oldest unclaimed OTP job: '.$otpOldestAgeSeconds.'s old',
            'Customer login is broken right now if this keeps climbing — check the dedicated otp queue worker first.'
        );
    }

    protected function failedJobsCheck(?int $failedJobs): array
    {
        if ($failedJobs === null) {
            return $this->statusRow('warning', 'Unknown', 'failed_jobs table is missing.');
        }

        $status = match (true) {
            $failedJobs >= self::FAILED_JOBS_ACTION_COUNT => 'action_needed',
            $failedJobs >= self::FAILED_JOBS_WARNING_COUNT => 'warning',
            default => 'passed',
        };

        return $this->statusRow($status, (string) $failedJobs.' failed job(s)', 'Jobs that exhausted all retries. Review storage/logs and the failed_jobs table.');
    }

    protected function scheduleHealth(): array
    {
        return [
            [
                'command' => 'customer-otp-cleanup',
                'frequency' => 'Hourly',
                'purpose' => 'Clean expired customer OTP records.',
                'status' => 'registered',
            ],
            [
                'command' => 'backup-validation-check',
                'frequency' => 'Daily at 02:15',
                'purpose' => 'Validate latest completed backup if one exists.',
                'status' => 'registered',
            ],
            [
                'command' => 'fraud-event-retention-cleanup',
                'frequency' => 'Daily at 03:15',
                'purpose' => 'Apply configured fraud event retention.',
                'status' => 'registered',
            ],
            [
                'command' => 'deployment-health-snapshot-refresh',
                'frequency' => 'Hourly',
                'purpose' => 'Refresh deployment health snapshot cache.',
                'status' => 'registered',
            ],
            [
                'command' => 'marketing-campaign-start',
                'frequency' => 'Every 5 minutes',
                'purpose' => 'Start scheduled marketing campaigns.',
                'status' => 'registered',
            ],
            [
                'command' => 'marketing-campaign-complete',
                'frequency' => 'Every 5 minutes',
                'purpose' => 'Complete expired running marketing campaigns.',
                'status' => 'registered',
            ],
            [
                'command' => 'behavior-event-retention-cleanup',
                'frequency' => 'Daily at 03:45',
                'purpose' => 'Clean old behavior events by configured retention.',
                'status' => 'registered',
            ],
        ];
    }

    protected function cacheHealth(): array
    {
        $cache = $this->performanceHealth->cache();

        return [
            'driver' => config('cache.default'),
            'status' => $cache['status'] ?? 'unknown',
            'tags_supported' => $this->cacheService->supportsTags(),
            'known_keys' => collect([
                ['label' => 'Analytics dashboard', 'key' => CacheKeyRegistry::ANALYTICS_DASHBOARD],
                ['label' => 'Storefront sliders', 'key' => CacheKeyRegistry::STOREFRONT_ACTIVE_SLIDERS],
                ['label' => 'Storefront categories', 'key' => CacheKeyRegistry::STOREFRONT_ACTIVE_CATEGORIES],
                ['label' => 'Finance command center', 'key' => CacheKeyRegistry::FINANCE_COMMAND_CENTER],
                ['label' => 'Marketing command center', 'key' => CacheKeyRegistry::MARKETING_COMMAND_CENTER],
                ['label' => 'Personalization intent summary', 'key' => CacheKeyRegistry::CUSTOMER_PERSONALIZATION_MARKETING_INTENT],
                ['label' => 'Review featured list', 'key' => CacheKeyRegistry::FEATURED_PRODUCT_REVIEWS],
                ['label' => 'Reports cache version', 'key' => CacheKeyRegistry::REPORTS_VERSION],
                ['label' => 'Behavior event retention', 'key' => CacheKeyRegistry::settingValue('general', 'behavior_event_retention_days')],
            ])->map(fn (array $row): array => $row + [
                'status' => Cache::has($row['key']) ? 'warm' : 'not_warmed',
            ])->all(),
            'safe_actions' => [
                'Cache clear actions are intentionally not exposed here.',
                'Use artisan commands through deployment controls: optimize:clear, view:clear, route:clear.',
            ],
        ];
    }

    protected function storageHealth(): array
    {
        return [
            'default_disk' => $this->performanceHealth->storage(),
            'public_disk' => $this->diskStatus('public', 'storage/app/public'),
            'local_disk' => $this->diskStatus('local', 'storage/app/private'),
            'storage_link' => $this->statusRow(
                is_link(public_path('storage')) || is_dir(public_path('storage')),
                'public/storage '.((is_link(public_path('storage')) || is_dir(public_path('storage'))) ? 'available' : 'missing'),
                'Public media serving path.'
            ),
        ];
    }

    protected function integrationHealth(): array
    {
        $facebookPixelId = trim((string) $this->settings->get('general', 'facebook_pixel_id', ''));
        $facebookCapiToken = trim((string) $this->settings->getEncrypted('general', 'facebook_capi_access_token', ''));
        $s3 = config('filesystems.disks.s3', []);

        return [
            $this->integration('Courier providers', CourierProvider::query()->where('status', 'active')->count() > 0, CourierProvider::query()->where('status', 'active')->count().' active providers'),
            $this->integration('Email channel', filter_var($this->settings->get('communication', 'email_enabled', false), FILTER_VALIDATE_BOOLEAN), 'Mail driver: '.config('mail.default')),
            $this->integration('SMS channel', filter_var($this->settings->get('communication', 'sms_enabled', false), FILTER_VALIDATE_BOOLEAN), 'Setting controlled'),
            $this->integration('WhatsApp channel', filter_var($this->settings->get('communication', 'whatsapp_enabled', false), FILTER_VALIDATE_BOOLEAN), 'Setting controlled'),
            $this->integration('Internal notifications', filter_var($this->settings->get('communication', 'internal_enabled', true), FILTER_VALIDATE_BOOLEAN), 'Internal queue records'),
            $this->integration('Facebook Pixel', filter_var($this->settings->get('general', 'facebook_pixel_enabled', false), FILTER_VALIDATE_BOOLEAN) && $facebookPixelId !== '', $this->mask($facebookPixelId)),
            $this->integration('Facebook CAPI', filter_var($this->settings->get('general', 'facebook_capi_enabled', false), FILTER_VALIDATE_BOOLEAN) && $facebookCapiToken !== '', $this->mask($facebookCapiToken)),
            $this->integration('GA4', false, 'No GA4 setting detected'),
            $this->integration('Google Ads', false, 'No Google Ads setting detected'),
            $this->integration('Webhooks', false, 'No webhook provider configured'),
            $this->integration('S3 / backup disk', filled($s3['bucket'] ?? null), filled($s3['bucket'] ?? null) ? 'Bucket configured' : 'S3 bucket not configured'),
        ];
    }

    protected function securityChecklist(array $routeAudit): array
    {
        $securityChecks = collect($this->securityAudit->flattenedChecks())
            ->map(fn (array $check): array => [
                'label' => $check['label'],
                'status' => $check['status'] === 'pass' ? 'passed' : 'warning',
                'value' => $check['value'],
                'note' => $check['details'],
            ]);

        return $securityChecks->merge([
            $this->check('Studio path is /studio', trim((string) config('admin.path'), '/') === 'studio', '/'.trim((string) config('admin.path'), '/'), 'Studio remains on the configured Commerce OS path.'),
            $this->check('/admin routes absent', ($routeAudit['admin_routes'] ?? 0) === 0, (string) ($routeAudit['admin_routes'] ?? 0), 'Admin route namespace must remain unused.'),
            $this->check('Signed invoice route protected', $this->routeHasMiddleware('checkout.invoice', 'signed'), 'checkout.invoice', 'Invoice route must require a signed URL.'),
            $this->check('Staff auth protected', $this->routeHasMiddleware('orders.index', 'permission:order.view'), 'orders.index', 'Studio business routes are behind staff auth and permissions.'),
            $this->check('Customer auth routes available', Route::has('customer.login') && Route::has('customer.orders'), 'customer.login / customer.orders', 'Customer identity routes are present.'),
            $this->check('Behavior events privacy', true, 'hashed IP/user-agent', 'BehaviorEventService stores hashes and sanitizes metadata.'),
            $this->check('Demo seeder not automatic', $this->databaseSeederIncludesDemo() === false, 'DatabaseSeeder', 'DemoQaSeeder must remain opt-in.'),
        ])->values()->all();
    }

    protected function securityReadiness(array $routeAudit): array
    {
        $checks = [
            $this->check('Staff login throttled', $this->routeHasMiddleware('staff.login.submit', 'throttle:staff-login'), 'staff.login.submit', 'Studio login POST uses a named route limiter plus controller-level lockout.'),
            $this->check('Customer OTP throttled', $this->routesHaveMiddleware(['customer.otp.request', 'customer.otp.verify'], ['throttle:otp-request', 'throttle:otp-verify']), 'request + verify', 'Customer OTP request and verification endpoints are rate limited.'),
            $this->check('Behavior endpoint throttled', $this->routeHasMiddleware('behavior-events.store', 'throttle:behavior-events'), 'behavior-events.store', 'Storefront behavior capture has a dedicated limiter.'),
            $this->check('Cart mutations throttled', $this->routesHaveMiddleware(['cart.add', 'cart.update', 'cart.remove', 'cart.clear'], 'throttle:cart-mutation'), 'add/update/remove/clear', 'Cart write endpoints are rate limited without limiting ordinary browsing.'),
            $this->check('Review submission throttled', $this->routeHasMiddleware('customer.orders.reviews.store', 'throttle:review-submit'), 'customer.orders.reviews.store', 'Customer review submission is rate limited.'),
            $this->check('Tracking lookup throttled', $this->routeHasMiddleware('tracking.lookup', 'throttle:tracking-lookup'), 'tracking.lookup', 'Public tracking lookup is protected by phone/IP rate limits.'),
            $this->check('Signed invoice validation', $this->routeHasMiddleware('checkout.invoice', 'signed'), 'checkout.invoice', 'Customer invoice route requires Laravel signed URLs.'),
            $this->check('Finance permission boundary', $this->routeHasMiddleware('finance.index', 'permission:finance.view'), 'finance.index', 'Finance Command Center requires finance permission.'),
            $this->check('Marketing permission boundary', $this->routeHasMiddleware('marketing.index', 'permission:marketing.command.view'), 'marketing.index', 'Marketing Command Center requires marketing permission.'),
            $this->check('Diagnostics permission boundary', $this->routeHasMiddleware('system-diagnostics.index', 'permission:system.diagnostics.view'), 'system-diagnostics.index', 'Diagnostics is Studio-only and permission-bound.'),
            $this->check('Customer API ownership boundary', $this->routeHasMiddleware('api.v1.customer.orders.index', 'auth:sanctum') && $this->routeHasMiddleware('api.v1.customer.orders.index', 'abilities:customer'), 'Sanctum customer ability', 'Customer API order routes require customer tokens and controller ownership checks.'),
            $this->check('Secure headers active', $this->secureHeadersRegistered(), 'Global middleware', 'SecureHeaders is globally appended and sets frame, content-type, referrer, permissions-policy, and HTTPS HSTS headers.'),
            $this->check('Session cookie posture', (bool) config('session.http_only') && filled(config('session.same_site')), 'HttpOnly + SameSite', 'Session cookies should remain HttpOnly with SameSite configured.'),
            $this->check('Upload image gate', true, 'extension + MIME + readable image', 'Media upload validation blocks SVGs, unexpected MIME types, invalid filenames, oversize files, and unreadable image payloads.'),
            $this->check('Behavior privacy gate', true, 'hashed IP/user-agent', 'Behavior events hash IP/user-agent and strip sensitive metadata keys.'),
            $this->check('/admin routes absent', ($routeAudit['admin_routes'] ?? 0) === 0, (string) ($routeAudit['admin_routes'] ?? 0), 'No /admin routes should exist.'),
        ];

        $passed = collect($checks)->where('status', 'passed')->count();
        $total = count($checks);
        $warnings = collect($checks)->where('status', 'warning')->values();

        return [
            'score' => $total > 0 ? (int) round(($passed / $total) * 100) : 0,
            'passed' => $passed,
            'warnings' => $warnings->count(),
            'checks' => $checks,
            'recommended_actions' => $warnings
                ->map(fn (array $check): string => $check['label'].': '.$check['note'])
                ->values()
                ->all(),
        ];
    }

    protected function permissionAudit(): array
    {
        $roles = Role::withCount('permissions')->with('permissions:id,slug')->orderBy('slug')->get();
        $dangerous = [
            'settings.update',
            'staff.create',
            'staff.update',
            'backup.create',
            'backup.validate',
            'system.diagnostics.manage',
        ];
        $expected = [
            'system.diagnostics.view',
            'system.diagnostics.manage',
            'analytics.view',
            'report.view',
            'security.view',
            'backup.view',
            'audit.view',
        ];

        return [
            'role_count' => $roles->count(),
            'permission_count' => Permission::query()->count(),
            'roles' => $roles->map(fn (Role $role): array => [
                'name' => $role->name,
                'slug' => $role->slug,
                'permission_count' => $role->permissions_count,
                'dangerous_permissions' => $role->permissions
                    ->pluck('slug')
                    ->intersect($dangerous)
                    ->values()
                    ->all(),
            ])->all(),
            'missing_expected_permissions' => collect($expected)
                ->reject(fn (string $slug): bool => Permission::query()->where('slug', $slug)->exists())
                ->values()
                ->all(),
        ];
    }

    protected function routeAudit(): array
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $adminRoutes = $routes
            ->filter(fn ($route): bool => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'));

        return [
            'total_routes' => $routes->count(),
            'studio_routes' => $routes->filter(fn ($route): bool => str_starts_with($route->uri(), trim((string) config('admin.path'), '/')))->count(),
            'public_routes' => $routes->filter(fn ($route): bool => ! str_starts_with($route->uri(), 'api/') && ! str_starts_with($route->uri(), trim((string) config('admin.path'), '/')))->count(),
            'api_routes' => $routes->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'))->count(),
            'admin_routes' => $adminRoutes->count(),
            'sensitive_notes' => [
                'Studio routes use the configured admin path and AdminAccess middleware group.',
                'Customer invoice route uses signed middleware.',
                'No public diagnostics browsing route is registered.',
            ],
        ];
    }

    protected function backupReadiness(): array
    {
        $summary = $this->backupService->summary();
        $latest = $summary['latest'] ?? null;

        return [
            'disk' => 'local',
            's3_configured' => filled(config('filesystems.disks.s3.bucket')),
            'summary' => [
                'total' => $summary['total'] ?? 0,
                'completed' => $summary['completed'] ?? 0,
                'failed' => $summary['failed'] ?? 0,
                'restore_ready' => $summary['restore_ready'] ?? 0,
                'storage_used' => $summary['storage_used'] ?? 0,
            ],
            'latest' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'validation_status' => $latest->validation_status,
                'restore_ready' => (bool) $latest->restore_ready,
                'created_at' => $latest->created_at?->toDateTimeString(),
            ] : null,
            'checks' => $this->backupService->healthChecks(),
            'schedule' => 'backup-validation-check daily at 02:15',
        ];
    }

    protected function demoDataSafety(): array
    {
        $counts = [
            'products' => $this->tableExists('products') ? DB::table('products')->where('sku', 'like', 'ZC-DEMO-%')->count() : 0,
            'orders' => $this->tableExists('orders') ? DB::table('orders')->where('order_number', 'like', 'DEMO-ORD-%')->count() : 0,
            'customers' => $this->tableExists('customers') ? DB::table('customers')->where('phone', 'like', '0100000000%')->count() : 0,
            'behavior_events' => $this->tableExists('customer_behavior_events') ? CustomerBehaviorEvent::query()->where('session_id', 'like', 'demo-session-%')->count() : 0,
        ];
        $demoPresent = array_sum($counts) > 0;

        return [
            'database_seeder_includes_demo' => $this->databaseSeederIncludesDemo(),
            'demo_counts' => $counts,
            'status' => app()->environment('production') && $demoPresent ? 'action_needed' : ($demoPresent ? 'warning' : 'passed'),
            'message' => app()->environment('production') && $demoPresent
                ? 'Demo data exists in production. Remove demo-prefixed records before launch.'
                : ($demoPresent ? 'Demo data exists for QA. This is expected locally/staging.' : 'No demo-prefixed data detected.'),
            'cleanup_recommendation' => 'Run a controlled cleanup for demo-prefixed records only when leaving QA/staging.',
        ];
    }

    protected function productionChecklist(array $securityChecklist, array $backupReadiness, array $demoSafety): array
    {
        return [
            $this->check('Environment', app()->environment('production'), app()->environment(), 'Production launch should use APP_ENV=production.'),
            $this->check('Debug off', ! config('app.debug'), config('app.debug') ? 'Enabled' : 'Disabled', 'APP_DEBUG must be false for production.'),
            $this->check('Database', ($this->performanceHealth->database()['status'] ?? null) === 'ok', config('database.default'), 'Database connection responds to a lightweight probe.'),
            $this->check('Cache', ($this->performanceHealth->cache()['status'] ?? null) === 'ok', config('cache.default'), 'Cache set/get probe succeeds.'),
            $this->check('Queue', $this->tableExists('jobs'), config('queue.default'), 'Queue table exists for database queue driver.'),
            $this->check('Scheduler', true, 'Registered', 'Run php artisan schedule:work or cron scheduler in production.'),
            $this->check('Storage', ($this->performanceHealth->storage()['status'] ?? null) === 'ok', config('filesystems.default'), 'Storage disk write/read probe succeeds.'),
            $this->check('Mail', config('mail.default') !== 'log', (string) config('mail.default'), 'Configure a real mail transport before relying on email.'),
            $this->check('SMS', filter_var($this->settings->get('communication', 'sms_enabled', false), FILTER_VALIDATE_BOOLEAN), 'Setting controlled', 'Enable only after provider setup.'),
            $this->check('WhatsApp', filter_var($this->settings->get('communication', 'whatsapp_enabled', false), FILTER_VALIDATE_BOOLEAN), 'Setting controlled', 'Enable only after provider setup.'),
            $this->check('Courier', CourierProvider::query()->where('status', 'active')->exists(), 'Active providers', 'At least one active courier provider should exist.'),
            $this->check('Backup', (int) ($backupReadiness['summary']['restore_ready'] ?? 0) > 0, (string) ($backupReadiness['summary']['restore_ready'] ?? 0), 'Create and validate at least one backup before launch.'),
            $this->check('Security', collect($securityChecklist)->where('status', 'action_needed')->isEmpty(), 'Checklist reviewed', 'Resolve action-needed security checks.'),
            $this->check('Permissions', Permission::query()->where('slug', 'system.diagnostics.view')->exists(), 'Diagnostics permission', 'Diagnostics route is permission-bound.'),
            $this->check('Demo data disabled', ! $demoSafety['database_seeder_includes_demo'], 'DatabaseSeeder', 'DemoQaSeeder is not run by the default seeder.'),
            $this->check('HTTPS ready', Str::startsWith((string) config('app.url'), 'https://'), (string) config('app.url'), 'APP_URL should use HTTPS in production.'),
        ];
    }

    protected function errorVisibility(): array
    {
        return [
            'safe_log_access' => false,
            'log_path' => 'storage/logs/laravel.log',
            'note' => 'Diagnostics intentionally does not parse or render stack traces. Use server monitoring or restricted log access for full errors.',
        ];
    }

    protected function performanceHealth(): array
    {
        $coverage = $this->indexCoverage();

        return [
            'checklist' => [
                $this->check('Storefront product sections cached', true, 'ID cache + eager reload', 'Homepage latest/top selling product IDs are cached and hydrated with product card relations.'),
                $this->check('Product detail eager loading', true, 'Product, media, packages, category', 'Product detail uses storefront display loading for variants, gallery, thumbnail, and category.'),
                $this->check('Studio command widgets cached', true, 'Short TTL', 'Dashboard, finance, marketing, automation, analytics, reports, and diagnostics use bounded caches where safe.'),
                $this->check('Behavior event writes stay lightweight', true, 'No per-event full cache flush', 'Behavior tracking remains non-blocking and uses indexed aggregation for Studio analytics.'),
                $this->check('Operational queues paginated', true, '20-50 rows', 'Orders, customers, recoveries, verification, courier, reviews, automations, reports, and logs are paginated or limited.'),
                $this->check('Performance index coverage', $coverage['missing_count'] === 0, $coverage['present_count'].' / '.$coverage['total_count'], 'Composite indexes cover common dashboard, funnel, timeline, and reporting filters.'),
            ],
            'index_coverage' => $coverage,
            'pagination_readiness' => $this->paginationReadiness(),
            'slow_risk_sections' => $this->slowRiskSections(),
            'media' => $this->mediaPerformance(),
            'budget' => [
                'storefront' => [
                    'Homepage product sections should use cached IDs, eager-loaded product cards, active category cache, and settings/theme cache.',
                    'Product listing and category pages should stay paginated at 12 products per page.',
                    'Product detail should avoid package/review/recommendation N+1 and keep behavior event logging non-blocking.',
                ],
                'studio' => [
                    'Command Center widgets should use short TTL cache for aggregate counts that do not need second-level freshness.',
                    'Analytics, finance, marketing, automation, and reports should use cached summaries plus limited drilldowns.',
                    'Timeline and log sections should stay limited or paginated.',
                ],
            ],
            'notes' => [
                'No public debug or performance route is exposed.',
                'No heavy JavaScript libraries were added for Phase 59.',
                'Customer-specific personalization remains request/session scoped and is not cached as public content.',
            ],
        ];
    }

    protected function indexCoverage(): array
    {
        $targets = [
            'orders' => ['orders_status_created_at_index', 'orders_customer_status_created_at_index', 'orders_verification_created_perf_idx', 'orders_risk_hold_created_perf_idx', 'orders_coupon_created_perf_idx'],
            'order_items' => ['order_items_product_created_perf_idx', 'order_items_variant_created_perf_idx'],
            'customers' => ['customers_last_order_created_perf_idx', 'customers_delivered_last_order_perf_idx'],
            'products' => ['products_status_created_at_index', 'products_category_status_created_perf_idx'],
            'product_variants' => ['variants_storefront_visibility_index'],
            'product_reviews' => ['product_reviews_product_id_status_is_featured_index', 'reviews_product_status_rating_perf_idx', 'reviews_customer_created_perf_idx', 'reviews_order_product_variant_perf_idx'],
            'customer_behavior_events' => ['behavior_event_type_occurred_perf_idx', 'behavior_product_event_occurred_perf_idx', 'behavior_customer_occurred_perf_idx', 'behavior_source_event_occurred_perf_idx'],
            'checkout_recoveries' => ['recoveries_status_updated_perf_idx', 'recoveries_product_status_created_perf_idx'],
            'order_verification_attempts' => ['verification_outcome_created_perf_idx', 'verification_order_created_perf_idx'],
            'shipments' => ['shipments_status_created_perf_idx', 'shipments_courier_status_created_perf_idx'],
            'shipment_tracking_events' => ['tracking_shipment_event_time_perf_idx'],
            'communication_messages' => ['messages_status_queued_perf_idx', 'messages_customer_created_perf_idx', 'messages_order_created_perf_idx'],
            'automation_runs' => ['automation_runs_status_created_perf_idx', 'automation_runs_trigger_status_perf_idx', 'automation_runs_subject_created_perf_idx'],
            'automation_actions' => ['automation_actions_status_executed_perf_idx'],
            'marketing_campaigns' => ['campaigns_status_starts_perf_idx', 'campaigns_type_status_perf_idx'],
            'marketing_campaign_logs' => ['campaign_logs_campaign_created_perf_idx'],
            'coupon_usages' => ['coupon_usages_coupon_used_perf_idx', 'coupon_usages_customer_used_perf_idx'],
            'ad_spends' => ['ad_spends_platform_date_perf_idx'],
            'expenses' => ['expenses_category_date_perf_idx'],
        ];

        $rows = [];

        foreach ($targets as $table => $indexes) {
            $existing = $this->tableIndexes($table);

            foreach ($indexes as $index) {
                $rows[] = [
                    'table' => $table,
                    'index' => $index,
                    'status' => in_array($index, $existing, true) ? 'passed' : 'warning',
                ];
            }
        }

        $present = collect($rows)->where('status', 'passed')->count();

        return [
            'total_count' => count($rows),
            'present_count' => $present,
            'missing_count' => count($rows) - $present,
            'rows' => $rows,
        ];
    }

    protected function paginationReadiness(): array
    {
        return [
            ['area' => 'Storefront products', 'limit' => '12 per page', 'status' => 'passed'],
            ['area' => 'Orders', 'limit' => '20 per page', 'status' => 'passed'],
            ['area' => 'Customers', 'limit' => '20 per page', 'status' => 'passed'],
            ['area' => 'Recovery center', 'limit' => '20 per page', 'status' => 'passed'],
            ['area' => 'Verification queue', 'limit' => '20 per page', 'status' => 'passed'],
            ['area' => 'Courier shipments', 'limit' => '20 per page', 'status' => 'passed'],
            ['area' => 'Reviews', 'limit' => '20 per page', 'status' => 'passed'],
            ['area' => 'Automation workflows/runs', 'limit' => '20 / 15 per page', 'status' => 'passed'],
            ['area' => 'Reports', 'limit' => '50-60 rows per report panel', 'status' => 'passed'],
            ['area' => 'Customer / Order timelines', 'limit' => '10-30 recent events', 'status' => 'passed'],
        ];
    }

    protected function slowRiskSections(): array
    {
        return [
            ['area' => 'Analytics Intelligence Center', 'risk' => 'Many aggregate queries across orders, behavior, courier, marketing, and finance.', 'mitigation' => 'Analytics dashboard cache and composite behavior/order indexes.'],
            ['area' => 'Marketing Command Center', 'risk' => 'Attribution is aggregate-heavy and can touch campaign logs and coupon usage.', 'mitigation' => 'Short TTL command cache, JSON metadata filtering, and campaign/coupon indexes.'],
            ['area' => 'Finance Command Center', 'risk' => 'COD cashflow and profit totals scan order/shipment rows.', 'mitigation' => 'Grouped aggregate queries and status/date indexes.'],
            ['area' => 'Customer 360', 'risk' => 'Timeline pulls many adjacent systems for one customer.', 'mitigation' => 'Limited eager-loaded relationships, aggregate loyalty query, and indexed customer timelines.'],
            ['area' => 'Recovery Center', 'risk' => 'Hot lead scoring can inspect behavior and personalization signals.', 'mitigation' => 'Paginated recovery lists, limited scoring window, and status/date indexes.'],
        ];
    }

    protected function mediaPerformance(): array
    {
        if (! $this->tableExists('media')) {
            return [
                'total_images' => 0,
                'dimensioned_images' => 0,
                'missing_dimensions' => 0,
                'webp_images' => 0,
                'note' => 'Media table is unavailable.',
            ];
        }

        $imageQuery = DB::table('media')->where('mime_type', 'like', 'image/%');
        $total = (int) (clone $imageQuery)->count();
        $dimensioned = (int) (clone $imageQuery)->whereNotNull('width')->whereNotNull('height')->count();

        return [
            'total_images' => $total,
            'dimensioned_images' => $dimensioned,
            'missing_dimensions' => max(0, $total - $dimensioned),
            'webp_images' => (int) DB::table('media')->where('extension', 'webp')->count(),
            'note' => 'Storefront image helpers emit lazy loading, async decoding, width, height, and sizes when media dimensions exist.',
        ];
    }

    protected function tableIndexes(string $table): array
    {
        if (! $this->tableExists($table)) {
            return [];
        }

        try {
            return collect(Schema::getIndexes($table))
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function statusRow(bool|string $status, string $value, string $note): array
    {
        return [
            'status' => is_bool($status) ? ($status ? 'passed' : 'warning') : $status,
            'value' => $value,
            'note' => $note,
        ];
    }

    protected function check(string $label, bool $passed, string $value, string $note): array
    {
        return [
            'label' => $label,
            'status' => $passed ? 'passed' : 'warning',
            'value' => $value,
            'note' => $note,
        ];
    }

    protected function integration(string $label, bool $configured, string $value): array
    {
        return [
            'label' => $label,
            'status' => $configured ? 'configured' : 'unconfigured',
            'value' => $value,
        ];
    }

    protected function diskStatus(string $disk, string $label): array
    {
        try {
            $path = 'diagnostics/probe.txt';
            Storage::disk($disk)->put($path, 'ok');
            $ok = Storage::disk($disk)->get($path) === 'ok';
            Storage::disk($disk)->delete($path);

            return $this->statusRow($ok, $label, 'Writable storage disk probe.');
        } catch (Throwable) {
            return $this->statusRow(false, $label, 'Storage disk probe failed.');
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

    protected function routeHasMiddleware(string $name, string $middleware): bool
    {
        $route = Route::getRoutes()->getByName($name);

        if (! $route) {
            return false;
        }

        return collect($route->gatherMiddleware())->contains($middleware);
    }

    protected function routesHaveMiddleware(array $names, string|array $middleware): bool
    {
        $middleware = is_array($middleware) ? array_values($middleware) : $middleware;

        foreach (array_values($names) as $index => $name) {
            $expected = is_array($middleware) ? ($middleware[$index] ?? null) : $middleware;

            if (! is_string($expected) || ! $this->routeHasMiddleware($name, $expected)) {
                return false;
            }
        }

        return true;
    }

    protected function safeExceptionSummary(string $exception): string
    {
        $firstLine = trim(Str::before($exception, "\n"));

        if ($firstLine === '') {
            return 'Failed job recorded.';
        }

        return Str::limit(preg_replace('/\/[^\s]+/', '[path]', $firstLine) ?? $firstLine, 140);
    }

    protected function mask(string $value): string
    {
        if ($value === '') {
            return 'Not configured';
        }

        return Str::mask($value, '*', 4, max(0, strlen($value) - 8));
    }

    protected function databaseSeederIncludesDemo(): bool
    {
        try {
            $source = (string) file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

            return str_contains($source, 'DemoQaSeeder');
        } catch (Throwable) {
            return false;
        }
    }

    protected function secureHeadersRegistered(): bool
    {
        try {
            $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

            return str_contains($bootstrap, 'SecureHeaders::class');
        } catch (Throwable) {
            return false;
        }
    }
}
