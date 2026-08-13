<?php

namespace App\Modules\Customer\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerCommunication;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerFollowUpService
{
    /**
     * Paginated view of queue() for the index screen. queue() itself stays
     * a plain Collection — summary() and the other status-accessor methods
     * (overdueFollowUps() etc.) depend on it returning the *full* set, and
     * must keep doing so or their counts would silently start reflecting
     * only one page instead of the whole dataset.
     *
     * Status (overdue/due_today/pending/completed) is computed in PHP per
     * row (statusFor()/completionAt()), not a stored column, so it can't
     * be pushed into a SQL WHERE/ORDER BY without reimplementing that
     * business logic in SQL. Pagination is therefore applied to the
     * already-computed, already-sorted collection via forPage() + a
     * manually constructed LengthAwarePaginator — the same mechanism
     * Eloquent's own ->paginate() uses internally, just applied after the
     * PHP-side computation instead of before it. This bounds the HTML
     * response size and pagination links exactly like every other Studio
     * index view; it does not reduce the underlying query cost of loading
     * every OrderVerificationAttempt row with a next_follow_up_at set,
     * which remains proportional to follow-up volume, not paginated away.
     */
    public function paginatedQueue(int $perPage = 20): LengthAwarePaginator
    {
        $followUps = $this->queue();
        $page = Paginator::resolveCurrentPage() ?: 1;

        return new LengthAwarePaginatorImpl(
            $followUps->forPage($page, $perPage)->values(),
            $followUps->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public function overdueFollowUps(): Collection
    {
        return $this->queue()
            ->where('status', 'overdue')
            ->values();
    }

    public function todayFollowUps(): Collection
    {
        return $this->queue()
            ->where('status', 'due_today')
            ->values();
    }

    public function upcomingFollowUps(): Collection
    {
        return $this->queue()
            ->where('status', 'pending')
            ->values();
    }

    public function completedFollowUps(): Collection
    {
        return $this->queue()
            ->where('status', 'completed')
            ->values();
    }

    public function completedTodayFollowUps(): Collection
    {
        return $this->completedFollowUps()
            ->filter(fn (array $followUp) => $followUp['completed_at']?->isToday())
            ->values();
    }

    public function highRiskRequiringFollowUp(): Collection
    {
        return $this->queue()
            ->filter(fn (array $followUp) => $followUp['status'] !== 'completed' && $followUp['risk_level'] === 'high')
            ->values();
    }

    public function queue(): Collection
    {
        return OrderVerificationAttempt::query()
            ->with([
                'staffUser',
                'order.customer.segment',
                'order.customer.riskProfile',
                'order.customer.communications.staffUser',
            ])
            ->whereNotNull('next_follow_up_at')
            ->latest('next_follow_up_at')
            ->get()
            ->map(fn (OrderVerificationAttempt $attempt) => $this->formatFollowUp($attempt))
            ->filter()
            ->sort(fn (array $first, array $second) => $this->compareFollowUps($first, $second))
            ->values();
    }

    public function customerFollowUpSummary(Customer $customer): array
    {
        $followUps = OrderVerificationAttempt::query()
            ->with([
                'staffUser',
                'order.customer.segment',
                'order.customer.riskProfile',
                'order.customer.communications.staffUser',
            ])
            ->whereNotNull('next_follow_up_at')
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest('next_follow_up_at')
            ->get()
            ->map(fn (OrderVerificationAttempt $attempt) => $this->formatFollowUp($attempt))
            ->filter()
            ->sort(fn (array $first, array $second) => $this->compareFollowUps($first, $second))
            ->values();

        $activeFollowUp = $followUps
            ->first(fn (array $followUp) => $followUp['status'] !== 'completed');

        $selectedFollowUp = $activeFollowUp ?: $followUps->first();
        $latestCommunication = $customer->communications()
            ->with('staffUser')
            ->latest()
            ->first();

        return [
            'next_follow_up_at' => $selectedFollowUp['next_follow_up_at'] ?? null,
            'status' => $selectedFollowUp['status'] ?? 'pending',
            'assigned_staff' => $selectedFollowUp['assigned_staff'] ?? null,
            'latest_communication' => $latestCommunication,
            'is_overdue' => ($selectedFollowUp['status'] ?? null) === 'overdue',
        ];
    }

    public function summary(): array
    {
        $followUps = $this->queue();

        return [
            'overdue' => $followUps->where('status', 'overdue')->count(),
            'due_today' => $followUps->where('status', 'due_today')->count(),
            'upcoming' => $followUps->where('status', 'pending')->count(),
            'completed_today' => $followUps
                ->where('status', 'completed')
                ->filter(fn (array $followUp) => $followUp['completed_at']?->isToday())
                ->count(),
            'high_risk' => $followUps
                ->filter(fn (array $followUp) => $followUp['status'] !== 'completed' && $followUp['risk_level'] === 'high')
                ->count(),
        ];
    }

    protected function formatFollowUp(OrderVerificationAttempt $attempt): ?array
    {
        $order = $attempt->order;
        $customer = $order?->customer;

        if (! $order || ! $customer) {
            return null;
        }

        $completionAt = $this->completionAt($attempt);
        $status = $this->statusFor($attempt->next_follow_up_at, $completionAt);
        $latestCommunication = $customer->communications
            ->sortByDesc('created_at')
            ->first();

        return [
            'attempt' => $attempt,
            'customer' => $customer,
            'order' => $order,
            'phone' => $customer->phone,
            'segment' => $customer->segment?->segment,
            'risk_level' => $customer->riskProfile?->risk_level,
            'latest_communication' => $latestCommunication,
            'next_follow_up_at' => $attempt->next_follow_up_at,
            'assigned_staff' => $attempt->staffUser,
            'status' => $status,
            'completed_at' => $completionAt,
        ];
    }

    protected function completionAt(OrderVerificationAttempt $attempt): ?Carbon
    {
        $verificationAt = OrderVerificationAttempt::query()
            ->where('order_id', $attempt->order_id)
            ->where('id', '!=', $attempt->id)
            ->where('created_at', '>', $attempt->next_follow_up_at)
            ->oldest('created_at')
            ->value('created_at');

        $communicationAt = CustomerCommunication::query()
            ->where('customer_id', $attempt->order?->customer_id)
            ->where('created_at', '>', $attempt->next_follow_up_at)
            ->oldest('created_at')
            ->value('created_at');

        return collect([$verificationAt, $communicationAt])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();
    }

    protected function statusFor(Carbon $nextFollowUpAt, ?Carbon $completionAt): string
    {
        if ($completionAt) {
            return 'completed';
        }

        if ($nextFollowUpAt->isPast() && ! $nextFollowUpAt->isToday()) {
            return 'overdue';
        }

        if ($nextFollowUpAt->isToday()) {
            return 'due_today';
        }

        return 'pending';
    }

    protected function statusSortRank(string $status): int
    {
        return match ($status) {
            'overdue' => 0,
            'due_today' => 1,
            'pending' => 2,
            'completed' => 3,
            default => 4,
        };
    }

    protected function compareFollowUps(array $first, array $second): int
    {
        return $this->statusSortRank($first['status']) <=> $this->statusSortRank($second['status'])
            ?: ($first['risk_level'] === 'high' ? 0 : 1) <=> ($second['risk_level'] === 'high' ? 0 : 1)
            ?: ($first['next_follow_up_at']?->timestamp ?? PHP_INT_MAX) <=> ($second['next_follow_up_at']?->timestamp ?? PHP_INT_MAX);
    }
}
