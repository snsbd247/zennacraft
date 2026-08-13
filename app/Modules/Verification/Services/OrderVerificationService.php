<?php

namespace App\Modules\Verification\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Communication\Services\CommunicationService as CommunicationHubService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerCommunicationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\RTO\Services\OrderRiskService;
use App\Modules\Shared\Services\PhoneService;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderVerificationService
{
    protected const OUTCOMES = [
        'verified',
        'callback_requested',
        'no_answer',
        'wrong_number',
        'customer_cancelled',
        'duplicate_order',
        'suspicious',
        'failed_verification',
        'call_later',
        'failed',
    ];

    protected const VERIFICATION_STATUSES = [
        'unverified',
        'called',
        'verified',
        'callback_required',
        'failed',
        'no_answer',
        'call_later',
        'cancelled',
    ];

    public function __construct(
        private OrderManagementService $orderManagementService,
        private CustomerCommunicationService $communicationService,
        private CommunicationHubService $communicationHubService,
        private AuditService $auditService,
        private OrderRiskService $orderRiskService,
        private PhoneService $phoneService,
    ) {}

    public function queue(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $verificationStatus = $filters['verification_status'] ?? null;

        return Order::query()
            ->select('orders.*')
            ->leftJoin('order_risk_profiles', 'order_risk_profiles.order_id', '=', 'orders.id')
            ->with([
                'customer.segment',
                'customer.riskProfile',
                'items.product.thumbnail',
                'items.variant',
                'riskProfile',
                'verifiedBy',
                'verificationAttempts.staffUser' => fn ($query) => $query->latest()->limit(1),
            ])
            ->when($verificationStatus, fn ($query, $status) => $this->applyVerificationStatusFilter($query, (string) $status))
            ->when(! $verificationStatus, fn ($query) => $query->whereNotIn('verification_status', ['verified', 'cancelled']))
            ->when($filters['order_number'] ?? null, fn ($query, $orderNumber) => $query->where('order_number', 'like', '%'.$orderNumber.'%'))
            ->when($filters['phone'] ?? null, fn ($query, $phone) => $this->applyPhoneFilter($query, (string) $phone))
            ->when($filters['follow_up_due'] ?? null, function ($query) {
                $query->whereHas('verificationAttempts', fn ($attemptQuery) => $attemptQuery
                    ->whereNotNull('next_follow_up_at')
                    ->where('next_follow_up_at', '<=', now()));
            })
            ->when(($filters['sort'] ?? 'risk_desc') === 'newest', fn ($query) => $query
                ->orderByRaw("case when orders.risk_hold_status = 'active' then 0 else 1 end")
                ->latest('orders.created_at'))
            ->when(($filters['sort'] ?? 'risk_desc') !== 'newest', fn ($query) => $query
                ->orderByRaw("case when orders.risk_hold_status = 'active' then 0 else 1 end")
                ->orderByDesc('order_risk_profiles.risk_score')
                ->latest('orders.created_at'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function dashboardStats(): array
    {
        $pendingVerification = Order::query()
            ->whereNotIn('verification_status', ['verified', 'cancelled'])
            ->count();

        $verifiedToday = Order::query()
            ->where('verification_status', 'verified')
            ->whereDate('verified_at', today())
            ->count();

        $failedVerification = Order::query()
            ->whereIn('verification_status', ['failed', 'cancelled'])
            ->count();

        $callbackVerification = Order::query()
            ->whereIn('verification_status', ['call_later', 'no_answer'])
            ->count();

        $totalFinished = Order::query()
            ->whereIn('verification_status', ['verified', 'failed', 'cancelled'])
            ->count();

        $verifiedTotal = Order::query()
            ->where('verification_status', 'verified')
            ->count();

        $avgMinutes = Order::query()
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->limit(100)
            ->get(['created_at', 'verified_at'])
            ->map(fn (Order $order): int => max(0, (int) $order->created_at?->diffInMinutes($order->verified_at)))
            ->filter(fn (int $minutes): bool => $minutes > 0)
            ->avg();

        return [
            'pending' => $pendingVerification,
            'verified_today' => $verifiedToday,
            'failed' => $failedVerification,
            'callback' => $callbackVerification,
            'avg_minutes' => $avgMinutes ? round((float) $avgMinutes, 1) : 0.0,
            'success_rate' => $totalFinished > 0 ? round(($verifiedTotal / $totalFinished) * 100, 2) : 0.0,
        ];
    }

    public function statusTabs(): array
    {
        return [
            'all' => [
                'label' => 'All',
                'count' => Order::query()->count(),
                'filter' => null,
            ],
            'unverified' => [
                'label' => 'Pending',
                'count' => Order::query()->where('verification_status', 'unverified')->count(),
                'filter' => 'unverified',
            ],
            'called' => [
                'label' => 'Called',
                'count' => Order::query()->whereHas('verificationAttempts')->whereNotIn('verification_status', ['verified', 'cancelled'])->count(),
                'filter' => 'called',
            ],
            'verified' => [
                'label' => 'Verified',
                'count' => Order::query()->where('verification_status', 'verified')->count(),
                'filter' => 'verified',
            ],
            'callback_required' => [
                'label' => 'Callback Required',
                'count' => Order::query()->whereIn('verification_status', ['call_later', 'no_answer'])->count(),
                'filter' => 'callback_required',
            ],
            'failed' => [
                'label' => 'Failed',
                'count' => Order::query()->where('verification_status', 'failed')->count(),
                'filter' => 'failed',
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                'count' => Order::query()->where('verification_status', 'cancelled')->count(),
                'filter' => 'cancelled',
            ],
        ];
    }

    public function workspace(Order $order): array
    {
        $order->loadMissing([
            'customer.segment',
            'customer.riskProfile',
            'customer.blacklistEntries',
            'items.product.thumbnail',
            'items.variant',
            'riskProfile',
            'verifiedBy',
            'verificationAttempts.staffUser',
            'customerCommunications.staffUser',
            'communicationMessages',
            'coupon',
            'shipment.courierProvider',
        ]);

        return [
            'timeline' => $this->verificationTimeline($order),
            'previous_orders' => $this->previousOrders($order),
            'recovery_records' => $this->recoveryRecords($order),
            'customer_metrics' => $this->customerMetrics($order->customer),
            'priority' => $this->verificationPriority($order),
        ];
    }

    public function verificationTimeline(Order $order): Collection
    {
        return collect()
            ->push([
                'title' => 'Order created',
                'body' => 'Order entered the COD verification queue.',
                'at' => $order->created_at,
                'tone' => 'info',
            ])
            ->when($order->verificationAttempts->isNotEmpty(), fn (Collection $events) => $events->merge(
                $order->verificationAttempts->map(fn (OrderVerificationAttempt $attempt): array => [
                    'title' => $this->outcomeLabel($attempt->outcome),
                    'body' => $attempt->notes ?: 'No verification note recorded.',
                    'at' => $attempt->created_at,
                    'meta' => $attempt->staffUser?->name,
                    'follow_up_at' => $attempt->next_follow_up_at,
                    'tone' => $this->toneForOutcome($attempt->outcome),
                ])
            ))
            ->when($order->verified_at, fn (Collection $events) => $events->push([
                'title' => 'Verified',
                'body' => 'Order was verified and moved forward for processing.',
                'at' => $order->verified_at,
                'meta' => $order->verifiedBy?->name,
                'tone' => 'success',
            ]))
            ->sortByDesc('at')
            ->values();
    }

    public function callScripts(): array
    {
        return [
            [
                'key' => 'standard_cod',
                'label' => 'Standard COD Verification',
                'description' => 'Confirm customer identity, product/package, delivery address, COD total, and delivery expectation.',
                'steps' => [
                    'Greet the customer and mention Zenna Craft.',
                    'Confirm the order number, product/package, quantity, and COD amount.',
                    'Confirm delivery address, district, and any delivery note.',
                    'Explain that courier dispatch starts after verification.',
                ],
            ],
            [
                'key' => 'high_value',
                'label' => 'High Value Order',
                'description' => 'Use for expensive packages or orders with higher RTO exposure.',
                'steps' => [
                    'Confirm the customer intentionally placed the full order.',
                    'Repeat the package details and total payable amount clearly.',
                    'Confirm availability to receive the delivery call.',
                    'Escalate to callback or suspicious outcome if answers conflict.',
                ],
            ],
            [
                'key' => 'repeat_customer',
                'label' => 'Repeat Customer',
                'description' => 'Use previous successful delivery context to keep the call short and warm.',
                'steps' => [
                    'Thank the customer for ordering again.',
                    'Confirm whether the previous delivery address should be reused.',
                    'Confirm product/package and COD total.',
                    'Mark verified if all details match.',
                ],
            ],
            [
                'key' => 'recovery_follow_up',
                'label' => 'Recovery Follow-up',
                'description' => 'Use when the order originated from recovery or the customer recently abandoned checkout.',
                'steps' => [
                    'Reference the selected item without pressuring the customer.',
                    'Ask whether they need help with package, delivery, or COD details.',
                    'Confirm order only when the customer explicitly agrees.',
                    'Use callback requested if the customer wants time.',
                ],
            ],
        ];
    }

    public function outcomeOptions(): array
    {
        return [
            'verified' => 'Verified',
            'callback_requested' => 'Callback Requested',
            'no_answer' => 'No Answer',
            'wrong_number' => 'Wrong Number',
            'customer_cancelled' => 'Customer Cancelled',
            'duplicate_order' => 'Duplicate Order',
            'suspicious' => 'Suspicious',
            'failed_verification' => 'Failed Verification',
        ];
    }

    public function staffPerformance(): Collection
    {
        $attempts = OrderVerificationAttempt::query()
            ->select('staff_user_id')
            ->selectRaw('COUNT(*) as calls_completed')
            ->selectRaw("COALESCE(SUM(CASE WHEN outcome = 'verified' THEN 1 ELSE 0 END), 0) as verified_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN outcome IN ('failed', 'failed_verification', 'wrong_number') THEN 1 ELSE 0 END), 0) as failed_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN outcome IN ('call_later', 'callback_requested', 'no_answer') THEN 1 ELSE 0 END), 0) as callback_count")
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('staff_user_id')
            ->orderByDesc('calls_completed')
            ->limit(8)
            ->get();

        $staff = StaffUser::query()
            ->whereIn('id', $attempts->pluck('staff_user_id')->filter())
            ->get()
            ->keyBy('id');

        return $attempts->map(function ($row) use ($staff): array {
            $calls = (int) $row->calls_completed;
            $verified = (int) $row->verified_count;
            $failed = (int) $row->failed_count;
            $callbacks = (int) $row->callback_count;

            return [
                'staff' => $staff->get($row->staff_user_id),
                'calls_completed' => $calls,
                'verified' => $verified,
                'failed' => $failed,
                'callbacks' => $callbacks,
                'success_rate' => $calls > 0 ? round(($verified / $calls) * 100, 2) : 0.0,
                'failed_rate' => $calls > 0 ? round(($failed / $calls) * 100, 2) : 0.0,
                'callback_rate' => $calls > 0 ? round(($callbacks / $calls) * 100, 2) : 0.0,
            ];
        });
    }

    public function recoverySignalsForOrders(iterable $orders): array
    {
        $orders = collect($orders);
        $phoneValues = $orders
            ->pluck('customer_phone')
            ->filter()
            ->flatMap(fn (string $phone): array => $this->phoneService->lookupValues($phone) ?: [$this->phoneService->normalize($phone)])
            ->filter()
            ->unique()
            ->values();

        if ($phoneValues->isEmpty()) {
            return [];
        }

        $recoveries = CheckoutRecovery::query()
            ->with(['product', 'variant'])
            ->whereIn('customer_phone', $phoneValues->all())
            ->latest()
            ->get()
            ->groupBy(fn (CheckoutRecovery $recovery): string => $this->phoneService->normalize((string) $recovery->customer_phone));

        return $orders->mapWithKeys(function (Order $order) use ($recoveries): array {
            $normalized = $this->phoneService->normalize((string) $order->customer_phone);
            $recovery = $recoveries->get($normalized)?->first();

            return [
                $order->id => $recovery ? $this->recoveryScoreForRecord($recovery) : null,
            ];
        })->all();
    }

    protected function applyPhoneFilter($query, string $phone): void
    {
        $normalized = $this->phoneService->normalize($phone);

        if ($normalized === '') {
            return;
        }

        $values = $this->phoneService->lookupValues($normalized) ?: [$normalized];

        $query->where(function ($phoneQuery) use ($values): void {
            foreach ($values as $value) {
                $phoneQuery->orWhere('customer_phone', 'like', '%'.$value.'%');
            }
        });
    }

    protected function applyVerificationStatusFilter($query, string $status): void
    {
        match ($status) {
            'called' => $query->whereHas('verificationAttempts')
                ->whereNotIn('verification_status', ['verified', 'cancelled']),
            'callback_required' => $query->whereIn('verification_status', ['call_later', 'no_answer']),
            default => $query->where('verification_status', $status),
        };
    }

    public function recordAttempt(
        Order $order,
        string $outcome,
        ?string $notes = null,
        ?string $nextFollowUpAt = null,
        ?string $callbackReason = null,
        ?string $communicationChannel = null
    ): OrderVerificationAttempt {
        $outcome = $this->normalizeOutcome($outcome);

        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages([
                'outcome' => 'The selected verification outcome is invalid.',
            ]);
        }

        if (in_array($outcome, ['call_later', 'callback_requested'], true) && blank($nextFollowUpAt)) {
            throw ValidationException::withMessages([
                'next_follow_up_at' => 'A follow-up date is required when the customer asks to be called later.',
            ]);
        }

        if (in_array($outcome, ['call_later', 'callback_requested'], true)) {
            try {
                $followUpAt = Carbon::parse($nextFollowUpAt);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'next_follow_up_at' => 'The follow-up date must be a valid date.',
                ]);
            }

            if ($followUpAt->lte(now())) {
                throw ValidationException::withMessages([
                    'next_follow_up_at' => 'The follow-up date must be in the future.',
                ]);
            }
        }

        $notes = $this->verificationNotes($notes, $callbackReason, $nextFollowUpAt);

        $staffUserId = auth()->guard('staff')->id();
        $oldVerificationStatus = $order->verification_status;

        $attempt = DB::transaction(function () use ($order, $outcome, $notes, $nextFollowUpAt, $staffUserId, &$oldVerificationStatus) {
            $order = Order::with(['items', 'customer'])->lockForUpdate()->findOrFail($order->id);
            $oldVerificationStatus = $order->verification_status;

            if ($this->shouldApplyOutcome($order, $outcome)) {
                if ($outcome === 'verified') {
                    $order = $this->orderManagementService->updateStatus($order, 'confirmed', $notes);
                }

                if ($outcome === 'customer_cancelled') {
                    $order = $this->orderManagementService->updateStatus($order, 'cancelled', $notes);
                }

                $verificationPayload = [
                    'verification_status' => $this->verificationStatusForOutcome($outcome),
                ];

                if ($outcome === 'verified') {
                    $verificationPayload['verified_at'] = now();
                    $verificationPayload['verified_by'] = $staffUserId;
                }

                $order->forceFill($verificationPayload)->save();
            }

            return $order->verificationAttempts()->create([
                'staff_user_id' => $staffUserId,
                'outcome' => $outcome,
                'notes' => $notes,
                'next_follow_up_at' => $nextFollowUpAt,
            ]);
        });

        $attempt->load(['order.customer', 'staffUser']);
        $this->logCommunication($attempt);
        $this->queueVerificationCommunication($attempt, $communicationChannel);
        $this->logAudit($attempt, $oldVerificationStatus);
        $this->recalculateOrderRisk($attempt->order, 'verification_attempt');

        return $attempt;
    }

    public function allowedOutcomes(): array
    {
        return self::OUTCOMES;
    }

    public function allowedVerificationStatuses(): array
    {
        return self::VERIFICATION_STATUSES;
    }

    protected function verificationStatusForOutcome(string $outcome): string
    {
        return match ($outcome) {
            'verified' => 'verified',
            'customer_cancelled' => 'cancelled',
            'no_answer' => 'no_answer',
            'call_later', 'callback_requested' => 'call_later',
            'wrong_number', 'duplicate_order', 'suspicious', 'failed', 'failed_verification' => 'failed',
        };
    }

    protected function shouldApplyOutcome(Order $order, string $outcome): bool
    {
        if ($order->verification_status === 'verified' && $outcome !== 'verified') {
            return false;
        }

        if ($order->status === 'delivered' && $outcome === 'customer_cancelled') {
            return false;
        }

        return true;
    }

    protected function communicationStatusForOutcome(string $outcome): string
    {
        return match ($outcome) {
            'verified', 'customer_cancelled' => 'success',
            'call_later', 'callback_requested', 'no_answer' => 'pending',
            'wrong_number', 'duplicate_order', 'suspicious', 'failed', 'failed_verification' => 'failed',
        };
    }

    protected function normalizeOutcome(string $outcome): string
    {
        return match ($outcome) {
            'callback_requested' => 'callback_requested',
            default => $outcome,
        };
    }

    protected function verificationNotes(?string $notes, ?string $callbackReason, ?string $nextFollowUpAt): ?string
    {
        $lines = collect([
            trim((string) $notes),
            filled($callbackReason) ? 'Callback reason: '.trim((string) $callbackReason) : null,
            filled($nextFollowUpAt) ? 'Preferred callback: '.Carbon::parse($nextFollowUpAt)->format('M j, Y g:i A') : null,
        ])
            ->filter()
            ->values();

        return $lines->isEmpty() ? null : $lines->implode(PHP_EOL);
    }

    protected function queueVerificationCommunication(OrderVerificationAttempt $attempt, ?string $channel): void
    {
        if (! in_array($channel, ['sms', 'whatsapp', 'internal'], true)) {
            return;
        }

        try {
            $order = $attempt->order;

            if (! $order) {
                return;
            }

            $recipient = $channel === 'internal'
                ? 'studio'
                : (string) $order->customer_phone;

            if ($channel !== 'internal' && blank($recipient)) {
                return;
            }

            $message = $this->communicationHubService->createFromTemplate(
                $channel,
                $recipient,
                'verification_'.$attempt->outcome,
                [
                    'message' => 'Verification update for order '.$order->order_number.': '.$this->outcomeLabel($attempt->outcome),
                    'customer_name' => $order->customer_name ?: 'Customer',
                    'order_number' => $order->order_number,
                    'verification_outcome' => $this->outcomeLabel($attempt->outcome),
                ],
                [
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'trigger' => 'verification:'.$attempt->outcome,
                ]
            );

            $this->communicationHubService->queueMessage($message, true);
        } catch (Throwable $exception) {
            logger()->warning('Verification communication queueing failed', [
                'attempt_id' => $attempt->id,
                'order_id' => $attempt->order_id,
                'channel' => $channel,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function previousOrders(Order $order): Collection
    {
        $query = Order::query()
            ->with(['items', 'riskProfile', 'verificationAttempts.staffUser'])
            ->whereKeyNot($order->id)
            ->latest()
            ->limit(6);

        if ($order->customer_id) {
            $query->where('customer_id', $order->customer_id);
        } else {
            $values = $this->phoneService->lookupValues((string) $order->customer_phone);

            $query->where(function ($phoneQuery) use ($values, $order): void {
                foreach ($values ?: [(string) $order->customer_phone] as $value) {
                    $phoneQuery->orWhere('customer_phone', 'like', '%'.$value.'%');
                }
            });
        }

        return $query->get();
    }

    protected function recoveryRecords(Order $order): Collection
    {
        if (blank($order->customer_phone)) {
            return collect();
        }

        $values = $this->phoneService->lookupValues((string) $order->customer_phone);

        return CheckoutRecovery::query()
            ->with(['product.thumbnail', 'variant'])
            ->where(function ($query) use ($values, $order): void {
                foreach ($values ?: [(string) $order->customer_phone] as $value) {
                    $query->orWhere('customer_phone', 'like', '%'.$value.'%');
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (CheckoutRecovery $recovery): array {
                return [
                    'record' => $recovery,
                    'score' => $this->recoveryScoreForRecord($recovery),
                ];
            });
    }

    protected function recoveryScoreForRecord(CheckoutRecovery $recovery): array
    {
        $score = 20;

        if (filled($recovery->customer_phone)) {
            $score += 20;
        }

        if (filled($recovery->address)) {
            $score += 20;
        }

        $value = (float) ($recovery->variant?->price ?? $recovery->product?->price ?? 0) * max(1, (int) $recovery->quantity);

        if ($value >= 5000) {
            $score += 20;
        } elseif ($value >= 1500) {
            $score += 10;
        }

        if ($recovery->updated_at?->greaterThan(now()->subHours(6))) {
            $score += 15;
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => $score >= 70 ? 'Hot' : ($score >= 45 ? 'Warm' : 'Cold'),
            'value' => $value,
        ];
    }

    protected function customerMetrics(?Customer $customer): array
    {
        if (! $customer) {
            return [
                'total_orders' => 0,
                'delivered_orders' => 0,
                'cancelled_orders' => 0,
                'returned_orders' => 0,
                'total_revenue' => 0.0,
                'last_order_at' => null,
                'segment' => null,
                'risk_level' => null,
                'blacklisted' => false,
            ];
        }

        $summary = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END), 0) as returned_orders")
            ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->first();

        $customer->loadMissing(['segment', 'riskProfile', 'blacklistEntries']);

        return [
            'total_orders' => (int) $summary->total_orders,
            'delivered_orders' => (int) $summary->delivered_orders,
            'cancelled_orders' => (int) $summary->cancelled_orders,
            'returned_orders' => (int) $summary->returned_orders,
            'total_revenue' => (float) $summary->total_revenue,
            'last_order_at' => $summary->last_order_at ? Carbon::parse($summary->last_order_at) : null,
            'segment' => $customer->segment?->segment,
            'risk_level' => $customer->riskProfile?->risk_level,
            'blacklisted' => $customer->blacklistEntries->where('active', true)->isNotEmpty(),
        ];
    }

    protected function verificationPriority(Order $order): array
    {
        $score = 20;
        $reasons = [];

        if ((float) $order->total >= 5000) {
            $score += 25;
            $reasons[] = 'High value';
        }

        if ($order->risk_hold_status === 'active' || in_array($order->riskProfile?->risk_level, ['medium', 'high'], true)) {
            $score += 25;
            $reasons[] = 'Risk signal';
        }

        if ($order->verification_status === 'call_later' || $order->verification_status === 'no_answer') {
            $score += 15;
            $reasons[] = 'Needs callback';
        }

        if ($order->created_at?->lessThan(now()->subHours(12))) {
            $score += 10;
            $reasons[] = 'Aging order';
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => $score >= 70 ? 'High' : ($score >= 45 ? 'Medium' : 'Normal'),
            'reasons' => $reasons,
        ];
    }

    public function outcomeLabel(string $outcome): string
    {
        return [
            'verified' => 'Verified',
            'callback_requested' => 'Callback Requested',
            'call_later' => 'Callback Requested',
            'no_answer' => 'No Answer',
            'wrong_number' => 'Wrong Number',
            'customer_cancelled' => 'Customer Cancelled',
            'duplicate_order' => 'Duplicate Order',
            'suspicious' => 'Suspicious',
            'failed' => 'Failed Verification',
            'failed_verification' => 'Failed Verification',
        ][$outcome] ?? ucfirst(str_replace('_', ' ', $outcome));
    }

    protected function toneForOutcome(string $outcome): string
    {
        return match ($outcome) {
            'verified' => 'success',
            'callback_requested', 'call_later', 'no_answer' => 'warning',
            'wrong_number', 'customer_cancelled', 'duplicate_order', 'suspicious', 'failed', 'failed_verification' => 'danger',
            default => 'info',
        };
    }

    protected function logCommunication(OrderVerificationAttempt $attempt): void
    {
        try {
            $order = $attempt->order;

            if (! $order?->customer) {
                return;
            }

            $this->communicationService->createCommunication([
                'customer_id' => $order->customer->id,
                'order_id' => $order->id,
                'type' => 'call',
                'direction' => 'outbound',
                'subject' => 'Order verification',
                'message' => $attempt->notes ?: $attempt->outcome,
                'status' => $this->communicationStatusForOutcome($attempt->outcome),
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Order verification communication logging failed', [
                'attempt_id' => $attempt->id,
                'order_id' => $attempt->order_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function logAudit(OrderVerificationAttempt $attempt, ?string $oldVerificationStatus): void
    {
        try {
            $order = $attempt->order;

            $this->auditService->log(
                'order',
                'verification',
                'order',
                'Order verification recorded: '.$order->order_number,
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'old_verification_status' => $oldVerificationStatus,
                    'new_verification_status' => $order->verification_status,
                    'outcome' => $attempt->outcome,
                    'staff_user_id' => $attempt->staff_user_id,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Order verification audit logging failed', [
                'attempt_id' => $attempt->id,
                'order_id' => $attempt->order_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function recalculateOrderRisk(Order $order, string $trigger): void
    {
        try {
            $this->orderRiskService->recalculateOrder($order, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Order RTO risk recalculation after verification failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
