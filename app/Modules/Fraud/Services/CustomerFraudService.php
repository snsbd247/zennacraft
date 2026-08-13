<?php

namespace App\Modules\Fraud\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\RTO\Services\OrderHoldService;
use App\Modules\Shared\Services\PhoneService;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Illuminate\Support\Facades\DB;
use Throwable;

class CustomerFraudService
{
    public const ACTION_ALLOW = 'ALLOW';
    public const ACTION_WARN = 'WARN';
    public const ACTION_HOLD = 'HOLD';

    public function __construct(
        private AuditService $auditService,
        private OrderHoldService $orderHoldService,
        private CacheService $cacheService,
        private PhoneService $phoneService,
        private MarketingSegmentEngineService $marketingSegmentEngine,
    ) {}

    public function analyzeOrder(Order $order): array
    {
        try {
            $order->loadMissing(['customer', 'items', 'couponUsage']);

            $signals = [];
            $score = 0;
            $phone = $this->phoneService->normalize((string) $order->customer_phone);
            $customer = $order->customer;

            if ($this->isBlacklisted($phone)) {
                $signals[] = [
                    'type' => 'blacklist',
                    'score' => 100,
                    'reason' => 'Customer phone is on the active blacklist.',
                    'metadata' => ['source' => 'customer_blacklists'],
                ];
            }

            foreach ($this->duplicateSignals($order, $phone) as $signal) {
                $signals[] = $signal;
            }

            foreach ($this->couponSignals($order) as $signal) {
                $signals[] = $signal;
            }

            $history = $this->profileFor($customer, $phone);

            if ($history['cancelled_rate'] >= 50 && $history['total_orders'] >= 3) {
                $signals[] = [
                    'type' => 'customer_history',
                    'score' => 25,
                    'reason' => 'Customer has a high cancellation rate.',
                    'metadata' => ['cancelled_rate' => $history['cancelled_rate']],
                ];
            }

            if ($history['returned_rate'] >= 30 && $history['total_orders'] >= 3) {
                $signals[] = [
                    'type' => 'repeat_rto',
                    'score' => 35,
                    'reason' => 'Customer has repeat returned orders.',
                    'metadata' => ['returned_rate' => $history['returned_rate']],
                ];
            }

            if ($history['failed_verification_rate'] >= 40 && $history['verification_attempts'] >= 2) {
                $signals[] = [
                    'type' => 'verification_risk',
                    'score' => 25,
                    'reason' => 'Customer has repeated failed verification outcomes.',
                    'metadata' => ['failed_verification_rate' => $history['failed_verification_rate']],
                ];
            }

            foreach ($signals as $signal) {
                $score += (int) $signal['score'];
            }

            $score = min(100, max(0, $score));
            $severity = $this->severityForScore($score);
            $action = $this->actionForScore($score);
            $reasons = collect($signals)->pluck('reason')->unique()->values()->all();

            foreach ($signals as $signal) {
                $this->recordFraudEvent(
                    $customer,
                    $order,
                    $signal['type'],
                    $this->severityForScore((int) $signal['score']),
                    (int) $signal['score'],
                    $signal['reason'],
                    $signal['metadata'] ?? []
                );
            }

            if ($action === self::ACTION_HOLD) {
                $this->holdOrder($order, $reasons);
            }

            return [
                'action' => $action,
                'score' => $score,
                'severity' => $severity,
                'reasons' => $reasons,
                'signals' => $signals,
            ];
        } catch (Throwable $exception) {
            logger()->warning('Customer fraud analysis failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);

            return [
                'action' => self::ACTION_ALLOW,
                'score' => 0,
                'severity' => 'low',
                'reasons' => [],
                'signals' => [],
            ];
        }
    }

    public function customerFraudProfile(Customer $customer): array
    {
        return $this->profileFor($customer, $this->phoneService->normalize((string) $customer->phone));
    }

    public function fraudDashboard(): array
    {
        return $this->cacheService->remember(CacheKeyRegistry::CUSTOMER_FRAUD_DASHBOARD, fn () => [
            'high_risk_customers' => FraudEvent::query()
                ->where('severity', 'high')
                ->whereNotNull('customer_id')
                ->distinct('customer_id')
                ->count('customer_id'),
            'critical_customers' => FraudEvent::query()
                ->where('severity', 'critical')
                ->whereNotNull('customer_id')
                ->distinct('customer_id')
                ->count('customer_id'),
            'duplicate_orders' => FraudEvent::query()
                ->where('type', 'duplicate_order')
                ->count(),
            'blacklist_count' => CustomerBlacklist::query()
                ->where('active', true)
                ->count(),
            'fraud_events_today' => FraudEvent::query()
                ->whereDate('created_at', today())
                ->count(),
        ], CacheService::SHORT_TTL);
    }

    public function recordFraudEvent(
        ?Customer $customer,
        ?Order $order,
        string $type,
        string $severity,
        int $score,
        string $reason,
        array $metadata = []
    ): FraudEvent {
        $event = FraudEvent::create([
            'customer_id' => $customer?->id,
            'order_id' => $order?->id,
            'type' => $type,
            'severity' => $severity,
            'score' => min(100, max(0, $score)),
            'reason' => $reason,
            'metadata' => $this->safeMetadata($metadata),
            'created_at' => now(),
        ]);

        $this->logAudit('customer.fraud.event', [
            'fraud_event_id' => $event->id,
            'customer_id' => $customer?->id,
            'order_id' => $order?->id,
            'type' => $type,
            'severity' => $severity,
            'score' => $event->score,
        ]);
        $this->cacheService->invalidateFraudDashboard();
        $this->evaluateMarketingSegments($customer ?? $order?->customer, 'fraud_event:'.$type);
        $this->triggerAutomation('fraud.event.created', $event, [
            'fraud_event_id' => $event->id,
            'type' => $type,
            'severity' => $severity,
            'score' => $event->score,
            'customer_id' => $event->customer_id,
            'order_id' => $event->order_id,
        ]);

        return $event;
    }

    public function blacklistCustomer(?Customer $customer, string $phone, string $reason): CustomerBlacklist
    {
        $normalizedPhone = $this->phoneService->normalize($phone);
        $customer ??= $this->findCustomerByPhone($normalizedPhone);

        $blacklist = CustomerBlacklist::query()->updateOrCreate(
            ['phone' => $normalizedPhone, 'active' => true],
            [
                'customer_id' => $customer?->id,
                'reason' => $reason,
                'created_by' => auth()->guard('staff')->id(),
                'created_at' => now(),
            ]
        );

        $this->logAudit('customer.blacklist.add', [
            'blacklist_id' => $blacklist->id,
            'customer_id' => $blacklist->customer_id,
            'staff_id' => auth()->guard('staff')->id(),
        ]);
        $this->cacheService->invalidateFraudDashboard();
        $this->evaluateMarketingSegments($customer, 'blacklist:add');
        $this->triggerAutomation('blacklist.added', $blacklist, [
            'blacklist_id' => $blacklist->id,
            'customer_id' => $blacklist->customer_id,
        ]);

        return $blacklist;
    }

    public function unblacklistCustomer(CustomerBlacklist $blacklist): CustomerBlacklist
    {
        $blacklist->forceFill(['active' => false])->save();

        $this->logAudit('customer.blacklist.remove', [
            'blacklist_id' => $blacklist->id,
            'customer_id' => $blacklist->customer_id,
            'staff_id' => auth()->guard('staff')->id(),
        ]);
        $this->cacheService->invalidateFraudDashboard();
        $this->evaluateMarketingSegments($blacklist->customer, 'blacklist:remove');

        return $blacklist->refresh();
    }

    protected function duplicateSignals(Order $order, string $phone): array
    {
        $signals = [];
        $productIds = $order->items->pluck('product_id')->filter()->unique()->values();

        if ($phone !== '' && $productIds->isNotEmpty()) {
            $sameProductCount = Order::query()
                ->whereKeyNot($order->id)
                ->where('created_at', '>=', now()->subDay())
                ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $phone))
                ->whereHas('items', fn ($query) => $query->whereIn('product_id', $productIds))
                ->count();

            if ($sameProductCount > 0) {
                $signals[] = [
                    'type' => 'duplicate_order',
                    'score' => 30,
                    'reason' => 'Same phone ordered the same product within 24 hours.',
                    'metadata' => [
                        'matching_orders' => $sameProductCount,
                        'window_hours' => 24,
                    ],
                ];
            }

            $sameTotalCount = Order::query()
                ->whereKeyNot($order->id)
                ->where('created_at', '>=', now()->subHours(12))
                ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $phone))
                ->where('total', $order->total)
                ->count();

            if ($sameTotalCount > 0) {
                $signals[] = [
                    'type' => 'duplicate_order',
                    'score' => 20,
                    'reason' => 'Same phone placed an order with the same total within 12 hours.',
                    'metadata' => [
                        'matching_orders' => $sameTotalCount,
                        'window_hours' => 12,
                    ],
                ];
            }

            $pendingOrders = Order::query()
                ->where('status', 'pending')
                ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $phone))
                ->count();

            if ($pendingOrders >= 3) {
                $signals[] = [
                    'type' => 'duplicate_order',
                    'score' => 20,
                    'reason' => 'Customer has multiple pending orders.',
                    'metadata' => ['pending_orders' => $pendingOrders],
                ];
            }

            $openRecoveries = CheckoutRecovery::query()
                ->where('status', 'open')
                ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $phone))
                ->count();

            if ($openRecoveries >= 3) {
                $signals[] = [
                    'type' => 'spam_checkout',
                    'score' => 20,
                    'reason' => 'Customer has multiple incomplete checkouts.',
                    'metadata' => ['open_recoveries' => $openRecoveries],
                ];
            }
        }

        if (filled($order->address)) {
            $phoneCount = Order::query()
                ->where('address', $order->address)
                ->where('created_at', '>=', now()->subDays(7))
                ->distinct('customer_phone')
                ->count('customer_phone');

            if ($phoneCount >= 3) {
                $signals[] = [
                    'type' => 'suspicious_customer',
                    'score' => 20,
                    'reason' => 'Same address has been used with many phone numbers.',
                    'metadata' => [
                        'distinct_phone_count' => $phoneCount,
                        'window_days' => 7,
                    ],
                ];
            }
        }

        return $signals;
    }

    protected function couponSignals(Order $order): array
    {
        if (! $order->coupon_id) {
            return [];
        }

        $signals = [];
        $query = $order->customer_id
            ? DB::table('coupon_usages')->where('customer_id', $order->customer_id)
            : DB::table('orders')
                ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', (string) $order->customer_phone))
                ->whereNotNull('coupon_id');

        $sameCouponUses = (clone $query)->where('coupon_id', $order->coupon_id)->count();

        if ($sameCouponUses > 1) {
            $signals[] = [
                'type' => 'coupon_abuse',
                'score' => 15,
                'reason' => 'Customer has repeated usage of the same coupon.',
                'metadata' => [
                    'coupon_id' => $order->coupon_id,
                    'usage_count' => $sameCouponUses,
                ],
            ];
        }

        return $signals;
    }

    protected function profileFor(?Customer $customer, string $phone): array
    {
        $ordersQuery = Order::query()
            ->when($customer, fn ($query) => $query->where('customer_id', $customer->id))
            ->when(! $customer && $phone !== '', fn ($query) => $query->where(fn ($phoneQuery) => $this->phoneService->whereNormalizedPhone($phoneQuery, 'customer_phone', $phone)));

        $summary = (clone $ordersQuery)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END), 0) as returned_orders")
            ->first();

        $orderIds = (clone $ordersQuery)->pluck('id');
        $totalOrders = (int) $summary->total_orders;
        $cancelledOrders = (int) $summary->cancelled_orders;
        $returnedOrders = (int) $summary->returned_orders;
        $verificationAttempts = $orderIds->isEmpty() ? 0 : OrderVerificationAttempt::whereIn('order_id', $orderIds)->count();
        $failedVerificationAttempts = $orderIds->isEmpty() ? 0 : OrderVerificationAttempt::whereIn('order_id', $orderIds)
            ->whereIn('outcome', ['wrong_number', 'failed', 'customer_cancelled'])
            ->count();
        $duplicateCount = $customer
            ? FraudEvent::where('customer_id', $customer->id)->where('type', 'duplicate_order')->count()
            : 0;
        $blacklisted = $this->isBlacklisted($phone);
        $cancelledRate = $this->percentage($cancelledOrders, $totalOrders);
        $returnedRate = $this->percentage($returnedOrders, $totalOrders);
        $failedVerificationRate = $this->percentage($failedVerificationAttempts, $verificationAttempts);
        $score = 0;

        if ($blacklisted) {
            $score += 100;
        }

        if ($cancelledRate >= 50 && $totalOrders >= 3) {
            $score += 30;
        } elseif ($cancelledRate >= 30 && $totalOrders >= 3) {
            $score += 15;
        }

        if ($returnedRate >= 30 && $totalOrders >= 3) {
            $score += 35;
        } elseif ($returnedRate >= 15 && $totalOrders >= 3) {
            $score += 15;
        }

        if ($failedVerificationRate >= 40 && $verificationAttempts >= 2) {
            $score += 25;
        }

        $score += min(30, $duplicateCount * 10);
        $score = min(100, $score);

        return [
            'fraud_score' => $score,
            'fraud_level' => strtoupper($this->severityForScore($score)),
            'total_orders' => $totalOrders,
            'cancelled_orders' => $cancelledOrders,
            'returned_orders' => $returnedOrders,
            'cancelled_rate' => $cancelledRate,
            'returned_rate' => $returnedRate,
            'verification_attempts' => $verificationAttempts,
            'failed_verification_rate' => $failedVerificationRate,
            'duplicate_count' => $duplicateCount,
            'blacklist_active' => $blacklisted,
        ];
    }

    protected function actionForScore(int $score): string
    {
        if ($score >= 50) {
            return self::ACTION_HOLD;
        }

        if ($score >= 25) {
            return self::ACTION_WARN;
        }

        return self::ACTION_ALLOW;
    }

    protected function severityForScore(int $score): string
    {
        return match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }

    protected function holdOrder(Order $order, array $reasons): void
    {
        try {
            $reason = 'Fraud protection hold: '.implode(' ', array_slice($reasons, 0, 3));
            $this->orderHoldService->placeOnHold($order, $reason);
        } catch (Throwable $exception) {
            logger()->warning('Fraud hold placement failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function logAudit(string $action, array $metadata): void
    {
        try {
            $this->auditService->log(
                'customer.fraud',
                $action,
                'fraud',
                'Customer fraud protection event',
                $this->safeMetadata($metadata)
            );
        } catch (Throwable $exception) {
            logger()->warning('Customer fraud audit logging failed', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function isBlacklisted(string $phone): bool
    {
        return $phone !== '' && CustomerBlacklist::query()
            ->where('active', true)
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $phone))
            ->exists();
    }

    protected function findCustomerByPhone(string $phone): ?Customer
    {
        if ($phone === '') {
            return null;
        }

        return Customer::query()
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $phone))
            ->first();
    }

    protected function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }

    protected function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->except(['phone', 'email', 'address', 'customer_phone', 'customer_email', 'customer_address'])
            ->all();
    }

    protected function evaluateMarketingSegments(?Customer $customer, string $trigger): void
    {
        if (! $customer) {
            return;
        }

        try {
            $this->marketingSegmentEngine->evaluateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment fraud evaluation failed', [
                'customer_id' => $customer->id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function triggerAutomation(string $triggerKey, mixed $subject, array $context = []): void
    {
        try {
            app(AutomationEngineService::class)->handleTrigger($triggerKey, $subject, $context);
        } catch (Throwable $exception) {
            logger()->warning('Fraud automation trigger failed', [
                'trigger_key' => $triggerKey,
                'subject_type' => is_object($subject) ? class_basename($subject) : null,
                'subject_id' => $subject instanceof \Illuminate\Database\Eloquent\Model ? $subject->getKey() : null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
