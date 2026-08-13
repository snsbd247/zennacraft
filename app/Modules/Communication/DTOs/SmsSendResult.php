<?php

namespace App\Modules\Communication\DTOs;

class SmsSendResult
{
    private function __construct(
        public readonly bool $sent,
        public readonly string $message,
        public readonly ?string $providerMessageId = null,
    ) {}

    public static function success(string $message, ?string $providerMessageId = null): self
    {
        return new self(true, $message, $providerMessageId);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
