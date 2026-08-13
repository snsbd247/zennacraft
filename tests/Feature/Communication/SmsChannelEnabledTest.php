<?php

namespace Tests\Feature\Communication;

use App\Modules\Communication\Services\Channels\SmsChannel;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsChannelEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_by_default(): void
    {
        $this->assertFalse(app(SmsChannel::class)->enabled());
    }

    public function test_enabled_via_the_sms_gateway_settings_group(): void
    {
        // This is the toggle the SMS Gateway config page actually writes.
        app(SettingService::class)->set('sms', 'sms_enabled', true, 'boolean');

        $this->assertTrue(app(SmsChannel::class)->enabled());
    }

    public function test_still_honours_the_legacy_communication_group_flag(): void
    {
        app(SettingService::class)->set('communication', 'sms_enabled', true, 'boolean');

        $this->assertTrue(app(SmsChannel::class)->enabled());
    }
}
