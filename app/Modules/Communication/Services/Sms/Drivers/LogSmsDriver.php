<?php

namespace App\Modules\Communication\Services\Sms\Drivers;

use App\Modules\Communication\Contracts\SmsDriver;
use App\Modules\Communication\DTOs\SmsSendResult;
use Illuminate\Support\Facades\Log;

/**
 * Default driver in local/testing. Sends nothing to any real phone —
 * writes the recipient and full message body (including OTP codes) to
 * the application log instead. Replaces the old config('app.debug')
 * on-screen OTP reveal: check the log to see the code locally.
 */
class LogSmsDriver implements SmsDriver
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phone, string $message): SmsSendResult
    {
        Log::info('SMS (log driver — no real SMS was sent)', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return SmsSendResult::success('Logged locally by the log SMS driver; no real SMS was sent.');
    }
}
