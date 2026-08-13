<?php

namespace App\Modules\Communication\Services\Sms;

use App\Modules\Communication\Contracts\SmsDriver;
use App\Modules\Communication\Services\Sms\Drivers\LogSmsDriver;
use App\Modules\Settings\Services\SettingService;

class SmsDriverManager
{
    public function __construct(private SettingService $settingService) {}

    /**
     * Settings -> SMS & OTP (Studio) lets the owner pick a provider
     * without a deploy; a saved value there takes priority over
     * config/sms.php's env-driven default so an env-configured
     * deployment keeps working until someone explicitly saves a choice
     * in Studio.
     */
    public function driverName(): string
    {
        $configured = (string) $this->settingService->get('sms', 'provider', '');

        return $configured !== '' ? $configured : (string) config('sms.driver', 'log');
    }

    public function driver(): SmsDriver
    {
        $name = $this->driverName();
        $class = config("sms.drivers.{$name}.class");

        if (! is_string($class) || ! class_exists($class)) {
            logger()->warning('Unknown SMS driver configured — falling back to the log driver.', [
                'configured_driver' => $name,
            ]);

            return app(LogSmsDriver::class);
        }

        return app($class);
    }
}
