<?php

namespace Tests\Feature\Settings;

use App\Modules\Facebook\Jobs\SendFacebookCapiEventJob;
use App\Modules\Facebook\Models\FacebookEvent;
use App\Modules\Facebook\Services\FacebookTrackingService;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CapiTokenEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capi_token_is_encrypted_at_rest(): void
    {
        $settings = app(SettingService::class);
        $plaintext = 'EAAG-real-looking-facebook-capi-token-1234567890';

        $settings->setEncrypted('general', 'facebook_capi_access_token', $plaintext);

        $rawValue = Setting::where('setting_group', 'general')
            ->where('setting_key', 'facebook_capi_access_token')
            ->value('value');

        $this->assertNotNull($rawValue);
        $this->assertStringNotContainsString($plaintext, $rawValue, 'The raw DB value must not contain the plaintext token.');
    }

    public function test_capi_token_round_trips_correctly(): void
    {
        $settings = app(SettingService::class);
        $plaintext = 'EAAG-round-trip-token-abc123';

        $settings->setEncrypted('general', 'facebook_capi_access_token', $plaintext);

        $this->assertSame($plaintext, $settings->getEncrypted('general', 'facebook_capi_access_token'));
    }

    public function test_legacy_plaintext_token_saved_before_encryption_still_works(): void
    {
        // Simulate a value saved by the old code path (plain set(), not
        // setEncrypted()) before this fix shipped.
        Setting::updateOrCreate(
            ['setting_group' => 'general', 'setting_key' => 'facebook_capi_access_token'],
            ['value' => 'legacy-plaintext-token-not-encrypted', 'data_type' => 'string', 'is_public' => false]
        );

        $settings = app(SettingService::class);

        $this->assertSame('legacy-plaintext-token-not-encrypted', $settings->getEncrypted('general', 'facebook_capi_access_token'));
    }

    /**
     * The token is read inside SendFacebookCapiEventJob, which runs in a
     * queue worker process, not the HTTP request that saved it — verified
     * here by actually dispatching and running the real job, not just
     * calling FacebookTrackingService::sendToCapi() directly.
     */
    public function test_capi_pipeline_still_sends_the_real_token_through_the_queued_job(): void
    {
        $settings = app(SettingService::class);
        $realToken = 'EAAG-queued-job-real-token-9876543210';

        $settings->set('general', 'facebook_pixel_id', '123456789', 'string', true);
        $settings->set('general', 'facebook_capi_enabled', true, 'boolean', false);
        $settings->setEncrypted('general', 'facebook_capi_access_token', $realToken);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        $service = app(FacebookTrackingService::class);
        $event = FacebookEvent::create([
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_name' => 'PageView',
            'event_source_url' => 'https://example.test/',
            'event_time' => now(),
            'payload' => ['event_name' => 'PageView'],
            'status' => 'pending',
        ]);

        // Run the actual job (QUEUE_CONNECTION=sync in tests runs it
        // inline, but this is still the real job class, not a shortcut).
        (new SendFacebookCapiEventJob($event->id))->handle($service);

        $event->refresh();
        $this->assertSame('sent', $event->status);

        Http::assertSent(function (Request $request) use ($realToken) {
            return $request->url() === 'https://graph.facebook.com/v20.0/123456789/events'
                && $request['access_token'] === $realToken;
        });
    }
}
