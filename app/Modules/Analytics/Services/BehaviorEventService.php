<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

class BehaviorEventService
{
    public const EVENT_PRODUCT_VIEWED = 'product_viewed';
    public const EVENT_PACKAGE_SELECTED = 'package_selected';
    public const EVENT_ADDED_TO_CART = 'added_to_cart';
    public const EVENT_CART_UPDATED = 'cart_updated';
    public const EVENT_CART_REMOVED = 'cart_removed';
    public const EVENT_CHECKOUT_STARTED = 'checkout_started';
    public const EVENT_COUPON_ATTEMPTED = 'coupon_attempted';
    public const EVENT_COUPON_APPLIED = 'coupon_applied';
    public const EVENT_ORDER_SUBMITTED = 'order_submitted';

    private const SENSITIVE_METADATA_KEYS = [
        'phone',
        'customer_phone',
        'alternative_phone',
        'email',
        'customer_email',
        'address',
        'area',
        'district',
        'name',
        'customer_name',
        'ip',
        'user_agent',
        'otp',
        'token',
        'password',
    ];

    public function __construct(private SettingService $settingService) {}

    public static function allowedEventTypes(): array
    {
        return [
            self::EVENT_PRODUCT_VIEWED,
            self::EVENT_PACKAGE_SELECTED,
            self::EVENT_ADDED_TO_CART,
            self::EVENT_CART_UPDATED,
            self::EVENT_CART_REMOVED,
            self::EVENT_CHECKOUT_STARTED,
            self::EVENT_COUPON_ATTEMPTED,
            self::EVENT_COUPON_APPLIED,
            self::EVENT_ORDER_SUBMITTED,
        ];
    }

