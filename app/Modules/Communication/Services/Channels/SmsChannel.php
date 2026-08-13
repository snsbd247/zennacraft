<?php

namespace App\Modules\Communication\Services\Channels;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\Sms\SmsDriverManager;
use App\Modules\Settings\Services\SettingService;

class SmsChannel extends BaseChannel
{
    public function __construct(SettingService $settings, private SmsDriverManager $drivers)
    {
        parent::__construct($settings);
    }

    public function name(): string
    {
        return 'sms';
    }

    public function enabled(): bool
    {
        // The SMS Gateway settings page (Setting & Configuration) stores the
        // toggle in the 'sms' group next to provider/api_key; older setups used
        // the 'communication' group. Honour either so enabling it there works.
        return filter_var($this->settings->get('sms', 'sms_enabled', false), FILTER_VALIDATE_BOOLEAN)
            || $this->settingEnabled('sms_enabled', false);
    }

    public function send(CommunicationMessage $message): array
    {
        $result = $this->drivers->driver()->send($message->recipient, $message->body);

        return [
            'provider' => 'sms.'.$this->drivers->driverName(),
            'sent' => $result->sent,
            'message' => $result->message,
            'provider_message_id' => $result->providerMessageId,
        ];
    }
}
