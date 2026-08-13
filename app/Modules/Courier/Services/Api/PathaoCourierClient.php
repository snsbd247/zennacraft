<?php

namespace App\Modules\Courier\Services\Api;

use App\Modules\Courier\Contracts\CourierApiClient;
use App\Modules\Courier\Exceptions\CourierApiException;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pathao Merchant ("Aladdin") API — see docs/courier-payment-providers.md
 * for the full spec this was built from, including the zone-matching
 * caveat explained on createOrder() below.
 */
class PathaoCourierClient implements CourierApiClient
{
    public function __construct(
        private SettingService $settings,
        private PhoneService $phoneService,
    ) {}

    public function slug(): string
    {
        return 'pathao';
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId())
            && filled($this->clientSecret())
            && filled($this->clientEmail())
            && filled($this->clientPassword())
            && filled($this->storeId());
    }

    public function createOrder(Shipment $shipment): array
    {
        if (! $this->isConfigured()) {
            throw new CourierApiException('Pathao is not configured: fill in Store ID, Client ID/Secret and Client Email/Password in Studio -> Settings -> Courier API Setup.');
        }

        $order = $shipment->order;
        [$cityId, $zoneId] = $this->resolveCityAndZone((string) $order->district, (string) $order->address);

        $payload = [
            'store_id' => (int) $this->storeId(),
            'merchant_order_id' => $order->order_number,
            'recipient_name' => (string) $order->customer_name,
            'recipient_phone' => $this->phoneService->normalize((string) $order->customer_phone),
            'recipient_address' => (string) $order->address,
            'recipient_city' => $cityId,
            'recipient_zone' => $zoneId,
            'delivery_type' => 48, // normal delivery
            'item_type' => 2, // parcel
            'special_instruction' => Str::limit((string) $shipment->notes, 400, ''),
            'item_quantity' => max(1, (int) $order->items->sum('quantity')),
            'item_weight' => 0.5,
            'amount_to_collect' => (float) ($shipment->cod_amount ?: $order->total),
            'item_description' => $order->items->pluck('product_name')->filter()->implode(', ') ?: 'Order '.$order->order_number,
        ];

        $response = $this->request('post', '/aladdin/api/v1/orders', $payload);
        $data = $response['data'] ?? [];
        $consignmentId = (string) ($data['consignment_id'] ?? '');

        if ($consignmentId === '') {
            throw new CourierApiException('Pathao accepted the request but did not return a consignment_id: '.($response['message'] ?? 'unexpected response.'));
        }

        return [
            'tracking_number' => $consignmentId,
            'consignment_id' => $consignmentId,
            'raw' => $data,
        ];
    }

    public function trackOrder(Shipment $shipment): array
    {
        if ($shipment->consignment_id === null || $shipment->consignment_id === '') {
            throw new CourierApiException('This shipment has no Pathao consignment id to track.');
        }

        $response = $this->request('get', '/aladdin/api/v1/orders/'.$shipment->consignment_id.'/info');
        $data = $response['data'] ?? [];
        $slug = (string) ($data['order_status_slug'] ?? $data['order_status'] ?? '');

        return [
            'status' => $this->mapStatus($slug),
            'raw' => $data,
        ];
    }

    /**
     * Our orders only store a free-text district + address, not Pathao's
     * numeric city/zone taxonomy — so city is matched exactly against
     * Pathao's own city list (safe: both use real Bangladesh district
     * names) and zone is matched by looking for a zone name inside the
     * order's address text. When zone can't be confidently resolved this
     * throws rather than guessing — staff falls back to the existing
     * manual tracking-number field for that one order.
     *
     * @return array{0: int, 1: int}
     */
    protected function resolveCityAndZone(string $district, string $address): array
    {
        $district = trim($district);
        $cities = $this->cities();
        $city = null;

        if ($district !== '') {
            $city = collect($cities)->first(
                fn (array $c): bool => Str::lower(trim((string) ($c['city_name'] ?? ''))) === Str::lower($district)
            );
        }

        // Orders placed through Studio's "Create Order" (POS) flow often have
        // no separate district field — only a free-text address. Fall back to
        // finding a known Pathao city name inside that address text before
        // giving up; city names are distinctive enough words in Bangladesh
        // that a substring match here is safe.
        if (! $city) {
            $addressLower = Str::lower($address);
            $city = collect($cities)->first(
                fn (array $c): bool => filled($c['city_name'] ?? null) && str_contains($addressLower, Str::lower((string) $c['city_name']))
            );
        }

        if (! $city) {
            $label = $district !== '' ? "district \"{$district}\"" : 'this address';
            throw new CourierApiException("Pathao has no matching city for {$label}. Push this order from the Pathao dashboard directly and paste the tracking number here.");
        }

        $zones = $this->zones((int) $city['city_id']);
        $haystack = Str::lower($address);
        $matches = collect($zones)->filter(
            fn (array $z): bool => filled($z['zone_name'] ?? null) && str_contains($haystack, Str::lower((string) $z['zone_name']))
        );

        if ($matches->count() !== 1) {
            $cityName = (string) $city['city_name'];
            throw new CourierApiException(
                $matches->isEmpty()
                    ? "Could not auto-detect a Pathao delivery zone for this address in {$cityName}. Push this order from the Pathao dashboard directly and paste the tracking number here."
                    : "This address matches more than one Pathao zone in {$cityName} — enter the tracking number manually after choosing the zone in the Pathao dashboard."
            );
        }

        return [(int) $city['city_id'], (int) $matches->first()['zone_id']];
    }

    /** @return array<int, array{city_id: int, city_name: string}> */
    protected function cities(): array
    {
        return Cache::remember('pathao:cities', now()->addDay(), function (): array {
            $response = $this->request('get', '/aladdin/api/v1/city-list');

            return $response['data']['data'] ?? [];
        });
    }

    /** @return array<int, array{zone_id: int, zone_name: string}> */
    protected function zones(int $cityId): array
    {
        return Cache::remember("pathao:zones:{$cityId}", now()->addDay(), function () use ($cityId): array {
            $response = $this->request('get', "/aladdin/api/v1/cities/{$cityId}/zone-list");

            return $response['data']['data'] ?? [];
        });
    }

    protected function mapStatus(string $slug): ?string
    {
        $slug = Str::lower($slug);

        return match (true) {
            str_contains($slug, 'pickup') => 'assigned',
            str_contains($slug, 'transit'), str_contains($slug, 'in_review'), str_contains($slug, 'on_hold') => 'assigned',
            str_contains($slug, 'delivered') => 'delivered',
            str_contains($slug, 'return') => 'returned',
            str_contains($slug, 'cancel') => 'cancelled',
            str_contains($slug, 'fail') => 'failed',
            default => null,
        };
    }

    protected function request(string $method, string $path, array $payload = []): array
    {
        $token = $this->accessToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->{$method}($this->baseUrl().$path, $payload);
        } catch (Throwable $exception) {
            throw new CourierApiException('Pathao request failed: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            $body = $response->json();
            $message = $body['message'] ?? ('HTTP '.$response->status());
            throw new CourierApiException('Pathao rejected the request: '.$message);
        }

        return $response->json() ?? [];
    }

    protected function accessToken(): string
    {
        return Cache::remember('pathao:access_token', now()->addMinutes(50), function (): string {
            try {
                $response = Http::asJson()->acceptJson()->timeout(15)->post($this->baseUrl().'/aladdin/api/v1/issue-token', [
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'username' => $this->clientEmail(),
                    'password' => $this->clientPassword(),
                    'grant_type' => 'password',
                ]);
            } catch (Throwable $exception) {
                throw new CourierApiException('Pathao login failed: '.$exception->getMessage(), previous: $exception);
            }

            if (! $response->successful() || blank($response->json('access_token'))) {
                throw new CourierApiException('Pathao login rejected the configured credentials — double check Client ID/Secret and Client Email/Password.');
            }

            return (string) $response->json('access_token');
        });
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->settings->get('courier', 'pathao_base_url') ?: config('courier.drivers.pathao.base_url')), '/');
    }

    protected function storeId(): string
    {
        return (string) $this->settings->get('courier', 'pathao_store_id', '');
    }

    protected function clientId(): string
    {
        return (string) $this->settings->get('courier', 'pathao_client_id', '');
    }

    protected function clientSecret(): string
    {
        return (string) $this->settings->getEncrypted('courier', 'pathao_client_secret', '');
    }

    protected function clientEmail(): string
    {
        return (string) $this->settings->get('courier', 'pathao_client_email', '');
    }

    protected function clientPassword(): string
    {
        return (string) $this->settings->getEncrypted('courier', 'pathao_client_password', '');
    }
}
