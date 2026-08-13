<?php

namespace App\Modules\Verification\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Auto-call order verification: when a customer submits an order, and the
 * integration is enabled in Studio → Setting & Configuration → Order
 * Verification Call, fire a request to the configured auto-call/IVR provider so
 * the system dials the customer to confirm the order. The enabled flag lives in
 * the settings store so it's the single on/off switch the whole system reads.
 */
class AutoCallVerificationService
{
    public function __construct(private SettingService $settings) {}

    public function isEnabled(): bool
    {
        return filter_var($this->settings->get('verification', 'autocall_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Fire the verification call for an order. Never throws — checkout must not break. */
    public function requestCall(Order $order): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $url = trim((string) $this->settings->get('verification', 'autocall_api_url', ''));
        if ($url === '') {
            return false;
        }

        try {
            $apiKey = (string) $this->settings->getEncrypted('verification', 'autocall_api_key', '');
            $request = Http::timeout(6)->connectTimeout(3)->acceptJson();
            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $request->post($url, [
                'phone' => $order->customer_phone,
                'order_number' => $order->order_number,
                'amount' => (float) $order->total,
                'caller_id' => (string) $this->settings->get('verification', 'autocall_caller_id', ''),
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
