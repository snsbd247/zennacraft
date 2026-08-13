<?php

namespace App\Modules\RTO\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerSegmentationService;
use App\Modules\Order\Models\Order;
use App\Modules\RTO\Models\CustomerRiskProfile;
use Throwable;

class CustomerRiskService
{
    public function __construct(
        private AuditService $auditService,
        private OrderRiskService $orderRiskService,
        private CustomerSegmentationService $customerSegmentationService,
    ) {}

    public function recalculateCustomer(Customer $customer, string $trigger = 'manual'): CustomerRiskProfile
    {
        $profile = $this->calculateAndPersist($customer);
        $this->recalculateOrderRisksForCustomer($customer, 'customer_risk:'.$trigger);
        $this->recalculateCustomerSegment($customer, 'customer_risk:'.$trigger);

        $this->logAudit([
            'customer_id' => $customer->id,
            'count' => null,
            'trigger' => $trigger,
        ]);

        return $profile;
    }

    public function recalculateAll(string $trigger = 'manual'): int
    {
        $count = 0;

        Customer::query()
            ->orderBy('id')
            ->each(function (Customer $customer) use (&$count, $trigger) {
                $this->calculateAndPersist($customer);
                $this->recalculateOrderRisksForCustomer($customer, 'customer_risk:'.$trigger);
                $this->recalculateCustomerSegment($customer, 'customer_risk:'.$trigger);
                $count++;
            });

        $this->logAudit([
            'customer_id' => null,
            'count' => $count,
            'trigger' => $trigger,
        ]);

        return $count;
    }

    public function score(Customer $customer): int
    {
        return $this->scoreFromMetrics($this->metricsForCustomer($customer));
    }

    public function riskLevel(int $score): string
    {
        if ($score >= 70) {
            return 'high';
        }

        if ($score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    public function summary(): array
    {
        return [
            'high' => CustomerRiskProfile::where('risk_level', 'high')->count(),
            'medium' => CustomerRiskProfile::where('risk_level', 'medium')->count(),
            'low' => CustomerRiskProfile::where('risk_level', 'low')->count(),
        ];
    }

    protected function calculateAndPersist(Customer $customer): CustomerRiskProfile
    {
        $metrics = $this->metricsForCustomer($customer);
        $score = $this->scoreFromMetrics($metrics);

        return CustomerRiskProfile::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'risk_score' => $score,
                'risk_level' => $this->riskLevel($score),
                'total_orders' => $metrics['total_orders'],
                'delivered_orders' => $metrics['delivered_orders'],
                'cancelled_orders' => $metrics['cancelled_orders'],
                'returned_orders' => $metrics['returned_orders'],
                'delivery_rate' => $metrics['delivery_rate'],
                'cancel_rate' => $metrics['cancel_rate'],
                'return_rate' => $metrics['return_rate'],
                'last_calculated_at' => now(),
            ]
        );
    }

    protected function metricsForCustomer(Customer $customer): array
    {
        $orders = Order::where('customer_id', $customer->id)->get(['status']);
        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', 'delivered')->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $returnedOrders = $orders->where('status', 'returned')->count();

        return [
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'cancelled_orders' => $cancelledOrders,
            'returned_orders' => $returnedOrders,
            'delivery_rate' => $this->rate($deliveredOrders, $totalOrders),
            'cancel_rate' => $this->rate($cancelledOrders, $totalOrders),
            'return_rate' => $this->rate($returnedOrders, $totalOrders),
        ];
    }

    protected function scoreFromMetrics(array $metrics): int
    {
        if ($metrics['total_orders'] === 0) {
            return 0;
        }

        $score = 0;
        $score += $metrics['returned_orders'] * 25;
        $score += $metrics['cancelled_orders'] * 15;

        if ($metrics['total_orders'] >= 3) {
            if ($metrics['delivery_rate'] < 30) {
                $score += 35;
            } elseif ($metrics['delivery_rate'] < 50) {
                $score += 20;
            }
        }

        if ($metrics['return_rate'] >= 30) {
            $score += 20;
        }

        if ($metrics['cancel_rate'] >= 30) {
            $score += 15;
        }

        return max(0, min(100, $score));
    }

    protected function rate(int $part, int $total): float
    {
        if ($total === 0) {
            return 0;
        }

        return round(($part / $total) * 100, 2);
    }

    protected function logAudit(array $metadata): void
    {
        try {
            $this->auditService->log(
                'risk',
                'risk.recalculate',
                'risk',
                'Customer RTO risk recalculated',
                $metadata
            );
        } catch (Throwable $exception) {
            logger()->warning('Customer RTO risk audit logging failed', [
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function recalculateOrderRisksForCustomer(Customer $customer, string $trigger): void
    {
        $customer->orders()
            ->orderBy('id')
            ->each(function (Order $order) use ($customer, $trigger) {
                try {
                    $this->orderRiskService->recalculateOrder($order, $trigger);
                } catch (Throwable $exception) {
                    logger()->warning('Order RTO risk recalculation after customer risk failed', [
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'trigger' => $trigger,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
    }

    protected function recalculateCustomerSegment(Customer $customer, string $trigger): void
    {
        try {
            $this->customerSegmentationService->recalculateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Customer segment recalculation after risk failed', [
                'customer_id' => $customer->id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
