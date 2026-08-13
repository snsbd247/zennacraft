<?php

namespace App\Modules\Communication\Services\Channels;

use App\Modules\Communication\Models\CommunicationMessage;

class WhatsappChannel extends BaseChannel
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function enabled(): bool
    {
        return $this->settingEnabled('whatsapp_enabled', false);
    }

    public function send(CommunicationMessage $message): array
    {
        return [
            'provider' => 'whatsapp.placeholder',
            'sent' => false,
            'message' => 'WhatsApp channel is infrastructure-ready. Configure a provider before enabling live delivery.',
        ];
    }
}
