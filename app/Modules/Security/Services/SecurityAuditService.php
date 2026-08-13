<?php

namespace App\Modules\Security\Services;

use App\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SecurityAuditService
{
    public function __construct(
        private SettingService $settingService,
        private FileSecurityService $fileSecurityService,
    ) {}

    public function checks(): array
    {
        return [
            'environment' => [
                $this->check(
                    'Application Debug',
                    ! (bool) config('app.debug'),
                    config('app.debug') ? 'Enabled' : 'Disabled',
                    'APP_DEBUG should be disabled in production.'
                ),
                $this->check(
                    'Application Environment',
                    app()->environment('production'),
                    app()->environment(),
                    'Production should use the production environment.'
                ),
                $this->check(
                    'HTTPS App URL',
                    Str::startsWith((string) config('app.url'), 'https://'),
                    (string) config('app.url'),
                    'Production APP_URL should use HTTPS.'
                ),
            ],
            'session' => [
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
                    'SameSite Session Cookie',
                    filled(config('session.same_site')),
                    (string) config('session.same_site'),
                    'SameSite helps reduce cross-site request risk.'
                ),
            ],
            'csrf' => [
                $this->check(
                    'Web CSRF Middleware',
                    $this->csrfMiddlewareEnabled(),
                    $this->csrfMiddlewareEnabled() ? 'Enabled' : 'Not detected',
                    'Laravel web routes should keep CSRF protection enabled.'
                ),
            ],
            'api_security' => [
                $this->check(
                    'Laravel Sanctum',
                    class_exists(\Laravel\Sanctum\Sanctum::class),
                    class_exists(\Laravel\Sanctum\Sanctum::class) ? 'Available' : 'Missing',
                    'API tokens are issued and revoked through Sanctum.'
                ),
                $this->check(
                    'Token Storage Table',
                    $this->tableExists('personal_access_tokens'),
                    $this->tableExists('personal_access_tokens') ? 'Ready' : 'Missing',
                    'Sanctum personal access token storage should exist.'
                ),
                $this->check(
                    'Customer API Protection',
                    $this->routeHasMiddleware('api.v1.customer.orders.index', 'auth:sanctum'),
                    $this->routeHasMiddleware('api.v1.customer.orders.index', 'auth:sanctum') ? 'Protected' : 'Not detected',
                    'Customer order API routes must require Sanctum authentication.'
                ),
                $this->check(
                    'Staff API Ability Protection',
                    $this->routeHasMiddleware('api.v1.auth.staff.me', 'abilities:staff'),
                    $this->routeHasMiddleware('api.v1.auth.staff.me', 'abilities:staff') ? 'Protected' : 'Not detected',
                    'Staff API profile routes must require staff token abilities.'
                ),
            ],
            'rate_limits' => [
                $this->checkLimiter('api-public', 'Public API'),
                $this->checkLimiter('api-customer', 'Customer API'),
                $this->checkLimiter('api-staff', 'Staff API'),
                $this->checkLimiter('checkout', 'Checkout / coupon preview'),
                $this->checkLimiter('otp-request', 'OTP request'),
                $this->checkLimiter('otp-verify', 'OTP verify'),
                $this->checkLimiter('tracking-lookup', 'Tracking lookup'),
                $this->checkLimiter('staff-login', 'Staff API login'),
                $this->checkLimiter('behavior-events', 'Behavior event capture'),
                $this->checkLimiter('cart-mutation', 'Cart mutations'),
                $this->checkLimiter('coupon-apply', 'Coupon attempts'),
                $this->checkLimiter('review-submit', 'Review submission'),
            ],
            'headers' => [
                $this->check(
                    'Secure Headers Middleware',
                    $this->secureHeadersRegistered(),
                    $this->secureHeadersRegistered() ? 'Registered in bootstrap' : 'Not detected',
                    'Adds frame, content-type, referrer, permissions-policy, and HTTPS HSTS headers.'
                ),
                $this->check(
                    'CSP Preparation',
                    true,
                    'Documented',
                    'A report-only or enforced CSP should be configured after storefront scripts are inventoried.'
                ),
            ],
            'uploads' => [
                $this->check(
                    'Media Upload Limit',
                    $this->fileSecurityService->maxKilobytes() <= 5120,
                    $this->fileSecurityService->maxKilobytes().' KB',
                    'Media uploads are capped at 5MB.'
                ),
                $this->check(
                    'Media Upload Extensions',
                    true,
                    implode(', ', $this->fileSecurityService->allowedExtensions()),
                    'Only approved image extensions are accepted.'
                ),
                $this->check(
                    'SVG Uploads',
                    $this->svgUploadsBlocked(),
                    $this->svgUploadsBlocked() ? 'Blocked' : 'Allowed',
                    'SVG files are blocked for production upload safety.'
                ),
            ],
            'fraud_protection' => [
                $this->check(
                    'Block Blacklisted Checkout',
                    true,
                    $this->flag('block_blacklisted_checkout') ? 'Enabled' : 'Disabled',
                    'When enabled, active blacklist entries are blocked before order creation.'
                ),
                $this->check(
                    'Critical Fraud Auto Reject',
                    true,
                    $this->flag('auto_reject_critical_fraud') ? 'Enabled' : 'Disabled',
                    'When enabled, critical fraud orders are cancelled after creation and analysis.'
                ),
                $this->check(
                    'Duplicate Order Block',
                    true,
                    $this->flag('duplicate_order_block_enabled') ? 'Enabled' : 'Disabled',
                    'Blocks matching phone, item, variant, and payable total within the cooldown window.'
                ),
                $this->check(
                    'Phone Checkout Cooldown',
                    true,
                    $this->flag('phone_checkout_cooldown_enabled') ? 'Enabled' : 'Disabled',
                    'Limits repeated checkout/order attempts from the same normalized phone.'
                ),
                $this->check(
                    'Coupon Abuse Lock',
                    true,
                    $this->flag('coupon_abuse_lock_enabled') ? 'Enabled' : 'Disabled',
                    'Locks coupon use after repeated invalid coupon attempts.'
                ),
            ],
            'anti_copy' => [
                $this->check(
                    'Public Anti-Copy',
                    true,
                    $this->flag('public_anti_copy_enabled') ? 'Enabled' : 'Disabled',
                    'Optional deterrent only. DevTools cannot be fully disabled.'
                ),
                $this->check(
                    'Right Click Deterrent',
                    true,
                    $this->flag('public_disable_right_click') ? 'Enabled' : 'Disabled',
                    'Applies only when public anti-copy is enabled.'
                ),
                $this->check(
                    'Text Selection Deterrent',
                    true,
                    $this->flag('public_disable_text_selection') ? 'Enabled' : 'Disabled',
                    'Form fields remain usable.'
                ),
                $this->check(
                    'Copy Shortcut Deterrent',
                    true,
                    $this->flag('public_disable_copy_shortcuts') ? 'Enabled' : 'Disabled',
                    'Form fields remain usable.'
                ),
                $this->check(
                    'DevTools Shortcut Deterrent',
                    true,
                    $this->flag('public_disable_devtool_shortcuts') ? 'Enabled' : 'Disabled',
                    'This cannot fully prevent inspection.'
                ),
            ],
            'admin_routes' => [
                $this->check(
                    '/admin Route Absence',
                    $this->adminRouteUris() === [],
                    $this->adminRouteUris() === [] ? 'No /admin routes detected' : implode(', ', $this->adminRouteUris()),
                    'Studio routes must remain on the configured admin path.'
                ),
            ],
        ];
    }

    public function flattenedChecks(): array
    {
        return collect($this->checks())
            ->flatMap(fn (array $checks) => $checks)
            ->values()
            ->all();
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

    protected function checkLimiter(string $name, string $label): array
    {
        $exists = RateLimiter::limiter($name) !== null;

        return $this->check(
            $label,
            $exists,
            $exists ? $name : 'Missing',
            'Named rate limiter: '.$name
        );
    }

    protected function csrfMiddlewareEnabled(): bool
    {
        $middleware = app('router')->getMiddlewareGroups()['web'] ?? [];

        return collect($middleware)->contains(function (string $middleware): bool {
            return str_contains($middleware, 'ValidateCsrfToken')
                || str_contains($middleware, 'VerifyCsrfToken')
                || str_contains($middleware, 'PreventRequestForgery');
        });
    }

    protected function adminRouteUris(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => $uri === 'admin' || str_starts_with($uri, 'admin/'))
            ->values()
            ->all();
    }

    protected function routeHasMiddleware(string $name, string $middleware): bool
    {
        $route = Route::getRoutes()->getByName($name);

        if (! $route) {
            return false;
        }

        return collect($route->gatherMiddleware())->contains($middleware);
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function flag(string $key): bool
    {
        return filter_var(
            $this->settingService->get('general', $key, false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    protected function svgUploadsBlocked(): bool
    {
        $extensions = array_map('strtolower', $this->fileSecurityService->allowedExtensions());
        $mimeTypes = array_map('strtolower', $this->fileSecurityService->allowedMimeTypes());

        return ! in_array('svg', $extensions, true)
            && ! collect($mimeTypes)->contains(fn (string $mimeType): bool => str_contains($mimeType, 'svg'));
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
