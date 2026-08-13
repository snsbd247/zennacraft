<?php

namespace App\Modules\Facebook\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Facebook\Jobs\SendFacebookCapiEventJob;
use App\Modules\Facebook\Models\FacebookEvent;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FacebookTrackingService
{
    public function __construct(
        private SettingService $settings,
        private AuditService $auditService,
        private PhoneService $phoneService,
    ) {}

    public function trackPageView(Request $request): ?FacebookEvent
    {
        return $this->trackEvent('PageView', $request);
    }

    public function trackViewContent(Product $product, Request $request): ?FacebookEvent
    {
        return $this->trackEvent('ViewContent', $request, null, [
            'content_ids' => [(string) $product->id],
            'content_name' => $product->name,
            'content_type' => 'product',
            'currency' => $this->currency(),
            'value' => (float) $product->price,
        ]);
    }

    public function trackInitiateCheckout(Order|array $data, Request $request): ?FacebookEvent
    {
        if ($data instanceof Order) {
            return $this->trackEvent('InitiateCheckout', $request, $data, [
                'currency' => $this->currency(),
                'value' => (float) $data->total,
                'contents' => $this->orderContents($data),
                'content_type' => 'product',
            ], $data->customer_phone, $data->customer_email);
        }

        $item = $data['item'] ?? [];

        return $this->trackEvent('InitiateCheckout', $request, null, [
            'currency' => $this->currency(),
            'value' => (float) ($data['total'] ?? $item['subtotal'] ?? 0),
            'contents' => [[
                'id' => (string) ($item['product_id'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 1),
                'item_price' => (float) ($item['price'] ?? 0),
            ]],
            'content_type' => 'product',
        ]);
    }

    public function trackPurchase(Order $order, Request $request): ?FacebookEvent
    {
        $order->loadMissing('items');

        return $this->trackEvent('Purchase', $request, $order, [
            'currency' => $this->currency(),
            'value' => (float) $order->total,
            'contents' => $this->orderContents($order),
            'content_type' => 'product',
            'order_id' => $order->order_number,
        ], $order->customer_phone, $order->customer_email);
    }

    public function buildEventPayload(
        string $eventName,
        string $eventId,
        Request $request,
        array $customData = [],
        array $userData = []
    ): array {
        $payload = [
            'event_name' => $eventName,
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $request->fullUrl(),
            'user_data' => array_filter(array_merge([
                'client_ip_address' => $request->ip(),
                'client_user_agent' => $request->userAgent(),
            ], $userData)),
        ];

        if ($customData !== []) {
            $payload['custom_data'] = $customData;
        }

        return $payload;
    }

    public function sendToCapi(FacebookEvent $event): FacebookEvent
    {
        $pixelId = trim((string) $this->settings->get('general', 'facebook_pixel_id', ''));
        $accessToken = trim((string) $this->settings->getEncrypted('general', 'facebook_capi_access_token', ''));

        if (! $this->enabled('facebook_capi_enabled')) {
            return $this->markSkipped($event, 'Facebook CAPI is disabled.');
        }

        if ($pixelId === '' || $accessToken === '') {
            return $this->markSkipped($event, 'Facebook CAPI skipped because pixel id or access token is missing.');
        }

        try {
            $requestPayload = [
                'data' => [$event->payload],
                'access_token' => $accessToken,
            ];

            $testEventCode = trim((string) $this->settings->get('general', 'facebook_capi_test_event_code', ''));
            if ($testEventCode !== '') {
                $requestPayload['test_event_code'] = $testEventCode;
            }

            $response = Http::timeout(8)
                ->asJson()
                ->post("https://graph.facebook.com/v20.0/{$pixelId}/events", $requestPayload);

            $responseBody = $response->json();
            if (! is_array($responseBody)) {
                $responseBody = ['body' => $response->body()];
            }

            if ($response->successful() && ! isset($responseBody['error'])) {
                $event->update([
                    'status' => 'sent',
                    'response' => $responseBody,
                    'error_message' => null,
                    'sent_at' => now(),
                ]);
            } else {
                $event->update([
                    'status' => 'failed',
                    'response' => $responseBody,
                    'error_message' => $responseBody['error']['message'] ?? 'Facebook CAPI request failed with status '.$response->status().'.',
                ]);
            }
        } catch (ConnectionException $exception) {
            $event->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $event->refresh();
    }

    public function hashUserData(?string $phone = null, ?string $email = null): array
    {
        $data = [];

        $normalizedPhone = $this->phoneService->normalize($phone);
        if ($normalizedPhone !== '') {
            $data['ph'] = [hash('sha256', $normalizedPhone)];
        }

        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail !== '') {
            $data['em'] = [hash('sha256', $normalizedEmail)];
        }

        return $data;
    }

    protected function trackEvent(
        string $eventName,
        Request $request,
        ?Order $order = null,
        array $customData = [],
        ?string $phone = null,
        ?string $email = null
    ): ?FacebookEvent {
        try {
            $eventId = $this->uniqueEventId();
            $userData = $this->hashUserData($phone, $email);
            $payload = $this->buildEventPayload($eventName, $eventId, $request, $customData, $userData);

            $event = FacebookEvent::create([
                'event_id' => $eventId,
                'event_name' => $eventName,
                'event_source_url' => $request->fullUrl(),
                'event_time' => now(),
                'customer_phone_hash' => $userData['ph'][0] ?? null,
                'customer_email_hash' => $userData['em'][0] ?? null,
                'order_id' => $order?->id,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            // The local record above is what lets staff see the event was
            // captured immediately. The actual outbound HTTP call to Meta
            // (up to 8s, per sendToCapi()'s timeout) is queued on the bulk
            // tier so it never blocks the page load or checkout request —
            // and never competes with OTP, which has its own dedicated
            // worker (see docs/queue-setup.md).
            SendFacebookCapiEventJob::dispatch($event->id)
                ->onQueue((string) config('queue_priorities.bulk'));

            return $event;
        } catch (Throwable $exception) {
            logger()->warning('Facebook tracking failed safely', [
                'event_name' => $eventName,
                'order_id' => $order?->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function uniqueEventId(): string
    {
        do {
            $eventId = (string) Str::uuid();
        } while (FacebookEvent::where('event_id', $eventId)->exists());

        return $eventId;
    }

    protected function markSkipped(FacebookEvent $event, string $reason): FacebookEvent
    {
        $event->update([
            'status' => 'skipped',
            'error_message' => $reason,
        ]);

        return $event->refresh();
    }

    protected function enabled(string $key): bool
    {
        return filter_var($this->settings->get('general', $key, false), FILTER_VALIDATE_BOOLEAN);
    }

    protected function currency(): string
    {
        return (string) $this->settings->get('general', 'currency', 'BDT');
    }

    protected function orderContents(Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'id' => (string) ($item->variant_id ?: $item->product_id),
            'quantity' => (int) $item->quantity,
            'item_price' => (float) $item->price,
        ])->values()->all();
    }

    protected function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function logAudit(FacebookEvent $event): void
    {
        try {
            $this->auditService->log(
                'facebook',
                'facebook.event.track',
                'facebook',
                'Facebook event tracked: '.$event->event_name,
                [
                    'event_id' => $event->event_id,
                    'event_name' => $event->event_name,
                    'status' => $event->status,
                    'order_id' => $event->order_id,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Facebook tracking audit failed', [
                'event_id' => $event->event_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
