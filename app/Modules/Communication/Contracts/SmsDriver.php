<?php

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\DTOs\SmsSendResult;

interface SmsDriver
{
    public function name(): string;

    /**
     * Send a single SMS. Must never throw for an ordinary provider-side
     * failure (bad number, insufficient balance, etc.) — return a failed
     * SmsSendResult instead so the caller can log/record it. Throwing is
     * reserved for programmer error (misconfiguration should still be
     * caught and turned into a failure result by the implementation).
     */
    public function send(string $phone, string $message): SmsSendResult;
}
