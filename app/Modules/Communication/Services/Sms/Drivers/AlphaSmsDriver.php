<?php

namespace App\Modules\Communication\Services\Sms\Drivers;

use App\Modules\Communication\Contracts\SmsDriver;
use App\Modules\Communication\DTOs\SmsSendResult;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Alpha SMS (alpha.net.bd / sms.net.bd).
 *
 * Spec source: docs/sms-providers.md (fetched from
 * https://www.alpha.net.bd/SMS/api/). Endpoint supports GET or POST;
 * this driver uses a form-encoded POST since the API's own example
 * shows parameters passed the same way a GET query string would be
 * (?api_key=...&msg=...&to=...) rather than a documented JSON body.
 */
class AlphaSmsDriver implements SmsDriver
{
    public function __construct(private SettingService $settingService) {}

    public function name(): string
    {
        return 'alpha';
    }

    public function send(string $phone, string $message): SmsSendResult
    {
        $config = (array) config('sms.drivers.alpha', []);
        $apiKey = $this->settingService->getEncrypted('sms', 'api_key', '') ?: (string) ($config['api_key'] ?? '');
        $baseUrl = (string) ($config['base_url'] ?? 'https://api.sms.net.bd/sendsms');

        if ($apiKey === '') {
            return SmsSendResult::failure('Alpha SMS is not configured: set an API key in Studio -> Settings -> SMS & OTP.');
        }

        $params = [
            'api_key' => $apiKey,
            'msg' => $message,
            'to' => $this->toInternationalFormat($phone),
        ];

        $senderId = $this->settingService->get('sms', 'sender_id') ?: config('sms.sender_id');

        if ((bool) config('sms.masking_enabled', false) && filled($senderId)) {
            $params['sender_id'] = $senderId;
        }

        try {
            $response = Http::asForm()->timeout(10)->post($baseUrl, $params);
        } catch (Throwable $exception) {
            return SmsSendResult::failure('Alpha SMS request failed: '.$exception->getMessage());
        }

        $body = $response->json() ?? [];
        $errorCode = array_key_exists('error', $body) ? (int) $body['error'] : null;

        if (! $response->successful() || $errorCode !== 0) {
            $reason = (string) ($body['msg'] ?? ('HTTP '.$response->status()));

            return SmsSendResult::failure('Alpha SMS rejected the message: '.$reason);
        }

        $requestId = $body['data']['request_id'] ?? null;

        return SmsSendResult::success(
            (string) ($body['msg'] ?? 'Request successfully submitted'),
            $requestId !== null ? (string) $requestId : null,
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
