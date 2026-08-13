<?php

namespace App\Modules\Courier\Services\Api;

use App\Modules\Courier\Contracts\CourierApiClient;
use App\Modules\Courier\Exceptions\CourierApiException;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Steadfast Courier API — see docs/courier-payment-providers.md for the
 * full spec (including which endpoint paths are directly confirmed vs.
 * inferred from consistent naming across independent packages).
 */
class SteadfastCourierClient implements CourierApiClient
{
    public function __construct(
        private SettingService $settings,
        private PhoneService $phoneService,
    ) {}

    public function slug(): string
    {
        return 'steadfast';
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey()) && filled($this->secretKey());
    }

    public function createOrder(Shipment $shipment): array
    {
        if (! $this->isConfigured()) {
            throw new CourierApiException('Steadfast is not configured: fill in the API Key and Secret Key in Studio -> Settings -> Courier API Setup.');
        }

        $order = $shipment->order;

        $response = $this->request('post', '/create_order', [
            'invoice' => $order->order_number,
            'recipient_name' => (string) $order->customer_name,
            'recipient_phone' => $this->phoneService->normalize((string) $order->customer_phone),
            'recipient_address' => (string) $order->address,
            'cod_amount' => (float) ($shipment->cod_amount ?: $order->total),
            'note' => (string) $shipment->notes,
        ]);

        if ((int) ($response['status'] ?? 0) !== 200) {
            throw new CourierApiException('Steadfast rejected the order: '.($response['message'] ?? 'unexpected response.'));
        }

        $consignment = $response['consignment'] ?? [];
        $consignmentId = (string) ($consignment['consignment_id'] ?? '');
        $trackingCode = (string) ($consignment['tracking_code'] ?? $consignmentId);

        if ($consignmentId === '') {
            throw new CourierApiException('Steadfast accepted the request but did not return a consignment_id.');
        }

        return [
            'tracking_number' => $trackingCode,
            'consignment_id' => $consignmentId,
            'raw' => $consignment,
        ];
    }

    public function trackOrder(Shipment $shipment): array
    {
        if ($shipment->consignment_id === null || $shipment->consignment_id === '') {
            throw new CourierApiException('This shipment has no Steadfast consignment id to track.');
        }

        $response = $this->request('get', '/status_by_cid/'.$shipment->consignment_id);

        return [
            'status' => $this->mapStatus((string) ($response['delivery_status'] ?? '')),
            'raw' => $response,
        ];
    }

    public function mapStatus(string $deliveryStatus): ?string
    {
        return match (Str::lower($deliveryStatus)) {
            'pending', 'in_review', 'hold' => 'assigned',
            'delivered' => 'delivered',
            'partial_delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default => null,
        };
    }

    protected function request(string $method, string $path, array $payload = []): array
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey(),
                'Secret-Key' => $this->secretKey(),
            ])->acceptJson()->timeout(15)->{$method}($this->baseUrl().$path, $payload);
        } catch (Throwable $exception) {
            throw new CourierApiException('Steadfast request failed: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new CourierApiException('Steadfast rejected the configured API Key / Secret Key.');
        }

        return $response->json() ?? [];
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->settings->get('courier', 'steadfast_base_url') ?: config('courier.drivers.steadfast.base_url')), '/');
    }

    protected function apiKey(): string
    {
        return (string) $this->settings->getEncrypted('courier', 'steadfast_api_key', '');
    }

    protected function secretKey(): string
    {
        return (string) $this->settings->getEncrypted('courier', 'steadfast_secret_key', '');
    }
}
