<?php

namespace Tests\Feature\Communication;

use App\Modules\Communication\Services\Sms\Drivers\BdBulkSmsDriver;
use App\Modules\Communication\Services\Sms\SmsDriverManager;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BdBulkSmsDriverTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        $s = app(SettingService::class);
        $s->set('sms', 'provider', 'bdbulk');
        $s->setEncrypted('sms', 'api_key', 'TOKEN123');
    }

    public function test_manager_resolves_bdbulk_provider(): void
    {
        $this->configure();
        $this->assertSame('bdbulk', app(SmsDriverManager::class)->driverName());
        $this->assertInstanceOf(BdBulkSmsDriver::class, app(SmsDriverManager::class)->driver());
    }

    public function test_successful_send_parses_json_and_normalises_phone(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([['to' => '+8801340601530', 'message' => 'x', 'status' => 'SENT', 'statusmsg' => 'SMS Sent Successfully']], 200)]);

        $result = app(SmsDriverManager::class)->driver()->send('01340601530', 'Hello');

        $this->assertTrue($result->sent);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.php')
                && $request['token'] === 'TOKEN123'
                && $request['to'] === '01340601530'
                && $request['message'] === 'Hello';
        });
    }

    public function test_provider_rejection_returns_failure_with_reason(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([['to' => '+8801x', 'status' => 'ERROR', 'statusmsg' => 'Insufficient Balance']], 200)]);

        $result = app(SmsDriverManager::class)->driver()->send('01340601530', 'Hello');

        $this->assertFalse($result->sent);
        $this->assertStringContainsString('Insufficient Balance', $result->message);
    }

    public function test_missing_token_fails_cleanly(): void
    {
        app(SettingService::class)->set('sms', 'provider', 'bdbulk');
        config(['sms.drivers.bdbulk.token' => null]); // no env fallback either
        Http::fake();

        $result = app(SmsDriverManager::class)->driver()->send('01340601530', 'Hello');

        $this->assertFalse($result->sent);
        $this->assertStringContainsString('not configured', $result->message);
        Http::assertNothingSent();
    }
}
