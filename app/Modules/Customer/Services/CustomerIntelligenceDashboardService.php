<?php

namespace App\Modules\Customer\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use Illuminate\Support\Collection;

class CustomerIntelligenceDashboardService
{
    public function __construct(
        private CustomerFollowUpService $followUpService,
        private CustomerLifecycleService $lifecycleService,
        private CacheService $cacheService,
    ) {}

    public function dashboard(): array
    {
        return $this->cacheService->remember(CacheKeyRegistry::CUSTOMER_INTELLIGENCE_DASHBOARD, function () {
            $profiles = $this->lifecycleService->lifecycleCustomers();
            $segmentDistribution = $this->segmentDistribution($profiles);
            $lifecycleDistribution = $this->lifecycleService->summaryFromProfiles($profiles);
            $retentionDistribution = $this->retentionDistribution($profiles);
            $followUpSummary = $this->followUpService->summary();
            $riskIntelligence = $this->riskIntelligence($profiles);

            return [
                'kpis' => $this->kpis($profiles, $segmentDistribution, $lifecycleDistribution, $riskIntelligence),
                'segment_distribution' => $segmentDistribution,
                'lifecycle_distribution' => $lifecycleDistribution,
                'retention_distribution' => $retentionDistribution,
                'follow_up' => $followUpSummary,
                'top_customers' => $this->topCustomers($profiles),
                'risk' => $riskIntelligence,
                'quick_links' => $this->quickLinks(),
            ];
        }, CacheService::SHORT_TTL);
    }

    protected function kpis(Collection $profiles, array $segments, array $lifecycle, array $risk): array
    {
        return [
            'total_customers' => $profiles->count(),
            'vip_customers' => $segments['VIP'],
            'loyal_customers' => $segments['LOYAL'],
            'blocked_customers' => $segments['BLOCKED'],
            'at_risk_customers' => $segments['AT_RISK'],
            'win_back_candidates' => $profiles->filter(fn (array $profile) => in_array($profile['segment'], ['VIP', 'LOYAL'], true)
                && in_array($profile['retention_status'], ['inactive', 'dormant', 'lost'], true))->count(),
            'reactivation_candidates' => $lifecycle['REACTIVATION'],
            'high_risk_customers' => $risk['high_risk_customers'],
        ];
    }

    protected function segmentDistribution(Collection $profiles): array
    {
        $segments = ['VIP', 'LOYAL', 'REGULAR', 'NEW', 'AT_RISK', 'BLOCKED'];

        return collect($segments)
            ->mapWithKeys(fn (string $segment) => [$segment => $profiles->where('segment', $segment)->count()])
            ->all();
    }

    protected function retentionDistribution(Collection $profiles): array
    {
        return collect(CustomerRetentionService::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => $profiles->where('retention_status', $status)->count()])
            ->all();
    }

    protected function riskIntelligence(Collection $profiles): array
    {
        return [
            'high_risk_customers' => $profiles->where('risk_level', 'high')->count(),
            'blocked_customers' => $profiles->where('segment', 'BLOCKED')->count(),
            'active_risk_holds' => Order::query()->where('risk_hold_status', 'active')->count(),
            'released_holds' => Order::query()->whereNotNull('risk_hold_released_at')->count(),
        ];
    }

    protected function topCustomers(Collection $profiles): Collection
    {
        return $profiles
            ->sort(fn (array $first, array $second) => $second['lifetime_value'] <=> $first['lifetime_value']
                ?: $second['delivered_orders'] <=> $first['delivered_orders'])
            ->take(20)
            ->values();
    }

    protected function quickLinks(): array
    {
        return [
            [
                'title' => 'Customer Segments',
                'route' => 'customer-segments.index',
                'permission' => 'customer.segment.view',
            ],
            [
                'title' => 'Customer Retention',
                'route' => 'customer-retention.index',
                'permission' => 'customer.retention.view',
            ],
            [
                'title' => 'Customer Lifecycle',
                'route' => 'customer-lifecycle.index',
                'permission' => 'customer.lifecycle.view',
            ],
            [
                'title' => 'Customer Follow-ups',
                'route' => 'customer-followups.index',
                'permission' => 'customer.followup.view',
            ],
            [
                'title' => 'Customer List',
                'route' => 'customers.index',
                'permission' => 'customer.view',
            ],
        ];
    }
}
