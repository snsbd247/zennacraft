<?php

namespace App\Modules\Customer\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Courier\Services\CourierPerformanceService;
use App\Modules\Fraud\Services\CustomerFraudService;
use App\Modules\Tracking\Models\ShipmentTrackingEvent;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Illuminate\Support\Carbon;

class Customer360Service
{
    public function __construct(
        private CustomerFollowUpService $followUpService,
        private CustomerFraudService $fraudService,
        private CustomerLifecycleService $lifecycleService,
        private CustomerRetentionService $retentionService,
        private CourierPerformanceService $courierPerformanceService,
    ) {}

    public function profile(Customer $customer): array
    {
        return [
            'order_metrics' => $this->orderMetrics($customer),
            'financial_metrics' => $this->financialMetrics($customer),
            'risk_metrics' => $this->riskMetrics($customer),
            'fraud_profile' => $this->fraudService->customerFraudProfile($customer),
            'follow_up_summary' => $this->followUpService->customerFollowUpSummary($customer),
            'lifecycle_profile' => $this->lifecycleService->lifecycleProfile($customer),
            'retention_profile' => $this->retentionService->profile($customer),
            'communication_timeline' => $this->communicationTimeline($customer),
            'verification_summary' => $this->verificationSummary($customer),
            'verification_timeline' => $this->verificationTimeline($customer),
            'tracking_timeline' => $this->trackingTimeline($customer),
            'courier_history' => $this->courierPerformanceService->customerCourierHistory($customer, $customer->phone),
            'recent_orders' => $this->recentOrders($customer),
        ];
    }

