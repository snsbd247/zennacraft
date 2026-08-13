<?php

namespace App\Modules\Communication\Services\Sms\Drivers;

use App\Modules\Communication\Contracts\SmsDriver;
use App\Modules\Communication\DTOs\SmsSendResult;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * MiMSMS (mimsms.com).
 *
 * Spec source: docs/sms-providers.md, derived from the official
 * mimsms/mim-sms-laravel package source (src/MiMSMSManager.php).
 * POST {base_url}/SMS with UserName + Apikey merged into every
 * request. Success is statusCode === '200' (string comparison,
 * matching the vendor package's own check) — see docs/sms-providers.md
 * for the one flagged ambiguity (the exact success-response field for
 * a provider message id isn't confirmed; trxnId is read defensively).
 */
class MimSmsDriver implements SmsDriver
{
    protected const TRANSACTIONAL_TYPE = 'T';

    public function __construct(private SettingService $settingService) {}

    public function name(): string
    {
        return 'mim';
    }

    public function send(string $phone, string $message): SmsSendResult
    {
        $config = (array) config('sms.drivers.mim', []);
        $username = $this->settingService->get('sms', 'mim_username') ?: (string) ($config['username'] ?? '');
        $apiKey = $this->settingService->getEncrypted('sms', 'api_key', '') ?: (string) ($config['api_key'] ?? '');
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.mimsms.com/api/SmsSending'), '/');

        if ($username === '' || $apiKey === '') {
            return SmsSendResult::failure('MiMSMS is not configured: set a username and API key in Studio -> Settings -> SMS & OTP.');
        }

        $payload = [
            'UserName' => $username,
            'Apikey' => $apiKey,
            'MobileNumber' => $this->toInternationalFormat($phone),
            'Message' => $message,
            'TransactionType' => self::TRANSACTIONAL_TYPE,
        ];

        $senderId = $this->settingService->get('sms', 'sender_id') ?: config('sms.sender_id');

        if ((bool) config('sms.masking_enabled', false) && filled($senderId)) {
            $payload['SenderName'] = $senderId;
        }

        try {
            $response = Http::acceptJson()->asJson()->timeout(10)->post($baseUrl.'/SMS', $payload);
        } catch (Throwable $exception) {
            return SmsSendResult::failure('MiMSMS request failed: '.$exception->getMessage());
        }

        $body = $response->json() ?? [];
        $statusCode = array_key_exists('statusCode', $body) ? (string) $body['statusCode'] : null;

        if (! $response->successful() || $statusCode !== '200') {
            $reason = (string) ($body['responseResult'] ?? ('HTTP '.$response->status()));

            return SmsSendResult::failure('MiMSMS rejected the message: '.$reason);
        }

        $providerMessageId = $body['trxnId'] ?? null;

        return SmsSendResult::success(
            (string) ($body['responseResult'] ?? 'Submitted'),
            $providerMessageId !== null ? (string) $providerMessageId : null,
        );
    }

    protected function toInternationalFormat(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '880'.substr($digits, 1);
        }

        return $digits;
    }
}
