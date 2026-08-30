<?php

namespace App\Modules\License\Services;

use App\Modules\License\Models\LicenseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * License & Update CLIENT — talks to the central license server
 * (config('license.server')) to activate this installation and verify it
 * stays valid. Every response is checked against an RSA-2048/SHA-256
 * signature before it's ever trusted or written to storage — without that,
 * anyone with database access could set status='active' directly and
 * bypass the whole system. See docs/license-verification.md for the wire
 * contract this was built against.
 *
 * This is a HARD gate (unlike a soft "nag banner" license check): once a
 * blocking status is reached, LicenseGuard (app/Modules/Shared/Support)
 * stops the rest of the app from functioning. See that class and its call
 * sites for how enforcement is kept from being a single point of failure.
 */
class LicenseService
{
    public const ALLOWED_STATUSES = ['active', 'grace'];

    public function __construct() {}

    // --- Configuration -----------------------------------------------------

    public function server(): string
    {
        return rtrim((string) config('license.server', ''), '/');
    }

    public function productSlug(): string
    {
        return (string) config('license.product_slug', '');
    }

    public function domain(): string
    {
        return request()->getHost() ?: (string) parse_url(config('app.url', ''), PHP_URL_HOST);
    }

    protected function publicKey(): string
    {
        return (string) config('license.public_key_pem', '');
    }

    // --- Stored state --------------------------------------------------------

    public function licenseKey(): ?string
    {
        $encrypted = LicenseState::current()->license_key;

        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    public function maskedKey(): ?string
    {
        $key = $this->licenseKey();

        if (! $key) {
            return null;
        }

        $len = strlen($key);

        return $len <= 4 ? str_repeat('•', $len) : str_repeat('•', max(4, $len - 4)).substr($key, -4);
    }

    /** The domain the license server has this key bound to — distinct from domain(), which is this request's own host. */
    public function licensedDomain(): ?string
    {
        return LicenseState::current()->domain;
    }

    public function activatedAt(): ?Carbon
    {
        return LicenseState::current()->activated_at;
    }

    public function daysUntilExpiry(): ?int
    {
        $expiresAt = LicenseState::current()->expires_at;

        if (! $expiresAt) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);
    }

    // --- The effective, enforced state --------------------------------------

    /**
     * The single entry point everything else (middleware, LicenseGuard,
     * the /license/status endpoint) reads. Triggers a fresh verify() when
     * the cache is stale; on network/signature failure it falls back to the
     * last signature-verified status for up to `license.offline_trust_days`
     * so a brief outage never blocks a live store — past that window it
     * fails closed (blocked), not open.
     *
     * @return array{status: string, blocked: bool, message: string, expires_at: ?string, has_key: bool}
     */
    public function getEffectiveStatus(): array
    {
        // The hundreds of unrelated feature tests across the app should not
        // need to know licensing exists. Only LicenseVerificationTest (which
        // tests this gate itself) opts back into real enforcement.
        if (app()->runningUnitTests() && ! app()->bound('license.enforce_in_tests')) {
            return $this->result('active', false, 'License enforcement bypassed for automated tests.');
        }

        $state = LicenseState::current();

        if (! $state->license_key) {
            return $this->result('unactivated', true, 'No license key has been activated on this installation yet.');
        }

        $staleAfter = (int) config('license.verify_cache_minutes', 15);
        $isStale = ! $state->last_checked_at || $state->last_checked_at->lt(now()->subMinutes($staleAfter));

        // last_checked_at only ever advances on a SUCCESSFUL check (see
        // markCheckFailed) so the offline-trust window below means what it
        // says. That also means a prolonged outage would otherwise get
        // retried on every single request; this short cache stops that
        // without touching the timestamp that matters for trust.
        $recentlyFailed = Cache::get('license:last_attempt_at') > now()->subMinutes(5)->timestamp;

        if ($isStale && ! $recentlyFailed) {
            $this->verify();
            $state = LicenseState::current();
        }

        return $this->stateToResult($state);
    }

    protected function stateToResult(LicenseState $state): array
    {
        if (! $state->license_key) {
            return $this->result('unactivated', true, 'No license key has been activated on this installation yet.');
        }

        // The most recent check itself failed (network/signature) — trust
        // the last GOOD status for a limited offline window instead of
        // immediately blocking on a transient failure.
        if (! $state->last_check_ok) {
            $trustUntil = $state->last_checked_at?->copy()->addDays((int) config('license.offline_trust_days', 5));

            if ($state->status && $trustUntil && $trustUntil->isFuture() && in_array($state->status, self::ALLOWED_STATUSES, true)) {
                return $this->result($state->status, false, $state->message ?: 'Running on a cached license check — the license server was unreachable at the last attempt.', $state->expires_at);
            }

            return $this->result('unreachable', true, $state->message ?: 'Could not verify the license and no recent valid check is on record.', $state->expires_at);
        }

        $blocked = ! in_array($state->status, self::ALLOWED_STATUSES, true);

        return $this->result((string) $state->status, $blocked, (string) $state->message, $state->expires_at);
    }

    protected function result(string $status, bool $blocked, string $message, ?Carbon $expiresAt = null): array
    {
        $state = LicenseState::current();

        return [
            'status' => $status,
            'blocked' => $blocked,
            'message' => $message,
            'expires_at' => $expiresAt?->toIso8601String(),
            'has_key' => (bool) $state->license_key,
            'licensed_domain' => $state->domain,
            'activated_at' => $state->activated_at?->toIso8601String(),
        ];
    }

