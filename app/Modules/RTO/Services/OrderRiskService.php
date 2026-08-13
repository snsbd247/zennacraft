<?php

namespace App\Modules\RTO\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Order\Models\Order;
use App\Modules\RTO\Models\OrderRiskProfile;
use Throwable;

class OrderRiskService
{
    public function __construct(
        private AuditService $auditService,
        private OrderHoldService $orderHoldService,
    ) {}

    public function recalculateOrder(Order $order, string $trigger = 'manual'): OrderRiskProfile
    {
        $order->loadMissing([
            'customer.riskProfile',
            'shipment.courierProvider.metric',
        ]);

        $customerRisk = $this->customerRisk($order);
        $courierRisk = $this->courierRisk($order);
        $verificationRisk = $this->verificationRisk($order);
        $riskScore = max(0, min(100, $customerRisk['score'] + $courierRisk['score'] + $verificationRisk['score']));
        $riskLevel = $this->riskLevel($riskScore);
        $reasons = array_values(array_filter(array_merge(
            $customerRisk['reasons'],
            $courierRisk['reasons'],
            $verificationRisk['reasons']
        )));

        $profile = OrderRiskProfile::updateOrCreate(
            ['order_id' => $order->id],
            [
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel,
                'risk_reasons' => $reasons,
                'customer_risk_score' => $customerRisk['score'],
                'courier_risk_score' => $courierRisk['score'],
                'verification_risk_score' => $verificationRisk['score'],
                'last_calculated_at' => now(),
            ]
        );

        $this->logAudit($order, $profile, $trigger);
        $this->evaluateHold($order->refresh()->loadMissing(['customer.riskProfile', 'riskProfile']), $trigger);

        return $profile;
    }

    public function recalculateAll(string $trigger = 'manual'): int
    {
        $count = 0;

        Order::query()
            ->orderBy('id')
            ->each(function (Order $order) use (&$count, $trigger) {
                $this->recalculateOrder($order, $trigger);
                $count++;
            });

        return $count;
    }

    public function summary(): array
    {
        return [
            'high' => OrderRiskProfile::where('risk_level', 'high')->count(),
            'medium' => OrderRiskProfile::where('risk_level', 'medium')->count(),
            'low' => OrderRiskProfile::where('risk_level', 'low')->count(),
        ];
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

    public function shouldHoldOrder(Order $order): bool
    {
        $order->loadMissing(['customer.riskProfile', 'riskProfile']);

        if (($order->riskProfile?->risk_score ?? 0) >= 70) {
            return true;
        }

        if ($order->customer?->riskProfile?->risk_level !== 'high') {
            return false;
        }

        return $this->latestVerificationOutcome($order) !== 'verified';
    }

    protected function customerRisk(Order $order): array
    {
        $profile = $order->customer?->riskProfile;

        if (! $profile) {
            return ['score' => 0, 'reasons' => []];
        }

        $score = match ($profile->risk_level) {
            'high' => 50,
            'medium' => 25,
            default => 0,
        };

        $reasons = [];

        if ($profile->risk_level === 'high') {
            $reasons[] = 'Customer has high risk profile';
        } elseif ($profile->risk_level === 'medium') {
            $reasons[] = 'Customer has medium risk profile';
        }

        if ((float) $profile->return_rate >= 30) {
            $reasons[] = 'Customer return rate is high';
        }

        if ((float) $profile->cancel_rate >= 30) {
            $reasons[] = 'Customer cancel rate is high';
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    protected function courierRisk(Order $order): array
    {
        $returnRate = $order->shipment?->courierProvider?->metric?->return_rate;

        if ($returnRate === null) {
            return ['score' => 0, 'reasons' => []];
        }

        $returnRate = (float) $returnRate;
        $score = match (true) {
            $returnRate >= 30 => 35,
            $returnRate >= 20 => 20,
            $returnRate >= 10 => 10,
            default => 0,
        };

        $reasons = [];
        if ($score > 0) {
            $reasons[] = $returnRate >= 30
                ? 'Courier has high return rate'
                : 'Courier return rate is elevated';
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    protected function verificationRisk(Order $order): array
    {
        $attempt = $this->latestVerificationAttempt($order);

        if (! $attempt) {
            return ['score' => 0, 'reasons' => []];
        }

        $score = match ($attempt->outcome) {
            'verified' => 0,
            'call_later' => 10,
            'no_answer' => 20,
            'wrong_number' => 30,
            'failed' => 40,
            'customer_cancelled' => 50,
            default => 0,
        };

        $reasons = [];
        if ($score > 0) {
            $reasons[] = 'Verification outcome '.str_replace('_', ' ', $attempt->outcome);
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    protected function evaluateHold(Order $order, string $trigger): void
    {
        try {
            if (! $this->shouldHoldOrder($order)) {
                return;
            }

            $this->orderHoldService->placeOnHold($order, $this->holdReason($order, $trigger));
        } catch (Throwable $exception) {
            logger()->warning('Order risk hold evaluation failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function holdReason(Order $order, string $trigger): string
    {
        $reasons = [];

        if (($order->riskProfile?->risk_score ?? 0) >= 70) {
            $reasons[] = 'Order RTO risk score is '.$order->riskProfile->risk_score.'.';
        }

        if ($order->customer?->riskProfile?->risk_level === 'high' && $this->latestVerificationOutcome($order) !== 'verified') {
            $reasons[] = 'Customer risk is high and latest verification outcome is not verified.';
        }

        $reasons[] = 'Trigger: '.$trigger.'.';

        return implode(' ', $reasons);
    }

    protected function latestVerificationOutcome(Order $order): ?string
    {
        return $this->latestVerificationAttempt($order)?->outcome;
    }

    protected function latestVerificationAttempt(Order $order): ?object
    {
        return $order->verificationAttempts()
            ->latest()
            ->first();
    }

    protected function logAudit(Order $order, OrderRiskProfile $profile, string $trigger): void
    {
        try {
            $this->auditService->log(
                'order.risk',
                'order.risk.recalculate',
                'risk',
                'Order RTO risk recalculated: '.$order->order_number,
                [
                    'order_id' => $order->id,
                    'risk_score' => $profile->risk_score,
                    'risk_level' => $profile->risk_level,
                    'trigger' => $trigger,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Order RTO risk audit logging failed', [
                'order_id' => $order->id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
