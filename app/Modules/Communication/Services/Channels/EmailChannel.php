<?php

namespace App\Modules\Communication\Services\Channels;

use App\Modules\Communication\Models\CommunicationMessage;

class EmailChannel extends BaseChannel
{
    public function name(): string
    {
        return 'email';
    }

    public function enabled(): bool
    {
        return $this->settingEnabled('email_enabled', false);
    }

    public function send(CommunicationMessage $message): array
    {
        return [
            'provider' => 'email.placeholder',
            'sent' => false,
            'message' => 'Email channel is infrastructure-ready. Configure a provider before enabling live delivery.',
        ];
    }
}