    // --- Talking to the license server --------------------------------------

    /** @return array{ok: bool, message: string} */
    public function activate(string $key): array
    {
        $key = trim($key);

        if ($key === '') {
            return ['ok' => false, 'message' => 'Enter a license key.'];
        }

        try {
            $response = Http::timeout(12)->acceptJson()->post($this->server().'/license/activate', [
                'license_key' => $key,
                'domain' => $this->domain(),
                'product_slug' => $this->productSlug(),
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Could not reach the license server: '.$exception->getMessage()];
        }

        $data = is_array($response->json()) ? $response->json() : [];
        $status = (string) ($data['status'] ?? '');

        if (! $response->successful() || ! in_array($status, ['activated', 'already_active'], true)) {
            return ['ok' => false, 'message' => (string) ($data['message'] ?? 'Activation failed — check the key and try again.')];
        }

        if (! $this->verifySignature($data)) {
            return ['ok' => false, 'message' => 'The license server\'s response could not be verified (signature mismatch) — activation was not saved.'];
        }

        $state = LicenseState::current();
        $state->license_key = Crypt::encryptString($key);
        $state->domain = (string) ($data['domain'] ?? $this->domain());
        $state->activated_at = $this->parseDate($data['activated_at'] ?? null) ?? now();
        $state->status = 'active';
        $state->expires_at = $this->parseDate($data['expires_at'] ?? null);
        $state->message = (string) ($data['message'] ?? '');
        $state->signature = (string) ($data['signature'] ?? '');
        $state->last_checked_at = now();
        $state->last_check_ok = true;
        $state->save();

        return ['ok' => true, 'message' => (string) ($data['message'] ?? 'License activated.')];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function verify(bool $force = false): array
    {
        $state = LicenseState::current();
        $key = $this->licenseKey();

        if (! $key) {
            return ['ok' => false, 'message' => 'No license key to verify.'];
        }

        $staleAfter = (int) config('license.verify_cache_minutes', 15);
        if (! $force && $state->last_checked_at && $state->last_checked_at->gt(now()->subMinutes($staleAfter))) {
            return ['ok' => true, 'message' => 'Using the cached license status.'];
        }

        try {
            $response = Http::timeout(10)->acceptJson()->post($this->server().'/license/verify', [
                'license_key' => $key,
                'domain' => $this->domain(),
            ]);
        } catch (Throwable $exception) {
            $this->markCheckFailed($exception->getMessage());

            return ['ok' => false, 'message' => 'License server unreachable — using last known status.'];
        }

        $data = is_array($response->json()) ? $response->json() : [];
        $status = (string) ($data['status'] ?? '');

        $recognised = ['active', 'grace', 'expired', 'suspended', 'revoked', 'denied'];
        if (! $status || ! in_array($status, $recognised, true)) {
            $this->markCheckFailed('Unrecognised response from license server.');

            return ['ok' => false, 'message' => 'License server returned an unexpected response — using last known status.'];
        }

        if (! $this->verifySignature($data)) {
            $this->markCheckFailed('Signature verification failed.');

            return ['ok' => false, 'message' => 'The license server\'s response could not be verified — using last known status.'];
        }

        $state->status = $status;
        if (! empty($data['domain'])) {
            $state->domain = (string) $data['domain'];
        }
        if (! empty($data['activated_at'])) {
            $state->activated_at = $this->parseDate($data['activated_at']);
        }
        $state->expires_at = $this->parseDate($data['expires_at'] ?? null);
        $state->message = (string) ($data['message'] ?? '');
        $state->signature = (string) ($data['signature'] ?? '');
        $state->last_checked_at = now();
        $state->last_check_ok = true;
        $state->save();

        return ['ok' => true, 'message' => (string) ($data['message'] ?? 'License status refreshed.')];
    }

    protected function markCheckFailed(string $reason): void
    {
        // Deliberately does NOT touch last_checked_at — it must keep
        // reflecting the last SUCCESSFUL check, since the offline-trust
        // window in stateToResult() is measured from it. Advancing it on
        // every failed attempt would let a prolonged outage silently
        // extend the trust window forever instead of expiring it.
        $state = LicenseState::current();
        $state->last_check_ok = false;
        $state->message = $reason;
        $state->save();

        Cache::put('license:last_attempt_at', now()->timestamp, now()->addMinutes(5));
    }

    /**
     * "{status}|{license_key}|{domain}|{expires_at}" — a plain string, not a
     * re-serialization of the JSON body (field order/whitespace in JSON
     * isn't stable enough to sign against). Missing fields become "".
     */
    protected function verifySignature(array $data): bool
    {
        $publicKey = $this->publicKey();
        $signature = (string) ($data['signature'] ?? '');

        if ($publicKey === '' || $signature === '') {
            return false;
        }

        $canonical = implode('|', [
            (string) ($data['status'] ?? ''),
            (string) ($data['license_key'] ?? ''),
            (string) ($data['domain'] ?? ''),
            (string) ($data['expires_at'] ?? ''),
        ]);

        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            return false;
        }

        return openssl_verify($canonical, $decoded, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
