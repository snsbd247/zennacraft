<?php

namespace App\Modules\Communication\Services\Sms\Drivers;

use App\Modules\Communication\Contracts\SmsDriver;
use App\Modules\Communication\DTOs\SmsSendResult;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * BD Bulk SMS (bdbulksms.com).
 *
 * Spec source: https://bdbulksms.com/bd-bulk-sms-api.php
 * POST (or GET) to {base_url} with token + to + message. We request the
 * JSON output (?json) so the result is machine-readable:
 *   [{"to":"+8801…","message":"…","status":"SENT","statusmsg":"SMS Sent Successfully"}]
 * Success is status === "SENT". The plain-text form ("Ok: …" / "Error: …")
 * is handled as a fallback in case the account returns text instead of JSON.
 *
 * The account's API token is stored in the same "API Key" setting the other
 * drivers use (sms.api_key, encrypted), so the owner just pastes the token
 * into the API Key field on the SMS Gateway page.
 */
class BdBulkSmsDriver implements SmsDriver
{
    public function __construct(private SettingService $settingService) {}

    public function name(): string
    {
        return 'bdbulk';
    }

    public function send(string $phone, string $message): SmsSendResult
    {
        $config = (array) config('sms.drivers.bdbulk', []);
        $token = $this->settingService->getEncrypted('sms', 'api_key', '') ?: (string) ($config['token'] ?? '');
        $baseUrl = (string) ($config['base_url'] ?? 'https://api.bdbulksms.net/api.php');

        if ($token === '') {
            return SmsSendResult::failure('BD Bulk SMS is not configured: paste your API token into the API Key field in Studio -> Settings -> SMS Gateway.');
        }

        $params = [
            'token' => $token,
            'to' => $this->toLocalFormat($phone),
            'message' => $message,
        ];

        try {
            // ?json asks the API for structured output instead of plain text.
            $response = Http::asForm()->timeout(15)->post($baseUrl.'?json', $params);
        } catch (Throwable $exception) {
            return SmsSendResult::failure('BD Bulk SMS request failed: '.$exception->getMessage());
        }

        return $this->interpret($response);
    }

    protected function interpret(Response $response): SmsSendResult
    {
        $json = $response->json();
        $record = null;

        if (is_array($json)) {
            if (array_key_exists('status', $json)) {
                $record = $json;                       // single object
            } elseif (isset($json[0]) && is_array($json[0])) {
                $record = $json[0];                    // array of per-recipient objects
            }
        }

        if ($record !== null) {
            $status = strtoupper((string) ($record['status'] ?? ''));
            $statusMsg = trim((string) ($record['statusmsg'] ?? ''));

            if ($response->successful() && $status === 'SENT') {
                return SmsSendResult::success($statusMsg !== '' ? $statusMsg : 'SMS Sent Successfully');
            }

            $reason = $statusMsg !== '' ? $statusMsg : ($status !== '' ? $status : 'HTTP '.$response->status());

            return SmsSendResult::failure('BD Bulk SMS rejected the message: '.$reason);
        }

        // Plain-text fallback: "Ok: …" on success, "Error: …" on failure.
        $body = trim((string) $response->body());

        if ($response->successful() && stripos($body, 'ok:') === 0) {
            return SmsSendResult::success(trim(substr($body, 3)) !== '' ? trim(substr($body, 3)) : 'SMS sent');
        }

        $reason = $body !== '' ? (string) preg_replace('/^error:\s*/i', '', $body) : 'HTTP '.$response->status();

        return SmsSendResult::failure('BD Bulk SMS rejected the message: '.$reason);
    }

    /**
     * BD Bulk SMS accepts "01xxxxxxxxx" or "+8801xxxxxxxxx". Normalise to the
     * local 01xxxxxxxxx form, which is the simplest accepted format.
     */
    protected function toLocalFormat(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880')) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            return $digits;
        }

        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }
}
