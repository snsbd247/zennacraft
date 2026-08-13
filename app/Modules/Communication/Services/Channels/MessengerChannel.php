<?php

namespace App\Modules\Communication\Services\Channels;

use App\Modules\Communication\Models\CommunicationMessage;

class MessengerChannel extends BaseChannel
{
    public function name(): string
    {
        return 'messenger';
    }

    public function enabled(): bool
    {
        return $this->settingEnabled('messenger_enabled', false);
    }

    public function send(CommunicationMessage $message): array
    {
        return [
            'provider' => 'messenger.placeholder',
            'sent' => false,
            'message' => 'Messenger channel is future-ready. Configure a provider before enabling live delivery.',
        ];
    }
}
