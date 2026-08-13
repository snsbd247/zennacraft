<?php

namespace App\Modules\Customer\Services;

use App\Modules\Customer\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerRetentionService
{
    /**
     * Paginates an already-computed profiles() collection for display.
     * Takes the collection as a parameter (rather than calling profiles()
     * itself) so a caller that also needs the full set for a summary can
     * compute profiles() exactly once and derive both the summary and the
     * paginated slice from the same call. retention_status/win_back_candidate
     * are computed in PHP
     * per customer, not stored columns, so this can't be a SQL LIMIT/
     * OFFSET without reimplementing that logic in SQL; forPage() + a
     * manually built LengthAwarePaginator (the same mechanism Eloquent's
     * own ->paginate() uses internally) bounds the HTML response the same
     * way every other paginated Studio view does.
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

    public const STATUSES = [
        'active',
        'inactive',
        'dormant',
        'lost',
    ];

    public function activeCustomers(): Collection
    {
        return $this->profiles()
            ->where('retention_status', 'active')
            ->values();
    }

    public function inactiveCustomers(): Collection
    {
        return $this->profiles()
            ->where('retention_status', 'inactive')
            ->values();
    }

    public function dormantCustomers(): Collection
    {
        return $this->profiles()
            ->where('retention_status', 'dormant')
            ->values();
    }

    public function lostCustomers(): Collection
    {
        return $this->profiles()
            ->where('retention_status', 'lost')
            ->values();
    }

    public function winBackCandidates(): Collection
    {
        return $this->profiles()
            ->where('win_back_candidate', true)
            ->values();
    }

    public function profiles(): Collection
    {
        return Customer::query()
            ->with(['segment', 'riskProfile'])
            ->withMax('orders as latest_order_at', 'created_at')
            ->withSum(['orders as lifetime_value' => fn ($query) => $query->where('status', 'delivered')], 'total')
            ->get()
            ->map(fn (Customer $customer) => $this->profile($customer))
            ->sort(fn (array $first, array $second) => $this->compareProfiles($first, $second))
            ->values();
    }

    public function profile(Customer $customer): array
    {
        $customer->loadMissing(['segment', 'riskProfile']);

        $lastOrderAt = $this->lastOrderAt($customer);
        $daysSinceLastOrder = $lastOrderAt ? (int) floor($lastOrderAt->diffInDays(now())) : null;
        $retentionStatus = $this->retentionStatus($lastOrderAt);
        $segment = $customer->segment?->segment;
        $winBackCandidate = in_array($segment, ['VIP', 'LOYAL'], true)
            && in_array($retentionStatus, ['inactive', 'dormant', 'lost'], true);

        return [
            'customer' => $customer,
            'segment' => $segment,
            'risk_level' => $customer->riskProfile?->risk_level,
            'lifetime_value' => $this->lifetimeValue($customer),
            'last_order_at' => $lastOrderAt,
            'days_since_last_order' => $daysSinceLastOrder,
            'retention_status' => $retentionStatus,
            'win_back_candidate' => $winBackCandidate,
        ];
    }

    public function summary(): array
    {
        return $this->summaryFromProfiles($this->profiles());
    }

    public function summaryFromProfiles(Collection $profiles): array
    {
        return [
            'active' => $profiles->where('retention_status', 'active')->count(),
            'inactive' => $profiles->where('retention_status', 'inactive')->count(),
            'dormant' => $profiles->where('retention_status', 'dormant')->count(),
            'lost' => $profiles->where('retention_status', 'lost')->count(),
            'win_back' => $profiles->where('win_back_candidate', true)->count(),
        ];
    }

    public function retentionStatus(?Carbon $lastOrderAt): string
    {
        if (! $lastOrderAt) {
            return 'lost';
        }

        $days = (int) floor($lastOrderAt->diffInDays(now()));

        if ($days <= 30) {
            return 'active';
        }

        if ($days <= 90) {
            return 'inactive';
        }

        if ($days < 180) {
            return 'dormant';
        }

        return 'lost';
    }

    protected function lastOrderAt(Customer $customer): ?Carbon
    {
        $latestOrderAt = $customer->latest_order_at
            ?? $customer->orders()->max('created_at');

        return $latestOrderAt ? Carbon::parse($latestOrderAt) : null;
    }

    protected function lifetimeValue(Customer $customer): float
    {
        if ($customer->lifetime_value !== null) {
            return (float) $customer->lifetime_value;
        }

        if ($customer->segment?->lifetime_value !== null) {
            return (float) $customer->segment->lifetime_value;
        }

        return (float) $customer->orders()
            ->where('status', 'delivered')
            ->sum('total');
    }

    protected function compareProfiles(array $first, array $second): int
    {
        return ($first['win_back_candidate'] ? 0 : 1) <=> ($second['win_back_candidate'] ? 0 : 1)
            ?: $second['lifetime_value'] <=> $first['lifetime_value']
            ?: ($first['last_order_at']?->timestamp ?? PHP_INT_MAX) <=> ($second['last_order_at']?->timestamp ?? PHP_INT_MAX);
    }
}
