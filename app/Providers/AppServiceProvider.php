<?php

namespace App\Providers;

use App\Modules\Shared\Services\PhoneService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->validateQueuePriorityConfig();

        $rateKey = static function (string $scope, mixed $value): string {
            return $scope.':'.hash_hmac('sha256', (string) $value, (string) config('app.key'));
        };

        $sessionOrIp = static function (Request $request): string {
            return $request->hasSession() ? $request->session()->getId() : (string) $request->ip();
        };

        $normalizedPhone = function (Request $request): string {
            return app(PhoneService::class)->normalize((string) $request->input('phone')) ?: $request->ip();
        };

        RateLimiter::for('api-public', function (Request $request) use ($rateKey) {
            return Limit::perMinute(60)->by($rateKey('api-public-ip', $request->ip()));
        });

        RateLimiter::for('api-customer', function (Request $request) use ($rateKey) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $rateKey('api-customer-ip', $request->ip()));
        });

        RateLimiter::for('api-staff', function (Request $request) use ($rateKey) {
            return Limit::perMinute(300)->by($request->user()?->getAuthIdentifier() ?: $rateKey('api-staff-ip', $request->ip()));
        });

        RateLimiter::for('checkout', function (Request $request) use ($rateKey) {
            return Limit::perMinute(30)->by($rateKey('checkout-ip', $request->ip()));
        });

        RateLimiter::for('otp-request', function (Request $request) use ($normalizedPhone, $rateKey) {
            return [
                Limit::perMinute(5)->by($rateKey('otp-request-phone', $normalizedPhone($request))),
                Limit::perMinute(20)->by($rateKey('otp-request-ip', $request->ip())),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) use ($normalizedPhone, $rateKey) {
            return [
                Limit::perMinute(10)->by($rateKey('otp-verify-phone', $normalizedPhone($request))),
                Limit::perMinute(30)->by($rateKey('otp-verify-ip', $request->ip())),
            ];
        });

        RateLimiter::for('tracking-lookup', function (Request $request) use ($normalizedPhone, $rateKey) {
            return [
                Limit::perMinute(15)->by($rateKey('tracking-phone', $normalizedPhone($request))),
                Limit::perMinute(30)->by($rateKey('tracking-ip', $request->ip())),
            ];
        });

        RateLimiter::for('staff-login', function (Request $request) use ($rateKey) {
            return [
                Limit::perMinute(10)->by($rateKey('staff-login-email', strtolower((string) $request->input('email', $request->ip())))),
                Limit::perMinute(30)->by($rateKey('staff-login-ip', $request->ip())),
            ];
        });

        RateLimiter::for('behavior-events', function (Request $request) use ($rateKey, $sessionOrIp) {
            return [
                Limit::perMinute(120)->by($rateKey('behavior-events-session', $sessionOrIp($request))),
                Limit::perMinute(240)->by($rateKey('behavior-events-ip', $request->ip())),
            ];
        });

        RateLimiter::for('cart-mutation', function (Request $request) use ($rateKey, $sessionOrIp) {
            return [
                Limit::perMinute(60)->by($rateKey('cart-mutation-session', $sessionOrIp($request))),
                Limit::perMinute(120)->by($rateKey('cart-mutation-ip', $request->ip())),
            ];
        });

        RateLimiter::for('coupon-apply', function (Request $request) use ($rateKey, $sessionOrIp) {
            return [
                Limit::perMinute(20)->by($rateKey('coupon-apply-session', $sessionOrIp($request))),
                Limit::perMinute(60)->by($rateKey('coupon-apply-ip', $request->ip())),
            ];
        });

        RateLimiter::for('review-submit', function (Request $request) use ($rateKey) {
            $customerKey = $request->hasSession() ? $request->session()->get('customer_id') : null;

            return [
                Limit::perMinute(10)->by($rateKey('review-submit-customer', $customerKey ?: $request->ip())),
                Limit::perMinute(30)->by($rateKey('review-submit-ip', $request->ip())),
            ];
        });

        $this->shareStorefrontLayoutData();
    }

    /**
     * The storefront layout (header logo, categories, footer) needs theme data
     * on EVERY page — but only StorefrontController::viewData() used to supply
     * it, so pages rendered by checkout/tracking/auth controllers showed the
     * fallback mark instead of the uploaded logo. Share it via a composer so
     * the same logo appears on every page (only filling what a controller
     * didn't already pass).
     */
    protected function shareStorefrontLayoutData(): void
    {
        // Store name + slogan for EVERY storefront view (incl. child-view page
        // titles). Nothing is hardcoded to a brand: it resolves brand_name →
        // general.site_name → the current domain (so a fresh deploy on any
        // domain shows that domain's name until an owner sets a brand). This is
        // what makes the codebase reusable for any store, not just this one.
        \Illuminate\Support\Facades\View::composer(['layouts.app', 'storefront.*'], function ($view): void {
            $data = $view->getData();
            if (! array_key_exists('storeName', $data) || ! array_key_exists('brandSlogan', $data)) {
                $theme = app(\App\Modules\Theme\Services\ThemeService::class);
                $settings = app(\App\Modules\Settings\Services\SettingService::class);

                if (! array_key_exists('storeName', $data)) {
                    $host = \Illuminate\Support\Str::of((string) (request()?->getHost() ?? ''))
                        ->replaceFirst('www.', '')->before('.')->headline()->toString();
                    $view->with('storeName', $theme->get('brand_name')
                        ?: ($settings->get('general', 'site_name') ?: ($host !== '' ? $host : 'Store')));
                }
                if (! array_key_exists('brandSlogan', $data)) {
                    $view->with('brandSlogan', $theme->get('brand_slogan', 'Quality you can trust'));
                }
            }
        });

        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view): void {
            $data = $view->getData();
            $theme = app(\App\Modules\Theme\Services\ThemeService::class);
            $shared = [];

            if (! array_key_exists('themeSettings', $data)) {
                $shared['themeSettings'] = $theme->settings();
            }
            if (! array_key_exists('themeMediaUrl', $data)) {
                $shared['themeMediaUrl'] = fn (string $key): ?string => $theme->mediaUrl($key);
            }
            if (! array_key_exists('mediaUrl', $data)) {
                $media = app(\App\Modules\Media\Services\MediaService::class);
                $shared['mediaUrl'] = fn ($m): ?string => $m ? $media->url($m) : null;
            }
            if (! array_key_exists('activeCategories', $data)) {
                $shared['activeCategories'] = app(\App\Modules\Storefront\Services\StorefrontService::class)->activeCategories();
            }
            if (! array_key_exists('cmsFooterPages', $data)) {
                $shared['cmsFooterPages'] = app(\App\Modules\Storefront\Services\StorefrontContentService::class)->footerPages();
            }

            if ($shared) {
                $view->with($shared);
            }
        });
    }

    /**
     * A blank/missing queue name in config/queue_priorities.php means jobs
     * dispatch to a queue literally nobody listens on — no error anywhere,
     * just messages (including OTP) that never send. This is the exact
     * silent-failure pattern Phases A/A.1/A.2 exist to eliminate, so it
     * fails the whole boot cycle rather than letting the app run in a
     * state where that can happen unnoticed. A mismatch between a
     * non-empty custom queue name and what the shipped worker configs
     * actually listen on is a softer problem (the ops team may have
     * updated their live supervisor/systemd config to match, even if the
     * checked-in deploy/ files don't reflect it) — that case is
     * deliberately NOT a boot failure; it's surfaced in Studio diagnostics
     * instead (SystemDiagnosticsService::queueWorkerConfigDrift()).
     */
    protected function validateQueuePriorityConfig(): void
    {
        foreach (['otp', 'transactional', 'bulk'] as $tier) {
            $queueName = config("queue_priorities.{$tier}");

            if (! is_string($queueName) || trim($queueName) === '') {
                throw new RuntimeException(
                    "config/queue_priorities.php: the '{$tier}' tier resolves to an empty queue name. ".
                    'Check QUEUE_NAME_'.strtoupper($tier).' in .env — a blank queue name means jobs '.
                    'silently dispatch to a queue no worker consumes, including OTP if the otp tier is '.
                    'affected. Refusing to boot until this is fixed.'
                );
            }
        }
    }
}
