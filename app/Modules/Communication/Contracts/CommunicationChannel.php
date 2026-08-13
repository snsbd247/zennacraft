<?php

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\CommunicationMessage;

interface CommunicationChannel
{
    public function name(): string;

    public function enabled(): bool;

    public function send(CommunicationMessage $message): array;
}
