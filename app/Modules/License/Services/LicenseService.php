<?php

namespace App\Modules\License\Services;

use App\Modules\Settings\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * License & Update CLIENT.
 *
 * This is the thin half that lives inside the sold script (Zenna Craft, and any
 * future tool that drops this module in). It talks to your central License +
 * Update panel over a small HTTP API to activate the installation, validate it
 * periodically, and check for new versions.
 *
 * Design rules that keep a LIVE store safe:
 *  - Dormant by default: if config('license.server') is empty, nothing phones
 *    home and the app has full access (status "unlicensed" = developer mode).
 *  - Never hard-stops the storefront. An expired/invalid licence only shows a
 *    banner and blocks UPDATES; the store keeps selling.
 *  - Fault tolerant: if the panel is unreachable we keep the last good status
 *    for `grace_days`, so an outage or a network blip is invisible to shoppers.
 *
 * The panel API this expects (documented here so the panel implements the same
 * contract):
 *   POST {server}/api/v1/activate  {product,key,domain} -> {status,expires_at,message}
 *   POST {server}/api/v1/validate  {product,key,domain} -> {status,expires_at,latest_version,message}
 *   GET  {server}/api/v1/version   ?product&current&key&domain -> {latest_version,changelog,mandatory,...}
 * where status is one of: active | expired | invalid.
 */
class LicenseService
{
    private const GROUP = 'license';

    public function __construct(private SettingService $settings) {}

    // --- Configuration (read-only, from config/license.php) --------------

    public function isConfigured(): bool
    {
        return $this->server() !== '';
    }

    public function server(): string
    {
        return rtrim((string) config('license.server', ''), '/');
    }

    public function product(): string
    {
        return (string) config('license.product', 'app');
    }

    public function currentVersion(): string
    {
        return (string) config('license.version', '1.0.0');
    }

    public function graceDays(): int
    {
        return max(0, (int) config('license.grace_days', 7));
    }

    public function domain(): string
    {
        return request()->getHost() ?: 'localhost';
    }

    // --- Stored license key + preferences --------------------------------

    public function key(): ?string
    {
        $key = $this->settings->getEncrypted(self::GROUP, 'key');

        return $key ? (string) $key : null;
    }

    public function autoUpdate(): bool
    {
        return filter_var($this->settings->get(self::GROUP, 'auto_update', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setAutoUpdate(bool $on): void
    {
        $this->settings->set(self::GROUP, 'auto_update', $on ? '1' : '0', 'boolean');
    }

    public function expiresAt(): ?Carbon
    {
        $v = $this->settings->get(self::GROUP, 'expires_at');

        return $v ? Carbon::parse((string) $v) : null;
    }

    public function checkedAt(): ?Carbon
    {
        $v = $this->settings->get(self::GROUP, 'checked_at');

        return $v ? Carbon::parse((string) $v) : null;
    }

    // --- The effective, human-facing state -------------------------------

    /**
     * The status the UI + any gate reads. It NEVER hard-stops the app:
     *  - unlicensed : panel not set up OR no key entered -> full access (dev)
     *  - active     : validated and not expired
     *  - grace      : panel currently unreachable, but last check was good and
     *                 still inside the grace window
     *  - expired    : past expiry (updates blocked, banner shown; store runs)
     *  - invalid    : panel rejected the key
     */
    public function effectiveStatus(): string
    {
        if (! $this->isConfigured() || ! $this->key()) {
            return 'unlicensed';
        }

        $status = (string) $this->settings->get(self::GROUP, 'status', 'unknown');

        if ($status === 'invalid') {
            return 'invalid';
        }

        $expiresAt = $this->expiresAt();
        if ($expiresAt && $expiresAt->isPast()) {
            return 'expired';
        }

        if ($status === 'active') {
            return 'active';
        }

        // Status unknown / last refresh was unreachable: honour the grace window
        // measured from the last successful check.
        $checkedAt = $this->checkedAt();
        if ($checkedAt && $checkedAt->copy()->addDays($this->graceDays())->isFuture()) {
            return 'grace';
        }

        return 'expired';
    }

    public function state(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'server' => $this->server(),
            'product' => $this->product(),
            'domain' => $this->domain(),
            'has_key' => (bool) $this->key(),
            'key_masked' => $this->maskKey(),
            'status' => $this->effectiveStatus(),
            'expires_at' => optional($this->expiresAt())->toDateString(),
            'checked_at' => optional($this->checkedAt())->toDateTimeString(),
            'current_version' => $this->currentVersion(),
            'latest_version' => (string) $this->settings->get(self::GROUP, 'latest_version', $this->currentVersion()),
            'auto_update' => $this->autoUpdate(),
            'message' => (string) $this->settings->get(self::GROUP, 'message', ''),
            'grace_days' => $this->graceDays(),
        ];
    }

    // --- Talking to the panel --------------------------------------------

    public function activate(string $key): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'License server is not configured yet. Set LICENSE_SERVER_URL first.'];
        }

