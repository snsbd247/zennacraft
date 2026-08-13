<?php

namespace App\Modules\Checkout\Services\Payment;

use App\Modules\Checkout\Exceptions\PaymentGatewayException;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * bKash Checkout (URL-based), v1.2.0-beta — see
 * docs/courier-payment-providers.md for the full spec and which parts of
 * it are directly confirmed vs. pattern-matched from bKash's other
 * v1.2.0-beta products.
 */
class BkashPaymentClient
{
    public function __construct(private SettingService $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->appKey())
            && filled($this->appSecret())
            && filled($this->username())
            && filled($this->password());
    }

    /**
     * @return array{payment_id: string, bkash_url: string}
     *
     * @throws PaymentGatewayException
     */
    public function createPayment(Order $order, string $callbackUrl): array
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayException('bKash is not configured: fill in App Key/Secret and Username/Password in Studio -> Settings -> Payment Gateway.');
        }

        $response = $this->request('post', '/checkout/payment/create', [
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->order_number,
            'callbackURL' => $callbackUrl,
        ]);

        $paymentId = (string) ($response['paymentID'] ?? '');
        $bkashUrl = (string) ($response['bkashURL'] ?? '');

        if ($paymentId === '' || $bkashUrl === '') {
            throw new PaymentGatewayException('bKash did not return a payment URL: '.($response['statusMessage'] ?? 'unexpected response.'));
        }

        return ['payment_id' => $paymentId, 'bkash_url' => $bkashUrl];
    }

    /**
     * @return array{success: bool, trx_id: ?string, amount: ?float, raw: array}
     *
     * @throws PaymentGatewayException
     */
    public function executePayment(string $paymentId): array
    {
        $response = $this->request('post', '/checkout/payment/execute/'.$paymentId);

        $success = ($response['transactionStatus'] ?? null) === 'Completed'
            && ($response['statusCode'] ?? null) === '0000';

        return [
            'success' => $success,
            'trx_id' => $response['trxID'] ?? null,
            'amount' => isset($response['amount']) ? (float) $response['amount'] : null,
            'raw' => $response,
        ];
    }

    /**
     * Best-effort re-check, not on the critical checkout path — path is
     * pattern-matched from the create/execute paths, not independently
     * confirmed (see docs/courier-payment-providers.md).
     */
    public function queryPayment(string $paymentId): array
    {
        return $this->request('get', '/checkout/payment/status/'.$paymentId);
    }

    protected function request(string $method, string $path, array $payload = []): array
    {
        $token = $this->idToken();

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
                'X-APP-Key' => $this->appKey(),
            ])->acceptJson()->timeout(20)->{$method}($this->baseUrl().$path, $payload);
        } catch (Throwable $exception) {
            throw new PaymentGatewayException('bKash request failed: '.$exception->getMessage(), previous: $exception);
        }

        return $response->json() ?? [];
    }

    protected function idToken(): string
    {
        return Cache::remember('bkash:id_token:'.($this->sandbox() ? 'sandbox' : 'live'), now()->addMinutes(50), function (): string {
            try {
                $response = Http::withHeaders([
                    'username' => $this->username(),
                    'password' => $this->password(),
                ])->acceptJson()->timeout(20)->post($this->baseUrl().'/checkout/token/grant', [
                    'app_key' => $this->appKey(),
                    'app_secret' => $this->appSecret(),
                ]);
            } catch (Throwable $exception) {
                throw new PaymentGatewayException('bKash login failed: '.$exception->getMessage(), previous: $exception);
            }

            $idToken = $response->json('id_token');

            if (blank($idToken)) {
                throw new PaymentGatewayException('bKash login rejected the configured credentials — double check App Key/Secret and Username/Password.');
            }

            return (string) $idToken;
        });
    }

    protected function baseUrl(): string
    {
        return rtrim($this->sandbox() ? config('bkash.sandbox_base_url') : config('bkash.live_base_url'), '/');
    }

    protected function sandbox(): bool
    {
        return filter_var($this->settings->get('payment', 'bkash_sandbox', true), FILTER_VALIDATE_BOOLEAN);
    }

    protected function appKey(): string
    {
        return (string) $this->settings->get('payment', 'bkash_app_key', '');
    }

    protected function appSecret(): string
    {
        return (string) $this->settings->getEncrypted('payment', 'bkash_app_secret', '');
    }

    protected function username(): string
    {
        return (string) $this->settings->get('payment', 'bkash_username', '');
    }

    protected function password(): string
    {
        return (string) $this->settings->getEncrypted('payment', 'bkash_password', '');
    }
}
