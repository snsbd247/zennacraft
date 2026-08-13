<?php

namespace App\Modules\Customer\Services;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Checkout\Services\CartService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Shared\Services\PhoneService;
use App\Modules\Storefront\Services\StorefrontService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerPersonalizationService
{
    private const EVENT_WEIGHTS = [
        BehaviorEventService::EVENT_PRODUCT_VIEWED => 1,
        BehaviorEventService::EVENT_PACKAGE_SELECTED => 2,
        BehaviorEventService::EVENT_ADDED_TO_CART => 4,
        BehaviorEventService::EVENT_CART_UPDATED => 3,
        BehaviorEventService::EVENT_CHECKOUT_STARTED => 6,
        BehaviorEventService::EVENT_COUPON_ATTEMPTED => 2,
        BehaviorEventService::EVENT_COUPON_APPLIED => 4,
        BehaviorEventService::EVENT_ORDER_SUBMITTED => 9,
    ];

    public function __construct(
        private BehaviorEventService $behaviorEventService,
        private StorefrontService $storefrontService,
        private CartService $cartService,
        private PhoneService $phoneService,
        private CacheService $cacheService,
    ) {}

    public function storefrontBlocks(Request $request, ?Customer $customer = null, ?Product $currentProduct = null): array
    {
        $customer ??= $this->customerFromRequest($request);
        $events = $this->eventsForRequest($request, $customer, 80);
        $profile = $this->profileFromEvents($events, $customer);
        $excludeIds = collect([$currentProduct?->id])
            ->filter()
            ->merge($events->pluck('product_id')->filter()->take(12))
            ->unique()
            ->values()
            ->all();
        $recentlyViewed = $this->productsFromEvents($events, BehaviorEventService::EVENT_PRODUCT_VIEWED, $currentProduct?->id, 8);
        $continueShopping = $this->continueShoppingProducts($events, $currentProduct?->id, 8);
        $recommended = $this->recommendedProducts($profile, $excludeIds, 8);

        return [
            'profile' => $profile,
            'recently_viewed' => $recentlyViewed,
            'continue_shopping' => $continueShopping,
            'recommended' => $recommended->isNotEmpty() ? $recommended : $this->fallbackProducts($excludeIds, 8),
            'favorite_category' => $profile['favorite_categories'][0] ?? null,
            'has_behavior' => $events->isNotEmpty(),
        ];
    }

    public function productDetailBlocks(Request $request, Product $product): array
    {
        $blocks = $this->storefrontBlocks($request, null, $product);
        $similar = $this->sameCategoryProducts($product, 8);
        $continueWith = $this->customersOftenContinueWith($product, 4);

        return array_merge($blocks, [
            'similar_products' => $similar,
            'continue_with' => $continueWith,
            'package_suggestions' => $product->relationLoaded('variants')
                ? $product->variants->where('status', 'active')->where('show_on_storefront', true)->take(4)->values()
                : collect(),
        ]);
    }

    public function customerDashboard(Customer $customer, Request $request): array
    {
        $events = $this->eventsForRequest($request, $customer, 100);
        $profile = $this->profileFromEvents($events, $customer);
        $recentlyViewed = $this->productsFromEvents($events, BehaviorEventService::EVENT_PRODUCT_VIEWED, null, 8);
        $continueShopping = $this->continueShoppingProducts($events, null, 8);
        $recommended = $this->recommendedProducts($profile, $recentlyViewed->pluck('id')->all(), 8);

        return [
            'profile' => $profile,
            'recently_viewed' => $recentlyViewed,
            'continue_shopping' => $continueShopping,
            'recommended' => $recommended->isNotEmpty() ? $recommended : $this->fallbackProducts($recentlyViewed->pluck('id')->all(), 8),
            'coupon_suggestions' => $this->validCoupons(3),
            'last_order' => $customer->orders()->latest()->first(),
        ];
    }

    public function customer360(Customer $customer): array
    {
        $events = $this->eventsForCustomer($customer, 120);
        $profile = $this->profileFromEvents($events, $customer);

        return [
            'profile' => $profile,
            'recommended_action' => $this->recommendedAction($profile, $customer),
            'last_viewed_product' => $events->firstWhere('event_type', BehaviorEventService::EVENT_PRODUCT_VIEWED)?->product,
            'last_cart_product' => $events->first(fn (CustomerBehaviorEvent $event): bool => in_array($event->event_type, [
                BehaviorEventService::EVENT_ADDED_TO_CART,
                BehaviorEventService::EVENT_CART_UPDATED,
                BehaviorEventService::EVENT_CHECKOUT_STARTED,
            ], true))?->product,
        ];
    }

    public function recoveryProfile(CheckoutRecovery $recovery): array
    {
        $customer = $this->customerForRecovery($recovery);
        $events = $customer
            ? $this->eventsForCustomer($customer, 100)
            : $this->behaviorEventService->recoverySignals($recovery)['events'];
        $profile = $this->profileFromEvents($events, $customer);
        $fallbackProduct = $recovery->product ? [
            'id' => $recovery->product->id,
            'name' => $recovery->product->name,
            'score' => 1,
        ] : null;

        return [
            'profile' => $profile,
            'intent' => $profile['intent'],
            'favorite_category' => $profile['favorite_categories'][0] ?? $recovery->product?->category?->only(['id', 'name']),
            'favorite_product' => $profile['favorite_products'][0] ?? $fallbackProduct,
            'recommended_action' => $this->recommendedAction($profile, $customer, true),
            'suggested_coupon' => $profile['price_sensitivity'] === 'high' ? $this->validCoupons(1)->first() : null,
            'last_signal' => $events->first(),
        ];
    }

    public function marketingIntentSummary(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::CUSTOMER_PERSONALIZATION_MARKETING_INTENT,
            function (): array {
                $events = CustomerBehaviorEvent::query()
                    ->with(['customer:id,name,last_order_at', 'product:id,name,category_id', 'product.category:id,name'])
                    ->whereNotNull('customer_id')
                    ->latest('occurred_at')
                    ->limit(1000)
                    ->get()
                    ->groupBy('customer_id');
                $hot = 0;
                $warm = 0;
                $couponSensitive = 0;
                $dormantHighIntent = 0;
                $categoryScores = [];

                foreach ($events as $customerEvents) {
                    $profile = $this->profileFromEvents($customerEvents);
                    $label = $profile['intent']['label'];

                    if ($label === 'Hot') {
                        $hot++;
                    } elseif ($label === 'Warm') {
                        $warm++;
                    }

                    if (in_array($profile['price_sensitivity'], ['medium', 'high'], true)) {
                        $couponSensitive++;
                    }

                    $customer = $customerEvents->first()?->customer;
                    if ($label === 'Hot' && $customer?->last_order_at && $customer->last_order_at->lt(now()->subDays(60))) {
                        $dormantHighIntent++;
                    }

                    foreach ($profile['favorite_categories'] as $category) {
                        $key = $category['name'];
                        $categoryScores[$key] = ($categoryScores[$key] ?? 0) + $category['score'];
                    }
                }

                arsort($categoryScores);

                return [
                    'hot_intent_customers' => $hot,
                    'warm_intent_customers' => $warm,
                    'coupon_sensitive_customers' => $couponSensitive,
                    'dormant_high_intent_customers' => $dormantHighIntent,
                    'category_interest_groups' => collect($categoryScores)
                        ->take(6)
                        ->map(fn (int|float $score, string $name): array => ['name' => $name, 'score' => (int) $score])
                        ->values()
                        ->all(),
                    'is_rule_based' => true,
                ];
            },
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::ANALYTICS_TAG]
        );
    }

    public function intentForCustomer(Customer $customer): array
    {
        return $this->profileFromEvents($this->eventsForCustomer($customer, 100), $customer)['intent'];
    }

    public function customerIntentMatches(Customer $customer, string $expected): bool
    {
        return strtolower($this->intentForCustomer($customer)['label']) === strtolower($expected);
    }

    public function profileFromEvents(Collection|EloquentCollection $events, ?Customer $customer = null): array
    {
        $events = collect($events);
        $categoryScores = [];
        $productScores = [];
        $packageScores = [];
        $productViewCounts = [];
        $couponAttempts = $events->where('event_type', BehaviorEventService::EVENT_COUPON_ATTEMPTED)->count();
        $couponApplied = $events->where('event_type', BehaviorEventService::EVENT_COUPON_APPLIED)->count();

        foreach ($events as $event) {
            if (! $event instanceof CustomerBehaviorEvent) {
                continue;
            }

            $weight = self::EVENT_WEIGHTS[$event->event_type] ?? 1;

            if ($event->product) {
                $productKey = (int) $event->product_id;
                $productScores[$productKey] ??= [
                    'id' => $event->product_id,
                    'name' => $event->product->name,
                    'score' => 0,
                ];
                $productScores[$productKey]['score'] += $weight;

                if ($event->event_type === BehaviorEventService::EVENT_PRODUCT_VIEWED) {
                    $productViewCounts[$productKey] = ($productViewCounts[$productKey] ?? 0) + 1;
                }

                if ($event->product->category) {
                    $categoryKey = (int) $event->product->category_id;
                    $categoryScores[$categoryKey] ??= [
                        'id' => $event->product->category_id,
                        'name' => $event->product->category->name,
                        'score' => 0,
                    ];
                    $categoryScores[$categoryKey]['score'] += $weight;
                }
            }

            if ($event->variant) {
                $package = $event->variant->package_type ?: $event->variant->name;
                if ($package) {
                    $packageScores[$package] = ($packageScores[$package] ?? 0) + $weight;
                }
            }
        }

        $intent = $this->intentFromEvents($events, $productViewCounts);
        $priceSensitivity = $couponApplied >= 2 || $couponAttempts >= 3
            ? 'high'
            : (($couponApplied + $couponAttempts) > 0 ? 'medium' : 'low');

        return [
            'favorite_categories' => $this->sortedScoreRows($categoryScores),
            'favorite_products' => $this->sortedScoreRows($productScores),
            'favorite_package_types' => collect($packageScores)
                ->sortDesc()
                ->take(5)
                ->map(fn (int|float $score, string $name): array => ['name' => $name, 'score' => (int) $score])
                ->values()
                ->all(),
            'price_sensitivity' => $priceSensitivity,
            'coupon_interest' => [
                'attempts' => $couponAttempts,
                'applied' => $couponApplied,
                'label' => $priceSensitivity === 'high' ? 'Coupon sensitive' : ($priceSensitivity === 'medium' ? 'Coupon curious' : 'Low coupon signal'),
            ],
            'intent' => $intent,
            'last_active_at' => $events->max('occurred_at'),
            'repeat_behavior_signal' => collect($productViewCounts)->max() >= 2 ? 'Repeat product interest' : 'No repeat view yet',
            'event_count' => $events->count(),
            'customer_id' => $customer?->id,
        ];
    }

    protected function intentFromEvents(Collection $events, array $productViewCounts = []): array
    {
        $score = 0;
        $reasons = [];
        $recent = fn (string $eventType, int $days): bool => $events
            ->where('event_type', $eventType)
            ->contains(fn (CustomerBehaviorEvent $event): bool => $event->occurred_at?->gte(now()->subDays($days)) ?? false);

        if ($recent(BehaviorEventService::EVENT_CHECKOUT_STARTED, 3)) {
            $score += 40;
            $reasons[] = 'Checkout started recently';
        }

        if ($recent(BehaviorEventService::EVENT_ADDED_TO_CART, 3) || $recent(BehaviorEventService::EVENT_CART_UPDATED, 3)) {
            $score += 30;
            $reasons[] = 'Cart activity is fresh';
        }

        if ($recent(BehaviorEventService::EVENT_COUPON_APPLIED, 7)) {
            $score += 15;
            $reasons[] = 'Coupon applied';
        }

        if ($recent(BehaviorEventService::EVENT_PACKAGE_SELECTED, 7)) {
            $score += 15;
            $reasons[] = 'Package selected';
        }

        if ($recent(BehaviorEventService::EVENT_PRODUCT_VIEWED, 7)) {
            $score += 10;
            $reasons[] = 'Product viewed recently';
        }

        if (collect($productViewCounts)->max() >= 2) {
            $score += 15;
            $reasons[] = 'Repeat product views';
        }

        if ($recent(BehaviorEventService::EVENT_ORDER_SUBMITTED, 14)) {
            $score += 10;
            $reasons[] = 'Recent purchase signal';
        }

        if ($events->isEmpty()) {
            $reasons[] = 'No recent behavior data';
        }

        $score = max(0, min(100, $score));
        $label = $score >= 70 ? 'Hot' : ($score >= 35 ? 'Warm' : 'Cold');

        return [
            'score' => $score,
            'label' => $label,
            'reason' => implode(', ', array_slice($reasons, 0, 4)) ?: 'Limited behavior signal',
        ];
    }

    protected function eventsForRequest(Request $request, ?Customer $customer, int $limit): EloquentCollection
    {
        $sessionHash = $this->behaviorEventService->sessionHashForRequest($request);

        if (! $customer && ! $sessionHash) {
            return new EloquentCollection();
        }

        return CustomerBehaviorEvent::query()
            ->with([
                'customer:id,name,last_order_at',
                'product:id,name,slug,category_id,thumbnail_id,status,short_description,price,compare_price',
                'product.thumbnail',
                'product.category:id,name,slug,status',
                'variant:id,name,package_type',
                'order:id,order_number,status,total',
                'coupon:id,code',
            ])
            ->where(function ($query) use ($customer, $sessionHash): void {
                if ($customer) {
                    $query->orWhere('customer_id', $customer->id);
                }

                if ($sessionHash) {
                    $query->orWhere('session_id', $sessionHash);
                }
            })
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }

    protected function eventsForCustomer(Customer $customer, int $limit): EloquentCollection
    {
        return CustomerBehaviorEvent::query()
            ->with([
                'product:id,name,slug,category_id,thumbnail_id,status,short_description,price,compare_price',
                'product.thumbnail',
                'product.category:id,name,slug,status',
                'variant:id,name,package_type',
                'order:id,order_number,status,total',
                'coupon:id,code',
            ])
            ->where('customer_id', $customer->id)
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }

    protected function productsFromEvents(Collection|EloquentCollection $events, string $eventType, ?int $excludeProductId = null, int $limit = 8): EloquentCollection
    {
        $ids = collect($events)
            ->where('event_type', $eventType)
            ->pluck('product_id')
            ->filter()
            ->reject(fn ($id): bool => $excludeProductId !== null && (int) $id === $excludeProductId)
            ->unique()
            ->take($limit)
            ->values();

        return $this->productsByIds($ids->all(), $limit);
    }

    protected function continueShoppingProducts(Collection|EloquentCollection $events, ?int $excludeProductId = null, int $limit = 8): EloquentCollection
    {
        $cartProductIds = $this->cartService->items()
            ->pluck('product_id')
            ->filter()
            ->all();
        $eventProductIds = collect($events)
            ->whereIn('event_type', [
                BehaviorEventService::EVENT_ADDED_TO_CART,
                BehaviorEventService::EVENT_CART_UPDATED,
                BehaviorEventService::EVENT_CHECKOUT_STARTED,
                BehaviorEventService::EVENT_PACKAGE_SELECTED,
            ])
            ->pluck('product_id')
            ->filter()
            ->all();
        $ids = collect($cartProductIds)
            ->merge($eventProductIds)
            ->reject(fn ($id): bool => $excludeProductId !== null && (int) $id === $excludeProductId)
            ->unique()
            ->take($limit)
            ->values();

        return $this->productsByIds($ids->all(), $limit);
    }

    protected function recommendedProducts(array $profile, array $excludeIds = [], int $limit = 8): EloquentCollection
    {
        $categoryIds = collect($profile['favorite_categories'] ?? [])->pluck('id')->filter()->take(3)->all();

        if ($categoryIds === []) {
            return new EloquentCollection();
        }

        return Product::query()
            ->with($this->productRelations())
            ->where('status', 'active')
            ->whereIn('category_id', $categoryIds)
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function fallbackProducts(array $excludeIds = [], int $limit = 8): EloquentCollection
    {
        $products = $this->storefrontService->topSellingProducts($limit + count($excludeIds))
            ->reject(fn (Product $product): bool => in_array((int) $product->id, array_map('intval', $excludeIds), true))
            ->take($limit)
            ->values();

        return new EloquentCollection($products->all());
    }

    protected function sameCategoryProducts(Product $product, int $limit): EloquentCollection
    {
        if (! $product->category_id) {
            return new EloquentCollection();
        }

        return Product::query()
            ->with($this->productRelations())
            ->where('status', 'active')
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function customersOftenContinueWith(Product $product, int $limit): EloquentCollection
    {
        $orderIds = DB::table('order_items')
            ->where('product_id', $product->id)
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return new EloquentCollection();
        }

        $productIds = DB::table('order_items')
            ->select('product_id')
            ->selectRaw('SUM(quantity) as sold_quantity')
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('product_id')
            ->where('product_id', '!=', $product->id)
            ->groupBy('product_id')
            ->orderByDesc('sold_quantity')
            ->limit($limit)
            ->pluck('product_id')
            ->all();

        return $this->productsByIds($productIds, $limit);
    }

    protected function productsByIds(array $ids, int $limit): EloquentCollection
    {
        $ids = collect($ids)->filter()->map(fn ($id): int => (int) $id)->unique()->take($limit)->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection();
        }

        $products = Product::query()
            ->with($this->productRelations())
            ->where('status', 'active')
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (Product $product): int|false => $ids->search((int) $product->id))
            ->values();

        return new EloquentCollection($products->all());
    }

    protected function validCoupons(int $limit): EloquentCollection
    {
        return Coupon::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function sortedScoreRows(array $rows): array
    {
        return collect($rows)
            ->sortByDesc('score')
            ->take(5)
            ->values()
            ->all();
    }

    protected function recommendedAction(array $profile, ?Customer $customer = null, bool $recoveryContext = false): string
    {
        $intent = $profile['intent']['label'] ?? 'Cold';

        if ($intent === 'Hot' && $recoveryContext) {
            return 'Call';
        }

        if ($intent === 'Hot') {
            return 'Recovery Message';
        }

        if (($profile['price_sensitivity'] ?? 'low') === 'high') {
            return 'Send Coupon';
        }

        if ($customer?->last_order_at && $customer->last_order_at->lt(now()->subDays(90)) && in_array($intent, ['Hot', 'Warm'], true)) {
            return 'Winback Campaign';
        }

        if ($intent === 'Warm') {
            return 'Recovery Message';
        }

        return 'No Action';
    }

    protected function customerForRecovery(CheckoutRecovery $recovery): ?Customer
    {
        if (! filled($recovery->customer_phone)) {
            return null;
        }

        $values = $this->phoneService->lookupValues((string) $recovery->customer_phone);

        if ($values === []) {
            return null;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $expression = "REPLACE(REPLACE(REPLACE(TRIM(phone), ' ', ''), '-', ''), '+', '')";

        return Customer::query()
            ->whereRaw($expression.' in ('.$placeholders.')', $values)
            ->first();
    }

    protected function customerFromRequest(Request $request): ?Customer
    {
        $customerId = $request->hasSession() ? $request->session()->get('customer_id') : null;

        return $customerId ? Customer::query()->find($customerId) : null;
    }

    protected function productRelations(): array
    {
        return [
            'thumbnail',
            'category',
            'variants' => fn ($query) => $query->where('status', 'active')->where('show_on_storefront', true),
        ];
    }
}
