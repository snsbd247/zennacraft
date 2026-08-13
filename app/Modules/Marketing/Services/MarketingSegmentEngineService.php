<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Models\MarketingSegmentMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Shared\Services\PhoneService;
use App\Modules\Marketing\Support\SystemMarketingSegments;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketingSegmentEngineService
{
    public const SYSTEM_SEGMENTS = [
        'NEW_CUSTOMER',
        'REPEAT_CUSTOMER',
        'VIP_CUSTOMER',
        'HIGH_VALUE_CUSTOMER',
        'DORMANT_CUSTOMER',
        'AT_RISK_CUSTOMER',
        'BLACKLISTED_CUSTOMER',
        'FRAUD_RISK_CUSTOMER',
        'COUPON_LOVER',
        'ABANDONED_CHECKOUT_CUSTOMER',
    ];

    protected bool $systemSegmentsEnsured = false;

    public function __construct(
        private AuditService $auditService,
        private CacheService $cacheService,
        private PhoneService $phoneService,
    ) {}

    public function ensureSystemSegments(): void
    {
        if ($this->systemSegmentsEnsured) {
            return;
        }

        foreach ($this->systemSegmentDefinitions() as $segment) {
            $model = MarketingSegment::query()->firstOrNew(['slug' => $segment['slug']]);
            $model->fill($segment);

            if (! $model->exists || $model->isDirty()) {
                $model->save();
            }
        }

        $this->systemSegmentsEnsured = true;
    }

    public function evaluateCustomer(Customer $customer, string $trigger = 'manual'): array
    {
        $this->ensureSystemSegments();

        $customer->loadMissing(['segment', 'riskProfile']);
        $metrics = $this->customerMetrics($customer);
        $matchedSlugs = $this->matchedSlugs($customer, $metrics);
        $now = now();

        $segments = MarketingSegment::query()
            ->where('type', MarketingSegment::TYPE_SYSTEM)
            ->whereIn('slug', self::SYSTEM_SEGMENTS)
            ->get();

        foreach ($segments as $segment) {
            if (! $segment->active || ! in_array($segment->slug, $matchedSlugs, true)) {
                MarketingSegmentMembership::query()
                    ->where('marketing_segment_id', $segment->id)
                    ->where('customer_id', $customer->id)
                    ->get()
                    ->each
                    ->delete();

                continue;
            }

            $membership = MarketingSegmentMembership::query()->firstOrNew([
                'marketing_segment_id' => $segment->id,
                'customer_id' => $customer->id,
            ]);

            $membership->fill([
                'joined_at' => $membership->joined_at ?: $now,
                'last_evaluated_at' => $now,
            ])->save();
        }

        MarketingSegment::query()
            ->where('type', MarketingSegment::TYPE_SYSTEM)
            ->whereIn('slug', self::SYSTEM_SEGMENTS)
            ->update(['last_evaluated_at' => $now]);

        $this->cacheService->invalidateMarketingSegments();
        $this->logAudit('marketing.segment.customer.evaluate', [
            'customer_id' => $customer->id,
            'trigger' => $trigger,
            'count' => count($matchedSlugs),
        ]);

        return $matchedSlugs;
    }

    public function evaluateAll(string $trigger = 'manual'): int
    {
        $this->ensureSystemSegments();

        $count = 0;

        Customer::query()
            ->orderBy('id')
            ->chunkById(100, function ($customers) use (&$count, $trigger): void {
                foreach ($customers as $customer) {
                    $this->evaluateCustomer($customer, $trigger);
                    $count++;
                }
            });

        $this->cacheService->invalidateMarketingSegments();

        return $count;
    }

    public function segmentSummary(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::MARKETING_SEGMENT_SUMMARY,
            function (): array {
                $this->ensureSystemSegments();

                return MarketingSegment::query()
                    ->withCount('memberships')
                    ->orderBy('name')
                    ->get()
                    ->sortBy(fn (MarketingSegment $segment): int => array_search($segment->slug, self::SYSTEM_SEGMENTS, true) === false ? 999 : array_search($segment->slug, self::SYSTEM_SEGMENTS, true))
                    ->mapWithKeys(fn (MarketingSegment $segment): array => [
                        $segment->slug => [
                            'id' => $segment->id,
                            'name' => $segment->name,
                            'slug' => $segment->slug,
                            'description' => $segment->description,
                            'type' => $segment->type,
                            'active' => (bool) $segment->active,
                            'count' => (int) $segment->memberships_count,
                            'last_evaluated_at' => $segment->last_evaluated_at?->toDateTimeString(),
                        ],
                    ])
                    ->all();
            },
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::MARKETING_SEGMENTS_TAG]
        );
    }

    public function segmentCustomers(MarketingSegment $segment, int $perPage = 20): LengthAwarePaginator
    {
        return MarketingSegmentMembership::query()
            ->with(['customer.segment', 'customer.riskProfile'])
            ->where('marketing_segment_id', $segment->id)
            ->latest('joined_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function customerSegmentSlugs(Customer $customer): array
    {
        return $customer->marketingSegments()
            ->where('marketing_segments.active', true)
            ->pluck('marketing_segments.slug')
            ->all();
    }

    protected function matchedSlugs(Customer $customer, array $metrics): array
    {
        $slugs = [];

        if ($metrics['delivered_orders'] === 0) {
            $slugs[] = 'NEW_CUSTOMER';
        }

        if ($metrics['delivered_orders'] >= 2) {
            $slugs[] = 'REPEAT_CUSTOMER';
        }

        if (
            $customer->segment?->segment === 'VIP'
            || $metrics['delivered_orders'] >= 5
            || $metrics['lifetime_revenue'] >= $this->vipThreshold()
        ) {
            $slugs[] = 'VIP_CUSTOMER';
        }

        if ($metrics['lifetime_revenue'] >= $this->highValueThreshold()) {
            $slugs[] = 'HIGH_VALUE_CUSTOMER';
        }

        if ($metrics['delivered_orders'] >= 1 && $metrics['last_order_at'] && $metrics['last_order_at']->lte(now()->subDays(90))) {
            $slugs[] = 'DORMANT_CUSTOMER';
        }

        if ($customer->riskProfile?->risk_level === 'high' || $this->isRetentionAtRisk($metrics)) {
            $slugs[] = 'AT_RISK_CUSTOMER';
        }

        if ($metrics['blacklisted']) {
            $slugs[] = 'BLACKLISTED_CUSTOMER';
        }

        if ($metrics['fraud_risk']) {
            $slugs[] = 'FRAUD_RISK_CUSTOMER';
        }

        if ($metrics['coupon_usage_count'] >= 2) {
            $slugs[] = 'COUPON_LOVER';
        }

        if ($metrics['open_recoveries'] >= 1) {
            $slugs[] = 'ABANDONED_CHECKOUT_CUSTOMER';
        }

        return array_values(array_unique($slugs));
    }

    protected function customerMetrics(Customer $customer): array
    {
        $orderSummary = Order::query()
            ->where('customer_id', $customer->id)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as lifetime_revenue")
            ->selectRaw('MAX(created_at) as last_order_at')
            ->first();

        $phoneValues = $this->phoneService->lookupValues((string) $customer->phone);
        $blacklisted = CustomerBlacklist::query()
            ->where('active', true)
            ->where(function (Builder $query) use ($customer, $phoneValues): void {
                $query->where('customer_id', $customer->id);

                if ($phoneValues !== []) {
                    $query->orWhereIn(DB::raw($this->normalizedExpression('phone')), $phoneValues);
                }
            })
            ->exists();

        $fraudRisk = FraudEvent::query()
            ->where('customer_id', $customer->id)
            ->whereIn('severity', ['high', 'critical'])
            ->where('created_at', '>=', now()->subDays(90))
            ->exists();

        $couponUsageCount = CouponUsage::query()
            ->where('customer_id', $customer->id)
            ->count();

        $openRecoveries = $phoneValues === []
            ? 0
            : CheckoutRecovery::query()
                ->where('status', 'open')
                ->whereIn(DB::raw($this->normalizedExpression('customer_phone')), $phoneValues)
                ->count();

        return [
            'total_orders' => (int) ($orderSummary->total_orders ?? 0),
            'delivered_orders' => (int) ($orderSummary->delivered_orders ?? 0),
            'lifetime_revenue' => (float) ($orderSummary->lifetime_revenue ?? 0),
            'last_order_at' => $orderSummary?->last_order_at ? Carbon::parse($orderSummary->last_order_at) : null,
            'blacklisted' => $blacklisted,
            'fraud_risk' => $fraudRisk,
            'coupon_usage_count' => $couponUsageCount,
            'open_recoveries' => $openRecoveries,
        ];
    }

    protected function isRetentionAtRisk(array $metrics): bool
    {
        return $metrics['delivered_orders'] >= 1
            && $metrics['last_order_at']
            && $metrics['last_order_at']->lte(now()->subDays(30));
    }

    protected function normalizedExpression(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(TRIM({$column}), ' ', ''), '-', ''), '+', '')";
    }

    protected function vipThreshold(): float
    {
        return (float) config('marketing_segments.vip_lifetime_value', 10000);
    }

    protected function highValueThreshold(): float
    {
        return (float) config('marketing_segments.high_value_lifetime_value', 10000);
    }

    protected function systemSegmentDefinitions(): array
    {
        return SystemMarketingSegments::definitions();
    }

    protected function logAudit(string $action, array $metadata): void
    {
        try {
            $this->auditService->log(
                'marketing.segment',
                $action,
                'marketing',
                'Marketing segment event recorded.',
                $metadata
            );
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment audit logging failed', [
                'action' => $action,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
