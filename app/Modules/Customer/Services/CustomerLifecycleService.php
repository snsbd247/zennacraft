<?php

namespace App\Modules\Customer\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class CustomerLifecycleService
{
    /**
     * Same pattern as CustomerRetentionService::paginate() — takes an
     * already-computed lifecycleCustomers() collection so the caller can
     * derive the summary, reactivation candidates, and this paginated
     * slice from a single full computation. lifecycle_stage is computed
     * in PHP (stage()), not a stored column, so this bounds the response
     * via forPage() + a manually built LengthAwarePaginator rather than a
     * SQL LIMIT/OFFSET.
     */
    public function paginate(Collection $profiles, int $perPage = 20): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage() ?: 1;

        return new LengthAwarePaginatorImpl(
            $profiles->forPage($page, $perPage)->values(),
            $profiles->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public const STAGES = [
        'NEW',
        'FIRST_PURCHASE',
        'REPEAT',
        'LOYAL',
        'AT_RISK',
        'CHURNED',
    ];

    public function __construct(
        private CustomerRetentionService $retentionService,
        private CacheService $cacheService,
    ) {}

    public function lifecycleProfile(Customer $customer): array
    {
        $customer->loadMissing(['segment', 'riskProfile']);

        $deliveredOrders = $this->deliveredOrders($customer);
        $retentionProfile = $this->retentionService->profile($customer);
        $segment = $retentionProfile['segment'];
        $retentionStatus = $retentionProfile['retention_status'];
        $stage = $this->stage($deliveredOrders, $retentionStatus, $segment);
        $reactivationCandidate = $this->isReactivationCandidate($deliveredOrders, $retentionStatus);

        return [
            'customer' => $customer,
            'lifecycle_stage' => $stage,
            'delivered_orders' => $deliveredOrders,
            'segment' => $segment,
            'retention_status' => $retentionStatus,
            'risk_level' => $retentionProfile['risk_level'],
            'lifetime_value' => $retentionProfile['lifetime_value'],
            'last_order_at' => $retentionProfile['last_order_at'],
            'days_since_last_order' => $retentionProfile['days_since_last_order'],
            'reactivation_candidate' => $reactivationCandidate,
        ];
    }

    public function lifecycleCustomers(): Collection
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::CUSTOMER_LIFECYCLE_PROFILES,
            fn () => Customer::query()
                ->with(['segment', 'riskProfile'])
                ->withCount(['orders as delivered_orders_count' => fn ($query) => $query->where('status', 'delivered')])
                ->withMax('orders as latest_order_at', 'created_at')
                ->withSum(['orders as lifetime_value' => fn ($query) => $query->where('status', 'delivered')], 'total')
                ->get()
                ->map(fn (Customer $customer) => $this->lifecycleProfile($customer))
                ->sortBy([
                    ['lifecycle_stage', 'asc'],
                    ['lifetime_value', 'desc'],
                ])
                ->values(),
            CacheService::SHORT_TTL
        );
    }

    public function lifecycleSummary(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::CUSTOMER_LIFECYCLE_SUMMARY,
            fn () => $this->summaryFromProfiles($this->lifecycleCustomers()),
            CacheService::SHORT_TTL
        );
    }

    public function summaryFromProfiles(Collection $profiles): array
    {
        $summary = collect(self::STAGES)
            ->mapWithKeys(fn (string $stage) => [$stage => $profiles->where('lifecycle_stage', $stage)->count()])
            ->all();

        $summary['REACTIVATION'] = $profiles->where('reactivation_candidate', true)->count();

        return $summary;
    }

    public function reactivationCandidates(): Collection
    {
        return $this->reactivationCandidatesFromProfiles($this->lifecycleCustomers());
    }

    public function reactivationCandidatesFromProfiles(Collection $profiles): Collection
    {
        return $profiles
            ->where('reactivation_candidate', true)
            ->sort(fn (array $first, array $second) => $second['lifetime_value'] <=> $first['lifetime_value']
                ?: ($first['last_order_at']?->timestamp ?? PHP_INT_MAX) <=> ($second['last_order_at']?->timestamp ?? PHP_INT_MAX))
            ->values();
    }

    protected function deliveredOrders(Customer $customer): int
    {
        if ($customer->delivered_orders_count !== null) {
            return (int) $customer->delivered_orders_count;
        }

        return (int) $customer->orders()
            ->where('status', 'delivered')
            ->count();
    }

    protected function stage(int $deliveredOrders, string $retentionStatus, ?string $segment): string
    {
        if ($retentionStatus === 'lost' && ! in_array($segment, ['VIP', 'LOYAL'], true)) {
            return 'CHURNED';
        }

        if ($deliveredOrders === 0) {
            return 'NEW';
        }

        if (in_array($retentionStatus, ['inactive', 'dormant', 'lost'], true)) {
            return 'AT_RISK';
        }

        if ($deliveredOrders >= 5) {
            return 'LOYAL';
        }

        if ($deliveredOrders >= 2) {
            return 'REPEAT';
        }

        return 'FIRST_PURCHASE';
    }

    protected function isReactivationCandidate(int $deliveredOrders, string $retentionStatus): bool
    {
        return $deliveredOrders >= 1
            && in_array($retentionStatus, ['inactive', 'dormant', 'lost'], true);
    }
}
