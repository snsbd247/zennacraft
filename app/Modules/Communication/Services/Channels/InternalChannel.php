<?php

namespace App\Modules\Communication\Services\Channels;

use App\Modules\Communication\Models\CommunicationMessage;

class InternalChannel extends BaseChannel
{
    public function name(): string
    {
        return 'internal';
    }

    public function enabled(): bool
    {
        return $this->settingEnabled('internal_enabled', true);
    }

    public function send(CommunicationMessage $message): array
    {
        return [
            'provider' => 'internal',
            'sent' => true,
            'message' => 'Internal notification recorded.',
        ];
    }
}
