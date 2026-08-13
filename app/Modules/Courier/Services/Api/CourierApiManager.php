<?php

namespace App\Modules\Courier\Services\Api;

use App\Modules\Courier\Contracts\CourierApiClient;
use App\Modules\Settings\Services\SettingService;

/**
 * Resolves the real API client for a courier provider slug, the same way
 * SmsDriverManager resolves an SMS driver: a provider is only "live" when
 * both its Settings "enabled" switch is on (Studio -> Settings -> Courier
 * API Setup) and its client reports isConfigured(). Everything else in
 * this module keeps working exactly as before for providers that have no
 * client here — CourierService just falls back to manual tracking-number
 * entry.
 */
class CourierApiManager
{
    public function __construct(private SettingService $settingService) {}

    public function clientFor(?string $providerSlug): ?CourierApiClient
    {
        if ($providerSlug === null) {
            return null;
        }

        if (! filter_var($this->settingService->get('courier', "{$providerSlug}_enabled", false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $class = config("courier.drivers.{$providerSlug}.class");

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $client = app($class);

        return $client instanceof CourierApiClient ? $client : null;
    }
}