    public function orderMetrics(Customer $customer): array
    {
        $summary = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END), 0) as shipped_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END), 0) as returned_orders")
            ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->first();

        $totalOrders = (int) $summary->total_orders;
        $pendingOrders = (int) $summary->pending_orders;
        $confirmedOrders = (int) $summary->confirmed_orders;
        $shippedOrders = (int) $summary->shipped_orders;
        $deliveredOrders = (int) $summary->delivered_orders;
        $cancelledOrders = (int) $summary->cancelled_orders;
        $returnedOrders = (int) $summary->returned_orders;
        $totalRevenue = (float) $summary->total_revenue;

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'confirmed_orders' => $confirmedOrders,
            'shipped_orders' => $shippedOrders,
            'delivered_orders' => $deliveredOrders,
            'cancelled_orders' => $cancelledOrders,
            'returned_orders' => $returnedOrders,
            'delivery_rate' => $this->percentage($deliveredOrders, $totalOrders),
            'cancel_rate' => $this->percentage($cancelledOrders, $totalOrders),
            'return_rate' => $this->percentage($returnedOrders, $totalOrders),
            'average_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0,
            'last_order_at' => $summary->last_order_at ? Carbon::parse($summary->last_order_at) : null,
        ];
    }

    public function financialMetrics(Customer $customer): array
    {
        $summary = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders")
            ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as delivered_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN total ELSE 0 END), 0) as returned_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END), 0) as cancelled_revenue")
            ->selectRaw('COALESCE(SUM(product_cost_total), 0) as product_cost_total')
            ->selectRaw('COALESCE(SUM(courier_cost_total), 0) as courier_cost_total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN gross_profit ELSE 0 END), 0) as gross_profit_total")
            ->first();

        $totalOrders = (int) $summary->total_orders;
        $deliveredOrders = (int) $summary->delivered_orders;
        $grossProfitTotal = (float) $summary->gross_profit_total;
        $totalRevenue = (float) $summary->total_revenue;
        $deliveredRevenue = (float) $summary->delivered_revenue;
        $cancelledRtoValue = (float) $summary->returned_revenue + (float) $summary->cancelled_revenue;
        // COD: profit is only realized on delivery, so both the margin and
        // the profitability tier it drives are measured against
        // delivered-only revenue, not total_revenue (which still includes
        // cancelled/returned/pending orders that never generated real
        // money).
        $profitMargin = $this->percentage($grossProfitTotal, $deliveredRevenue);

        return [
            'total_revenue' => $totalRevenue,
            'delivered_revenue' => $deliveredRevenue,
            'returned_revenue' => (float) $summary->returned_revenue,
            'cancelled_revenue' => (float) $summary->cancelled_revenue,
            'cancelled_rto_value' => $cancelledRtoValue,
            'product_cost_total' => (float) $summary->product_cost_total,
            'courier_cost_total' => (float) $summary->courier_cost_total,
            'gross_profit_total' => $grossProfitTotal,
            'profit_margin' => $profitMargin,
            'profitability_tier' => $this->profitabilityTier($grossProfitTotal, $profitMargin),
            'average_profit_per_order' => $deliveredOrders > 0 ? round($grossProfitTotal / $deliveredOrders, 2) : 0.0,
        ];
    }

    public function riskMetrics(Customer $customer): array
    {
        $customer->loadMissing('riskProfile');

        $riskSummary = $customer->orders()
            ->leftJoin('order_risk_profiles', 'orders.id', '=', 'order_risk_profiles.order_id')
            ->selectRaw('COALESCE(MAX(order_risk_profiles.risk_score), 0) as highest_order_risk_score')
            ->selectRaw("COALESCE(SUM(CASE WHEN order_risk_profiles.risk_level = 'high' THEN 1 ELSE 0 END), 0) as high_risk_order_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.risk_hold_status = 'active' THEN 1 ELSE 0 END), 0) as active_hold_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.risk_hold_released_at IS NOT NULL THEN 1 ELSE 0 END), 0) as released_hold_count")
            ->first();

        return [
            'customer_risk_score' => $customer->riskProfile?->risk_score,
            'customer_risk_level' => $customer->riskProfile?->risk_level,
            'customer_cod_score' => $customer->cod_score,
            'highest_order_risk_score' => (int) $riskSummary->highest_order_risk_score,
            'high_risk_order_count' => (int) $riskSummary->high_risk_order_count,
            'active_hold_count' => (int) $riskSummary->active_hold_count,
            'released_hold_count' => (int) $riskSummary->released_hold_count,
        ];
    }

    public function communicationTimeline(Customer $customer)
    {
        return $customer->communications()
            ->with(['order', 'staffUser'])
            ->latest()
            ->limit(10)
            ->get();
    }

    public function verificationTimeline(Customer $customer)
    {
        return OrderVerificationAttempt::query()
            ->with(['order', 'staffUser'])
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest()
            ->limit(10)
            ->get();
    }

    public function verificationSummary(Customer $customer): array
    {
        $orderSummary = $customer->orders()
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END), 0) as verified_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status IN ('call_later', 'no_answer') THEN 1 ELSE 0 END), 0) as callback_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status = 'failed' THEN 1 ELSE 0 END), 0) as failed_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_orders")
            ->first();

        $attemptSummary = OrderVerificationAttempt::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->selectRaw('COUNT(*) as total_attempts')
            ->selectRaw("COALESCE(SUM(CASE WHEN outcome = 'verified' THEN 1 ELSE 0 END), 0) as verified_attempts")
            ->selectRaw("COALESCE(SUM(CASE WHEN outcome IN ('call_later', 'callback_requested', 'no_answer') THEN 1 ELSE 0 END), 0) as callback_attempts")
            ->selectRaw("COALESCE(SUM(CASE WHEN outcome IN ('wrong_number', 'failed', 'failed_verification', 'duplicate_order', 'suspicious') THEN 1 ELSE 0 END), 0) as failed_attempts")
            ->first();

        $latestAttempt = OrderVerificationAttempt::query()
            ->with(['order', 'staffUser'])
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest()
            ->first();

        return [
            'verified_orders' => (int) $orderSummary->verified_orders,
            'callback_orders' => (int) $orderSummary->callback_orders,
            'failed_orders' => (int) $orderSummary->failed_orders,
            'cancelled_orders' => (int) $orderSummary->cancelled_orders,
            'total_attempts' => (int) $attemptSummary->total_attempts,
            'verified_attempts' => (int) $attemptSummary->verified_attempts,
            'callback_attempts' => (int) $attemptSummary->callback_attempts,
            'failed_attempts' => (int) $attemptSummary->failed_attempts,
            'latest_outcome' => $latestAttempt?->outcome,
            'latest_attempt_at' => $latestAttempt?->created_at,
            'latest_staff' => $latestAttempt?->staffUser,
            'latest_order' => $latestAttempt?->order,
        ];
    }

    public function trackingTimeline(Customer $customer)
    {
        return ShipmentTrackingEvent::query()
            ->with(['order', 'shipment'])
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->orderByDesc('event_time')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    public function recentOrders(Customer $customer)
    {
        return $customer->orders()
            ->with('riskProfile')
            ->latest()
            ->limit(10)
            ->get();
    }

    protected function percentage(float|int $value, float|int $total): float
    {
        return (float) $total > 0 ? round(((float) $value / (float) $total) * 100, 2) : 0.0;
    }

    protected function profitabilityTier(float $grossProfit, float $profitMargin): string
    {
        if ($grossProfit < 0) {
            return 'Loss Risk';
        }

        if ($profitMargin >= 30) {
            return 'High Profit';
        }

        if ($profitMargin >= 10) {
            return 'Profitable';
        }

        return 'Low Margin';
    }
}