    public function record(Request $request, string $eventType, array $context = []): ?CustomerBehaviorEvent
    {
        try {
            if (! in_array($eventType, self::allowedEventTypes(), true)) {
                return null;
            }

            return CustomerBehaviorEvent::create([
                'customer_id' => $this->customerId($request, $context),
                'session_id' => $this->sessionHash($request),
                'order_id' => $this->nullableInt($context['order_id'] ?? null),
                'product_id' => $this->nullableInt($context['product_id'] ?? null),
                'product_variant_id' => $this->nullableInt($context['product_variant_id'] ?? $context['variant_id'] ?? null),
                'coupon_id' => $this->nullableInt($context['coupon_id'] ?? null),
                'event_type' => $eventType,
                'source' => $this->source($request, $context),
                'url' => $this->sanitizeUrl($context['url'] ?? $request->fullUrl()),
                'referrer' => $this->sanitizeUrl($context['referrer'] ?? $request->headers->get('referer')),
                'user_agent_hash' => $this->hashValue($request->userAgent()),
                'ip_hash' => $this->hashValue($request->ip()),
                'metadata' => $this->sanitizeMetadata($context['metadata'] ?? []),
                'occurred_at' => $context['occurred_at'] ?? now(),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Customer behavior event logging failed', [
                'event_type' => $eventType,
                'customer_id' => $this->safeLogId($context['customer_id'] ?? null),
                'order_id' => $this->safeLogId($context['order_id'] ?? null),
                'product_id' => $this->safeLogId($context['product_id'] ?? null),
                'product_variant_id' => $this->safeLogId($context['product_variant_id'] ?? $context['variant_id'] ?? null),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function cleanupOldEvents(): int
    {
        $retentionDays = (int) $this->settingService->get('general', 'behavior_event_retention_days', 365);

        if ($retentionDays <= 0) {
            return 0;
        }

        return CustomerBehaviorEvent::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    public function linkSessionToCustomer(Request $request, Customer|int|null $customer, ?Order $order = null): int
    {
        try {
            $customerId = $customer instanceof Customer ? $customer->id : $this->nullableInt($customer);
            $sessionHash = $this->sessionHash($request);

            if (! $customerId || ! $sessionHash) {
                return 0;
            }

            return CustomerBehaviorEvent::query()
                ->where('session_id', $sessionHash)
                ->whereNull('customer_id')
                ->update([
                    'customer_id' => $customerId,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            logger()->warning('Customer behavior session linking failed', [
                'customer_id' => $customer instanceof Customer ? $customer->id : $this->safeLogId($customer),
                'order_id' => $order?->id,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    public function sessionHashForRequest(Request $request): ?string
    {
        return $this->sessionHash($request);
    }

    public function commerceFunnel(): array
    {
        $events = CustomerBehaviorEvent::query()
            ->select('event_type')
            ->selectRaw('COUNT(*) as total')
            ->whereIn('event_type', [
                self::EVENT_PRODUCT_VIEWED,
                self::EVENT_ADDED_TO_CART,
                self::EVENT_CHECKOUT_STARTED,
                self::EVENT_ORDER_SUBMITTED,
            ])
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return [
            'product_views' => (int) ($events[self::EVENT_PRODUCT_VIEWED] ?? 0),
            'cart_adds' => (int) ($events[self::EVENT_ADDED_TO_CART] ?? 0),
            'checkout_started' => (int) ($events[self::EVENT_CHECKOUT_STARTED] ?? 0),
            'order_submitted' => (int) ($events[self::EVENT_ORDER_SUBMITTED] ?? 0),
        ];
    }

    public function productFunnel(int $limit = 12): array
    {
        $eventRows = CustomerBehaviorEvent::query()
            ->whereNotNull('product_id')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as views", [self::EVENT_PRODUCT_VIEWED])
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as cart_adds", [self::EVENT_ADDED_TO_CART])
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as checkout_starts", [self::EVENT_CHECKOUT_STARTED])
            ->groupBy('product_id')
            ->orderByDesc('views')
            ->orderByDesc('cart_adds')
            ->limit($limit)
            ->get()
            ->keyBy('product_id');

        $orderRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['cancelled', 'returned'])
            ->select('order_items.product_id')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy('product_id');

        $productIds = $eventRows->keys()
            ->merge($orderRows->keys())
            ->filter()
            ->unique()
            ->take($limit)
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->pluck('name', 'id');

        return $productIds
            ->map(function ($productId) use ($eventRows, $orderRows, $products): array {
                $events = $eventRows->get($productId);
                $orders = $orderRows->get($productId);
                $views = (int) ($events?->views ?? 0);
                $cartAdds = (int) ($events?->cart_adds ?? 0);
                $checkoutStarts = (int) ($events?->checkout_starts ?? 0);
                $orderCount = (int) ($orders?->orders_count ?? 0);

                return [
                    'product_id' => (int) $productId,
                    'product_name' => (string) ($products[$productId] ?? 'Product #'.$productId),
                    'views' => $views,
                    'add_to_cart' => $cartAdds,
                    'checkout_starts' => $checkoutStarts,
                    'orders' => $orderCount,
                    'conversion_rate' => $views > 0 ? round(($orderCount / $views) * 100, 2) : 0.0,
                    'best_package' => $this->bestPackageForProduct((int) $productId),
                    'drop_off_signal' => $this->dropOffSignal($views, $cartAdds, $checkoutStarts, $orderCount),
                ];
            })
            ->values()
            ->all();
    }

    public function sourceSummary(): array
    {
        $rows = CustomerBehaviorEvent::query()
            ->selectRaw("COALESCE(NULLIF(source, ''), 'Unknown') as source_name")
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as product_views", [self::EVENT_PRODUCT_VIEWED])
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as cart_adds", [self::EVENT_ADDED_TO_CART])
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as checkout_starts", [self::EVENT_CHECKOUT_STARTED])
            ->selectRaw("SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as orders_submitted", [self::EVENT_ORDER_SUBMITTED])
            ->groupBy('source_name')
            ->orderByDesc('orders_submitted')
            ->orderByDesc('checkout_starts')
            ->limit(12)
            ->get();

        return $rows->map(function ($row): array {
            $checkoutStarts = (int) $row->checkout_starts;
            $orders = (int) $row->orders_submitted;

            return [
                'source' => (string) $row->source_name,
                'product_views' => (int) $row->product_views,
                'cart_adds' => (int) $row->cart_adds,
                'checkout_starts' => $checkoutStarts,
                'orders_submitted' => $orders,
                'checkout_to_order_rate' => $checkoutStarts > 0 ? round(($orders / $checkoutStarts) * 100, 2) : 0.0,
            ];
        })->all();
    }

    public function customerJourney(Customer $customer): array
    {
        $events = $customer->behaviorEvents()
            ->with(['product:id,name,slug', 'variant:id,name', 'order:id,order_number,total,status', 'coupon:id,code'])
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        return [
            'recent_events' => $events,
            'recently_viewed_products' => $this->uniqueProducts($events, self::EVENT_PRODUCT_VIEWED),
            'added_to_cart_products' => $this->uniqueProducts($events, self::EVENT_ADDED_TO_CART),
            'checkout_attempts' => $events->where('event_type', self::EVENT_CHECKOUT_STARTED)->count(),
            'coupon_attempts' => $events->where('event_type', self::EVENT_COUPON_ATTEMPTED)->count(),
            'orders_submitted' => $events->where('event_type', self::EVENT_ORDER_SUBMITTED)->count(),
            'timeline' => $events->map(fn (CustomerBehaviorEvent $event): array => [
                'type' => 'Behavior',
                'title' => str_replace('_', ' ', ucfirst($event->event_type)),
                'body' => $this->eventBody($event),
                'at' => $event->occurred_at,
                'tone' => in_array($event->event_type, [self::EVENT_ORDER_SUBMITTED, self::EVENT_COUPON_APPLIED], true) ? 'success' : 'info',
                'url' => $event->order && Route::has('orders.show') ? route('orders.show', $event->order) : null,
            ])->all(),
        ];
    }

    public function recoverySignals(CheckoutRecovery $recovery): array
    {
        if (! $recovery->product_id && ! $recovery->variant_id) {
            return [
                'events' => collect(),
                'last_viewed_product' => null,
                'last_cart_event' => null,
                'checkout_started_at' => null,
                'package_selected' => null,
                'fresh' => false,
            ];
        }

        $events = CustomerBehaviorEvent::query()
            ->with(['product:id,name', 'variant:id,name'])
            ->where(function ($query) use ($recovery): void {
                if ($recovery->product_id) {
                    $query->orWhere('product_id', $recovery->product_id);
                }

                if ($recovery->variant_id) {
                    $query->orWhere('product_variant_id', $recovery->variant_id);
                }
            })
            ->where('occurred_at', '>=', now()->subDays(14))
            ->latest('occurred_at')
            ->limit(12)
            ->get();

        $lastEvent = $events->first();

        return [
            'events' => $events,
            'last_viewed_product' => $events->firstWhere('event_type', self::EVENT_PRODUCT_VIEWED)?->product?->name,
            'last_cart_event' => $events->first(fn (CustomerBehaviorEvent $event): bool => in_array($event->event_type, [
                self::EVENT_ADDED_TO_CART,
                self::EVENT_CART_UPDATED,
                self::EVENT_CART_REMOVED,
            ], true)),
            'checkout_started_at' => $events->firstWhere('event_type', self::EVENT_CHECKOUT_STARTED)?->occurred_at,
            'package_selected' => $events->firstWhere('event_type', self::EVENT_PACKAGE_SELECTED)?->variant?->name,
            'fresh' => $lastEvent?->occurred_at instanceof Carbon && $lastEvent->occurred_at->greaterThanOrEqualTo(now()->subHours(24)),
        ];
    }

    protected function customerId(Request $request, array $context): ?int
    {
        $customerId = $this->nullableInt($context['customer_id'] ?? null)
            ?: $this->nullableInt($request->hasSession() ? $request->session()->get('customer_id') : null);

        if ($customerId) {
            return $customerId;
        }

        $orderId = $this->nullableInt($context['order_id'] ?? null);

        if (! $orderId) {
            return null;
        }

        return Order::query()->whereKey($orderId)->value('customer_id');
    }

    protected function sessionHash(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $this->hashValue($request->session()->getId());
    }

    protected function source(Request $request, array $context): ?string
    {
        $source = $context['source']
            ?? $request->query('utm_source')
            ?? $request->query('source')
            ?? $request->query('ref');

        $source = is_string($source) ? trim($source) : '';

        if ($source === '') {
            $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : '';

            $source = match (true) {
                str_contains($host, 'facebook') => 'facebook',
                str_contains($host, 'instagram') => 'instagram',
                str_contains($host, 'google') => 'google',
                str_contains($host, 'tiktok') => 'tiktok',
                str_contains($host, 'youtube') => 'youtube',
                str_contains($host, 'whatsapp') => 'whatsapp',
                default => 'unknown',
            };
        }

        $source = preg_replace('/[^a-zA-Z0-9_\- .]/', '', $source) ?: 'unknown';

        return strtolower(substr($source, 0, 120));
    }

    protected function sanitizeUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = [];

        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            $query = collect($query)
                ->reject(fn ($value, string|int $key): bool => is_string($key) && ($this->isSensitiveKey($key) || strtolower($key) === 'coupon_code'))
                ->map(fn ($value) => is_scalar($value) ? (string) $value : null)
                ->filter(fn ($value): bool => filled($value))
                ->take(10)
                ->all();
        }

        $safeUrl = $path.($query !== [] ? '?'.http_build_query($query) : '');

        return substr($safeUrl, 0, 1000);
    }

    protected function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn ($value, string|int $key): bool => ! is_string($key) || $this->isSensitiveKey($key))
            ->map(function ($value, string $key) {
                if ($key === 'coupon_code') {
                    return $this->hashValue(strtoupper(trim((string) $value)));
                }

                if (is_bool($value) || is_int($value) || is_float($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    return substr(strip_tags($value), 0, 240);
                }

                if (is_array($value)) {
                    return collect($value)
                        ->reject(fn ($nestedValue, string|int $nestedKey): bool => is_string($nestedKey) && $this->isSensitiveKey($nestedKey))
                        ->map(fn ($nestedValue) => is_scalar($nestedValue) ? substr(strip_tags((string) $nestedValue), 0, 120) : null)
                        ->filter(fn ($nestedValue): bool => $nestedValue !== null && $nestedValue !== '')
                        ->take(12)
                        ->all();
                }

                return null;
            })
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->take(30)
            ->all();
    }

    protected function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_METADATA_KEYS as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    protected function hashValue(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    protected function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    protected function safeLogId(mixed $value): ?int
    {
        return $this->nullableInt($value);
    }

    protected function bestPackageForProduct(int $productId): ?string
    {
        $variantId = CustomerBehaviorEvent::query()
            ->where('product_id', $productId)
            ->whereNotNull('product_variant_id')
            ->whereIn('event_type', [self::EVENT_PACKAGE_SELECTED, self::EVENT_ADDED_TO_CART, self::EVENT_CHECKOUT_STARTED])
            ->select('product_variant_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('product_variant_id')
            ->orderByDesc('total')
            ->value('product_variant_id');

        if (! $variantId) {
            return null;
        }

        return DB::table('product_variants')->where('id', $variantId)->value('name');
    }

    protected function dropOffSignal(int $views, int $cartAdds, int $checkoutStarts, int $orders): string
    {
        if ($views > 0 && $cartAdds === 0) {
            return 'View to cart drop-off';
        }

        if ($cartAdds > 0 && $checkoutStarts === 0) {
            return 'Cart to checkout drop-off';
        }

        if ($checkoutStarts > 0 && $orders === 0) {
            return 'Checkout to order drop-off';
        }

        return 'Healthy or insufficient data';
    }

    protected function uniqueProducts(EloquentCollection $events, string $eventType): array
    {
        return $events
            ->where('event_type', $eventType)
            ->filter(fn (CustomerBehaviorEvent $event): bool => $event->product !== null)
            ->unique('product_id')
            ->take(8)
            ->map(fn (CustomerBehaviorEvent $event): array => [
                'id' => $event->product_id,
                'name' => $event->product?->name,
                'package' => $event->variant?->name,
                'at' => $event->occurred_at,
                'url' => $event->product ? route('storefront.product.show', $event->product->slug) : null,
            ])
            ->values()
            ->all();
    }

    protected function eventBody(CustomerBehaviorEvent $event): string
    {
        $parts = [];

        if ($event->product?->name) {
            $parts[] = $event->product->name;
        }

        if ($event->variant?->name) {
            $parts[] = 'Package: '.$event->variant->name;
        }

        if ($event->order?->order_number) {
            $parts[] = 'Order: '.$event->order->order_number;
        }

        if ($event->coupon_id) {
            $parts[] = 'Coupon signal';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Storefront behavior event recorded.';
    }
}
