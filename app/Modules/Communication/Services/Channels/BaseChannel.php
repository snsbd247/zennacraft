<?php

namespace App\Modules\Communication\Services\Channels;

use App\Modules\Communication\Contracts\CommunicationChannel;
use App\Modules\Settings\Services\SettingService;

abstract class BaseChannel implements CommunicationChannel
{
    public function __construct(protected SettingService $settings) {}

    protected function settingEnabled(string $key, bool $default = false): bool
    {
        return filter_var($this->settings->get('communication', $key, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
