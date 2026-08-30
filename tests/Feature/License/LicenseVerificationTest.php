<?php

namespace Tests\Feature\License;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\License\Models\LicenseState;
use App\Modules\License\Services\LicenseService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\Shared\Support\OperationalStanding;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private mixed $privateKey;

    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        // This is the one suite that actually exercises the gate, so opt
        // back into real enforcement (LicenseService::getEffectiveStatus
        // bypasses it by default under phpunit for every other test).
        app()->instance('license.enforce_in_tests', true);

        // A fresh keypair per test run — proves the verifier actually checks
        // the signature against config('license.public_key_pem') rather than
        // trusting anything, since a response signed with a DIFFERENT key
        // (see the tampering test) must be rejected.
        $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($this->privateKey);
        $this->publicKeyPem = $details['key'];

        config([
            'license.server' => 'https://license.test/api/v1',
            'license.product_slug' => 'ecommerce1',
            'license.public_key_pem' => $this->publicKeyPem,
            'license.verify_cache_minutes' => 360,
            'license.offline_trust_days' => 5,
        ]);
    }

    private function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Owner', 'email' => 'lic-owner@zennacraft.test', 'password' => bcrypt('x'), 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function service(): LicenseService
    {
        return app(LicenseService::class);
    }

    /** Signs with THIS test's key by default; pass a different key to simulate a forged/mismatched signature. */
    private function sign(array $data, mixed $key = null): string
    {
        $canonical = implode('|', [
            $data['status'] ?? '', $data['license_key'] ?? '', $data['domain'] ?? '', $data['expires_at'] ?? '',
        ]);
        openssl_sign($canonical, $signature, $key ?? $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function signedResponse(array $data, mixed $key = null): array
    {
        return $data + ['signature' => $this->sign($data, $key)];
    }

    // --- activate() ---

    public function test_activate_stores_the_key_when_the_signature_is_valid(): void
    {
        $payload = ['status' => 'activated', 'license_key' => 'ZC-TEST-0001', 'domain' => 'localhost', 'expires_at' => now()->addYear()->toIso8601String(), 'message' => 'Activated'];
        Http::fake(['*/license/activate' => Http::response($this->signedResponse($payload))]);

        $result = $this->service()->activate('ZC-TEST-0001');

        $this->assertTrue($result['ok']);
        $this->assertSame('ZC-TEST-0001', $this->service()->licenseKey());
        $this->assertSame('active', $this->service()->getEffectiveStatus()['status']);
        $this->assertFalse($this->service()->getEffectiveStatus()['blocked']);
    }

    public function test_activate_stores_domain_and_activated_at_from_the_server_response(): void
    {
        $activatedAt = now()->subMinutes(5)->toIso8601String();
        $payload = ['status' => 'activated', 'license_key' => 'ZC-TEST-0001', 'domain' => 'zennacraft.com', 'activated_at' => $activatedAt, 'expires_at' => now()->addYear()->toIso8601String()];
        Http::fake(['*/license/activate' => Http::response($this->signedResponse($payload))]);

        $this->service()->activate('ZC-TEST-0001');
        $status = $this->service()->getEffectiveStatus();

        $this->assertSame('zennacraft.com', $status['licensed_domain']);
        $this->assertNotNull($status['activated_at']);
        $this->assertTrue(\Illuminate\Support\Carbon::parse($status['activated_at'])->equalTo(\Illuminate\Support\Carbon::parse($activatedAt)));
    }

    public function test_activate_rejects_a_response_with_an_invalid_signature(): void
    {
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $payload = ['status' => 'activated', 'license_key' => 'ZC-TEST-0002', 'domain' => 'localhost', 'expires_at' => now()->addYear()->toIso8601String()];
        // Signed with a DIFFERENT private key than the one whose public key is configured.
        Http::fake(['*/license/activate' => Http::response($this->signedResponse($payload, $otherKey))]);

        $result = $this->service()->activate('ZC-TEST-0002');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('signature', $result['message']);
        $this->assertNull($this->service()->licenseKey());
    }

    public function test_activate_denied_does_not_store_a_key(): void
    {
        Http::fake(['*/license/activate' => Http::response(['status' => 'denied', 'message' => 'Key not found'], 404)]);

        $result = $this->service()->activate('BOGUS-KEY');

        $this->assertFalse($result['ok']);
        $this->assertNull($this->service()->licenseKey());
    }

    // --- verify() / getEffectiveStatus() ---

    public function test_expired_license_is_blocked(): void
    {
        $this->activateActive();
        $payload = ['status' => 'expired', 'license_key' => 'ZC-TEST-0001', 'domain' => 'localhost', 'expires_at' => now()->subDay()->toIso8601String(), 'message' => 'Expired'];
        Http::fake(['*/license/verify' => Http::response($this->signedResponse($payload))]);

        $result = $this->service()->verify(force: true);
        $status = $this->service()->getEffectiveStatus();

        $this->assertTrue($result['ok']);
        $this->assertSame('expired', $status['status']);
        $this->assertTrue($status['blocked']);
    }

    public function test_grace_status_is_not_blocked(): void
    {
        $this->activateActive();
        $payload = ['status' => 'grace', 'license_key' => 'ZC-TEST-0001', 'domain' => 'localhost', 'expires_at' => now()->subDay()->toIso8601String(), 'message' => 'In grace period'];
        Http::fake(['*/license/verify' => Http::response($this->signedResponse($payload))]);

        $this->service()->verify(force: true);
        $status = $this->service()->getEffectiveStatus();

        $this->assertSame('grace', $status['status']);
        $this->assertFalse($status['blocked']);
    }

    public function test_verify_within_the_cache_window_does_not_call_the_server_again(): void
    {
        $this->activateActive();
        Http::fake();

        $this->service()->getEffectiveStatus();

        Http::assertNothingSent();
    }

    public function test_force_verify_bypasses_the_cache(): void
    {
        $this->activateActive();
        $payload = ['status' => 'active', 'license_key' => 'ZC-TEST-0001', 'domain' => 'localhost', 'expires_at' => now()->addMonth()->toIso8601String()];
        Http::fake(['*/license/verify' => Http::response($this->signedResponse($payload))]);

        $this->service()->verify(force: true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/license/verify'));
    }

    public function test_unreachable_server_keeps_the_last_good_status_within_the_offline_trust_window(): void
    {
        $this->activateActive();
        Http::fake(['*/license/verify' => fn () => throw new ConnectionException('down')]);

        $result = $this->service()->verify(force: true);
        $status = $this->service()->getEffectiveStatus();

        $this->assertFalse($result['ok']);
        $this->assertSame('active', $status['status']);
        $this->assertFalse($status['blocked']);
    }

    public function test_stale_offline_status_blocks_after_the_trust_window_expires(): void
    {
        $this->activateActive();
        $state = LicenseState::current();
        $state->last_checked_at = now()->subDays(6); // offline_trust_days = 5
        $state->last_check_ok = false;
        $state->save();

        $status = $this->service()->getEffectiveStatus();

        $this->assertTrue($status['blocked']);
    }

    public function test_no_key_activated_is_blocked(): void
    {
        $status = $this->service()->getEffectiveStatus();

        $this->assertSame('unactivated', $status['status']);
        $this->assertTrue($status['blocked']);
    }

    // --- HTTP-level gate ---

    public function test_blocked_installation_is_redirected_to_the_verification_page(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->get(route('studio.dashboard'))
            ->assertRedirect(route('license.verification'));
    }

    public function test_active_installation_reaches_the_dashboard(): void
    {
        $this->activateActive();
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->get(route('studio.dashboard'))->assertOk();
    }

    public function test_verification_page_itself_stays_reachable_while_blocked(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->get(route('license.verification'))->assertOk();
        $this->actingAs($owner, 'staff')->getJson(route('license.status'))->assertOk();
    }

    public function test_recheck_is_rate_limited(): void
    {
        $this->activateActive();
        $owner = $this->owner();
        Http::fake(['*/license/verify' => Http::response($this->signedResponse([
            'status' => 'active', 'license_key' => 'ZC-TEST-0001', 'domain' => 'localhost', 'expires_at' => now()->addMonth()->toIso8601String(),
        ]))]);

        $this->actingAs($owner, 'staff')->postJson(route('license.recheck'))->assertOk();
        $this->actingAs($owner, 'staff')->postJson(route('license.recheck'))->assertStatus(429);
    }

    // --- Anti-bypass hardening: deleting ONE enforcement point must not unlock the app ---

    public function test_order_status_update_is_blocked_independently_of_the_http_middleware(): void
    {
        $order = Order::create([
            'order_number' => 'ZC-LIC-'.uniqid(), 'customer_name' => 'Jane', 'customer_phone' => '01712345678',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 80, 'total' => 1080, 'status' => 'pending', 'source' => 'website',
        ]);

        // No license key activated -> blocked. Calling the SERVICE directly
        // (as if the HTTP middleware layer had been removed) must still stop it.
        $this->expectException(HttpException::class);
        app(OrderManagementService::class)->updateStatus($order, 'confirmed');
    }

    public function test_operational_standing_helper_blocks_when_unlicensed_and_passes_when_active(): void
    {
        $this->expectException(HttpException::class);
        OperationalStanding::assertActive();
    }

    public function test_operational_standing_passes_once_activated(): void
    {
        $this->activateActive();

        OperationalStanding::assertActive(); // does not throw

        $this->assertTrue(true);
    }

    private function activateActive(): void
    {
        $payload = ['status' => 'activated', 'license_key' => 'ZC-TEST-0001', 'domain' => 'localhost', 'expires_at' => now()->addYear()->toIso8601String()];
        Http::fake(['*/license/activate' => Http::response($this->signedResponse($payload))]);
        $this->service()->activate('ZC-TEST-0001');
    }
}