        try {
            $res = Http::timeout(12)->acceptJson()->post($this->server().'/api/v1/activate', [
                'product' => $this->product(),
                'key' => $key,
                'domain' => $this->domain(),
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Could not reach the license server. Please try again.'];
        }

        $data = is_array($res->json()) ? $res->json() : [];

        if ($res->successful() && ($data['status'] ?? '') === 'active') {
            $this->settings->setEncrypted(self::GROUP, 'key', $key);
            $this->store($data);

            return ['ok' => true, 'message' => $data['message'] ?? 'License activated.'];
        }

        return ['ok' => false, 'message' => $data['message'] ?? 'Activation failed. Check the key and try again.'];
    }

    public function refresh(): array
    {
        if (! $this->isConfigured() || ! $this->key()) {
            return ['ok' => false, 'message' => 'No license to validate.'];
        }

        try {
            $res = Http::timeout(10)->acceptJson()->post($this->server().'/api/v1/validate', [
                'product' => $this->product(),
                'key' => $this->key(),
                'domain' => $this->domain(),
            ]);
        } catch (Throwable) {
            // Unreachable -> keep the cached state; the grace window covers it.
            return ['ok' => false, 'message' => 'License server unreachable — using last known status.'];
        }

        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'License server error — using last known status.'];
        }

        $this->store(is_array($res->json()) ? $res->json() : []);

        return ['ok' => true, 'message' => 'License refreshed.'];
    }

    public function checkUpdate(): array
    {
        $current = $this->currentVersion();

        if (! $this->isConfigured()) {
            return ['ok' => false, 'current' => $current, 'latest' => $current, 'has_update' => false, 'changelog' => '', 'mandatory' => false, 'message' => 'License server is not configured yet.'];
        }

        try {
            $res = Http::timeout(10)->acceptJson()->get($this->server().'/api/v1/version', [
                'product' => $this->product(),
                'current' => $current,
                'key' => $this->key(),
                'domain' => $this->domain(),
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'current' => $current, 'latest' => $current, 'has_update' => false, 'changelog' => '', 'mandatory' => false, 'message' => 'Could not reach the update server.'];
        }

        $data = is_array($res->json()) ? $res->json() : [];
        $latest = (string) ($data['latest_version'] ?? $current);

        if ($res->successful()) {
            $this->settings->set(self::GROUP, 'latest_version', $latest, 'string');
        }

        return [
            'ok' => $res->successful(),
            'current' => $current,
            'latest' => $latest,
            'has_update' => version_compare($latest, $current, '>'),
            'changelog' => (string) ($data['changelog'] ?? ''),
            'mandatory' => (bool) ($data['mandatory'] ?? false),
            'message' => (string) ($data['message'] ?? ''),
        ];
    }

    public function deactivate(): void
    {
        foreach (['key', 'status', 'expires_at', 'checked_at', 'latest_version', 'message'] as $k) {
            $this->settings->remove(self::GROUP, $k);
        }
    }

    // --- internals -------------------------------------------------------

    /** Persist the fields the panel returned + stamp the check time. */
    private function store(array $data): void
    {
        if (isset($data['status'])) {
            $this->settings->set(self::GROUP, 'status', (string) $data['status'], 'string');
        }
        if (array_key_exists('expires_at', $data)) {
            $this->settings->set(self::GROUP, 'expires_at', $data['expires_at'] ? (string) $data['expires_at'] : '', 'string');
        }
        if (isset($data['latest_version'])) {
            $this->settings->set(self::GROUP, 'latest_version', (string) $data['latest_version'], 'string');
        }
        $this->settings->set(self::GROUP, 'message', (string) ($data['message'] ?? ''), 'string');
        $this->settings->set(self::GROUP, 'checked_at', now()->toDateTimeString(), 'string');
    }

    private function maskKey(): ?string
    {
        $key = $this->key();
        if (! $key) {
            return null;
        }

        $len = strlen($key);

        return $len <= 4
            ? str_repeat('•', $len)
            : str_repeat('•', max(4, $len - 4)).substr($key, -4);
    }
}
