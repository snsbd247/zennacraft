<?php

namespace App\Modules\Customer\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerSegment;
use Illuminate\Support\Carbon;
use Throwable;

class CustomerSegmentationService
{
    public const SEGMENTS = [
        'VIP',
        'LOYAL',
        'REGULAR',
        'NEW',
        'AT_RISK',
        'BLOCKED',
    ];

    public function __construct(private AuditService $auditService) {}

    public function recalculateCustomer(Customer $customer, string $trigger = 'manual'): CustomerSegment
    {
        $metrics = $this->metrics($customer);
        $segmentName = $this->segment($customer, $metrics);

        $segment = CustomerSegment::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'segment' => $segmentName,
                'lifetime_value' => $metrics['lifetime_value'],
                'total_orders' => $metrics['total_orders'],
                'delivered_orders' => $metrics['delivered_orders'],
                'cancelled_orders' => $metrics['cancelled_orders'],
                'returned_orders' => $metrics['returned_orders'],
                'last_order_at' => $metrics['last_order_at'],
                'last_calculated_at' => now(),
            ]
        );

        $this->logAudit($customer, $segment, $trigger);

        return $segment;
    }

    public function recalculateAll(string $trigger = 'manual'): int
    {
        $count = 0;

        Customer::query()
            ->orderBy('id')
            ->each(function (Customer $customer) use (&$count, $trigger) {
                $this->recalculateCustomer($customer, $trigger);
                $count++;
            });

        return $count;
    }

    public function segment(Customer $customer, ?array $metrics = null): string
    {
        $customer->loadMissing('riskProfile');
        $metrics ??= $this->metrics($customer);

        if ($metrics['active_hold_orders'] > 0) {
            return 'BLOCKED';
        }

        if ($customer->riskProfile?->risk_level === 'high') {
            return 'AT_RISK';
        }

        if (
            $metrics['delivered_orders'] >= 5
            && $metrics['lifetime_value'] >= $this->vipLifetimeValueThreshold()
        ) {
            return 'VIP';
        }

        if ($metrics['delivered_orders'] >= 3) {
            return 'LOYAL';
        }

        if ($metrics['delivered_orders'] >= 1) {
            return 'REGULAR';
        }

        return 'NEW';
    }

    public function summary(): array
    {
        $counts = CustomerSegment::query()
            ->selectRaw('segment, COUNT(*) as total')
            ->groupBy('segment')
            ->pluck('total', 'segment')
            ->all();

        return collect(self::SEGMENTS)
            ->mapWithKeys(fn (string $segment) => [$segment => (int) ($counts[$segment] ?? 0)])
            ->all();
    }

    protected function metrics(Customer $customer): array
    {
        $summary = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END), 0) as returned_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as lifetime_value")
            ->selectRaw("COALESCE(SUM(CASE WHEN risk_hold_status = 'active' THEN 1 ELSE 0 END), 0) as active_hold_orders")
            ->selectRaw('MAX(created_at) as last_order_at')
            ->first();

        return [
            'lifetime_value' => (float) $summary->lifetime_value,
            'total_orders' => (int) $summary->total_orders,
            'delivered_orders' => (int) $summary->delivered_orders,
            'cancelled_orders' => (int) $summary->cancelled_orders,
            'returned_orders' => (int) $summary->returned_orders,
            'active_hold_orders' => (int) $summary->active_hold_orders,
            'last_order_at' => $summary->last_order_at ? Carbon::parse($summary->last_order_at) : null,
        ];
    }

    protected function vipLifetimeValueThreshold(): float
    {
        return (float) config('customer_segments.vip_lifetime_value', 10000);
    }

    protected function logAudit(Customer $customer, CustomerSegment $segment, string $trigger): void
    {
        try {
            $this->auditService->log(
                'customer.segment',
                'customer.segment.recalculate',
                'customer',
                'Customer segment recalculated',
                [
                    'customer_id' => $customer->id,
                    'segment' => $segment->segment,
                    'trigger' => $trigger,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Customer segment audit logging failed', [
                'customer_id' => $customer->id,
                'segment' => $segment->segment,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
