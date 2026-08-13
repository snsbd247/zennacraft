<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Customer\Models\Customer;
use App\Modules\Expense\Models\Expense;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Services\MarketingAnalyticsService;
use App\Modules\Marketing\Services\MarketingCampaignService;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Review\Services\ProductReviewService;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExecutiveAnalyticsService
{
    public const REVENUE_EXCLUDED_STATUSES = ['cancelled', 'returned'];

    public const ORDER_STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'returned',
    ];

    public function __construct(
        private CacheService $cacheService,
        private MarketingSegmentEngineService $marketingSegmentEngine,
        private MarketingCampaignService $marketingCampaignService,
        private MarketingAnalyticsService $marketingAnalyticsService,
        private BehaviorEventService $behaviorEventService,
        private ProductReviewService $reviewService,
    ) {}

    public function dashboard(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::ANALYTICS_DASHBOARD,
            fn (): array => $this->buildDashboard(),
            300,
            [CacheKeyRegistry::ANALYTICS_TAG]
        );
    }

    protected function buildDashboard(): array
    {
        return [
            'executive' => $this->executiveMetrics(),
            'revenue' => $this->revenueAnalytics(),
            'profit' => $this->profitAnalytics(),
            'orders' => $this->orderAnalytics(),
            'customers' => $this->customerAnalytics(),
            'products' => $this->productAnalytics(),
            'coupons' => $this->couponAnalytics(),
            'fraud' => $this->fraudAnalytics(),
            'marketing' => $this->marketingAnalytics(),
            'marketing_segments' => $this->marketingSegmentAnalytics(),
            'marketing_campaigns' => $this->marketingCampaignAnalytics(),
            'trust' => $this->reviewService->reviewMetrics(),
            'intelligence' => $this->intelligenceCenter(),
        ];
    }

    public function executiveMetrics(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $lastSeven = now()->subDays(6)->startOfDay();
        $lastThirty = now()->subDays(29)->startOfDay();
        $monthStart = now()->startOfMonth();

        return [
            'today_revenue' => $this->revenueFor($today, now()->endOfDay()),
            'yesterday_revenue' => $this->revenueFor($yesterday, $yesterday->copy()->endOfDay()),
            'seven_day_revenue' => $this->revenueFor($lastSeven, now()->endOfDay()),
            'thirty_day_revenue' => $this->revenueFor($lastThirty, now()->endOfDay()),
            'monthly_revenue' => $this->revenueFor($monthStart, now()->endOfDay()),
            'today_profit' => $this->profitFor($today, now()->endOfDay()),
            'seven_day_profit' => $this->profitFor($lastSeven, now()->endOfDay()),
            'thirty_day_profit' => $this->profitFor($lastThirty, now()->endOfDay()),
            'today_orders' => Order::query()->whereDate('created_at', today())->count(),
            'pending_orders' => Order::query()->where('status', 'pending')->count(),
            'delivered_orders' => Order::query()->where('status', 'delivered')->count(),
            'cancelled_orders' => Order::query()->where('status', 'cancelled')->count(),
            'returned_orders' => Order::query()->where('status', 'returned')->count(),
            'new_customers' => Customer::query()->whereDate('created_at', today())->count(),
            'returning_customers' => Customer::query()->where('total_orders', '>', 1)->count(),
            'fraud_alerts' => FraudEvent::query()->whereDate('created_at', today())->count(),
            'fraud_holds' => Order::query()->where('risk_hold_status', 'active')->count(),
            'active_coupons' => $this->activeCouponsQuery()->count(),
            'coupon_revenue' => $this->couponRevenue(),
            'campaign_revenue' => (float) MarketingCampaign::query()->sum('total_revenue'),
            'campaign_conversions' => (int) MarketingCampaign::query()->sum('total_converted'),
        ];
    }

    public function revenueAnalytics(): array
    {
        return [
            'today' => $this->revenueFor(now()->startOfDay(), now()->endOfDay()),
            'yesterday' => $this->revenueFor(now()->subDay()->startOfDay(), now()->subDay()->endOfDay()),
            'seven_days' => $this->revenueFor(now()->subDays(6)->startOfDay(), now()->endOfDay()),
            'thirty_days' => $this->revenueFor(now()->subDays(29)->startOfDay(), now()->endOfDay()),
            'month' => $this->revenueFor(now()->startOfMonth(), now()->endOfDay()),
            'daily_trend' => $this->dailyTrend(now()->subDays(6)->startOfDay(), now()->endOfDay()),
        ];
    }

    public function profitAnalytics(): array
    {
        return [
            'today' => $this->profitBreakdown(now()->startOfDay(), now()->endOfDay()),
            'seven_days' => $this->profitBreakdown(now()->subDays(6)->startOfDay(), now()->endOfDay()),
            'thirty_days' => $this->profitBreakdown(now()->subDays(29)->startOfDay(), now()->endOfDay()),
            'month' => $this->profitBreakdown(now()->startOfMonth(), now()->endOfDay()),
        ];
    }

    public function orderAnalytics(): array
    {
        $statusCounts = Order::query()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $statuses = collect(self::ORDER_STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($statusCounts[$status] ?? 0)])
            ->all();

        $todayCount = Order::query()->whereDate('created_at', today())->count();
        $yesterdayCount = Order::query()->whereDate('created_at', today()->subDay())->count();

        return [
            'statuses' => $statuses,
            'trend' => [
                'today' => $todayCount,
                'yesterday' => $yesterdayCount,
                'difference' => $todayCount - $yesterdayCount,
            ],
            'daily_trend' => $this->dailyTrend(now()->subDays(6)->startOfDay(), now()->endOfDay()),
        ];
    }

    public function customerAnalytics(): array
    {
        $totalCustomers = Customer::query()->count();
        $repeatCustomers = Customer::query()->where('total_orders', '>', 1)->count();

        return [
            'total_customers' => $totalCustomers,
            'new_customers_today' => Customer::query()->whereDate('created_at', today())->count(),
            'repeat_customers' => $repeatCustomers,
            'repeat_purchase_rate' => $totalCustomers > 0 ? ($repeatCustomers / $totalCustomers) * 100 : 0.0,
            'top_by_revenue' => $this->topCustomersByRevenue(),
            'top_by_orders' => $this->topCustomersByOrders(),
        ];
    }

    public function productAnalytics(): array
    {
        return [
            'best_selling' => $this->productAggregate('quantity_sold'),
            'highest_revenue' => $this->productAggregate('revenue'),
            'most_ordered' => $this->productAggregate('orders_count'),
            'low_performance' => $this->lowPerformanceProducts(),
        ];
    }

    public function couponAnalytics(): array
    {
        return [
            'usage_count' => CouponUsage::query()->count(),
            'discount_given' => (float) CouponUsage::query()->sum('discount_amount'),
            'revenue_generated' => $this->couponRevenue(),
            'active_coupons' => $this->activeCouponsQuery()->count(),
            'top_coupons' => $this->topCoupons(),
        ];
    }

    public function fraudAnalytics(): array
    {
        return [
            'fraud_events_today' => FraudEvent::query()->whereDate('created_at', today())->count(),
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
            'duplicate_order_count' => FraudEvent::query()
                ->whereIn('type', ['duplicate_order', 'duplicate_order_blocked'])
                ->count(),
            'blacklist_count' => CustomerBlacklist::query()->where('active', true)->count(),
            'active_holds' => Order::query()->where('risk_hold_status', 'active')->count(),
        ];
    }

    public function marketingAnalytics(): array
    {
        $today = $this->marketingSummary(now()->startOfDay(), now()->endOfDay());
        $month = $this->marketingSummary(now()->startOfMonth(), now()->endOfDay());
        $overall = $this->marketingSummary();

        return [
            'today' => $today,
            'month' => $month,
            'overall' => $overall,
            'has_ad_spend' => ($today['ad_spend'] + $month['ad_spend'] + $overall['ad_spend']) > 0,
        ];
    }

    public function marketingSegmentAnalytics(): array
    {
        $summary = $this->marketingSegmentEngine->segmentSummary();

        return [
            'new_customers' => (int) ($summary['NEW_CUSTOMER']['count'] ?? 0),
            'repeat_customers' => (int) ($summary['REPEAT_CUSTOMER']['count'] ?? 0),
            'vip_customers' => (int) ($summary['VIP_CUSTOMER']['count'] ?? 0),
            'dormant_customers' => (int) ($summary['DORMANT_CUSTOMER']['count'] ?? 0),
            'at_risk_customers' => (int) ($summary['AT_RISK_CUSTOMER']['count'] ?? 0),
            'fraud_risk_customers' => (int) ($summary['FRAUD_RISK_CUSTOMER']['count'] ?? 0),
            'coupon_lovers' => (int) ($summary['COUPON_LOVER']['count'] ?? 0),
            'abandoned_checkout_customers' => (int) ($summary['ABANDONED_CHECKOUT_CUSTOMER']['count'] ?? 0),
        ];
    }

    public function marketingCampaignAnalytics(): array
    {
        return $this->marketingCampaignService->dashboardStats();
    }

    public function intelligenceCenter(): array
    {
        $marketingCommand = $this->marketingAnalyticsService->commandCenter();
        $topKpis = $this->intelligenceKpis($marketingCommand);
        $recoveryFunnel = $this->recoveryFunnel();
        $verificationFunnel = $this->verificationFunnel();
        $courierFunnel = $this->courierFunnel();
        $financeAnalytics = $this->financeIntelligence();
        $productIntelligence = $this->productIntelligence($marketingCommand);
        $customerIntelligence = $this->customerIntelligence();
        $geographicIntelligence = $this->geographicIntelligence();
        $timeAnalytics = $this->timeAnalytics();
        $marketingFunnel = $this->marketingFunnel($marketingCommand);
        $trustAnalytics = $this->reviewService->reviewMetrics();

        return [
            'kpis' => $topKpis,
            'commerce_funnel' => $this->commerceFunnel(),
            'recovery_funnel' => $recoveryFunnel,
            'verification_funnel' => $verificationFunnel,
            'courier_funnel' => $courierFunnel,
            'marketing_funnel' => $marketingFunnel,
            'finance' => $financeAnalytics,
            'products' => $productIntelligence,
            'customers' => $customerIntelligence,
            'trust' => $trustAnalytics,
            'geographic' => $geographicIntelligence,
            'time' => $timeAnalytics,
            'insights' => $this->ruleBasedInsights(
                $topKpis,
                $recoveryFunnel,
                $verificationFunnel,
                $courierFunnel,
                $marketingFunnel,
                $productIntelligence
            ),
            'drilldowns' => $this->drilldownLinks(),
            'reports' => $this->reportLinks(),
            'data_notes' => [
                'Product views, package selections, cart adds, checkout starts, coupon attempts, and order submission events use internal behavior tracking from Phase 54.',
                'Marketing source and automation revenue values are estimated where order-level attribution is incomplete.',
                'Geographic intelligence uses existing free-text order addresses and may group unknown or inconsistent districts.',
                'Review and loyalty metrics use approved review records plus delivered-order loyalty tiers.',
            ],
        ];
    }

    protected function intelligenceKpis(array $marketingCommand): array
    {
        $revenue = $this->revenueFor();
        $orderCount = (int) $this->revenueQuery()->count();
        $shipmentsCompleted = Shipment::query()->whereIn('status', ['delivered', 'returned', 'cancelled', 'failed'])->count();
        $deliveredShipments = Shipment::query()->where('status', 'delivered')->count();
        $completedOrders = Order::query()->whereIn('status', ['delivered', 'returned'])->count();
        $returnedOrders = Order::query()->where('status', 'returned')->count();
        $marketingDashboard = $marketingCommand['dashboard'] ?? [];

        return [
            'total_revenue' => $revenue,
            'net_profit' => $this->profitFor(),
            'orders' => Order::query()->count(),
            'customers' => Customer::query()->count(),
            'average_order_value' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0.0,
            'conversion_signals' => CustomerBehaviorEvent::query()
                ->whereIn('event_type', [
                    BehaviorEventService::EVENT_PRODUCT_VIEWED,
                    BehaviorEventService::EVENT_ADDED_TO_CART,
                    BehaviorEventService::EVENT_CHECKOUT_STARTED,
                    BehaviorEventService::EVENT_ORDER_SUBMITTED,
                ])
                ->count(),
            'recovery_revenue' => $this->recoveredRevenue(),
            'rto_rate' => $this->rate($returnedOrders, $completedOrders),
            'delivery_success_rate' => $this->rate($deliveredShipments, $shipmentsCompleted),
            'marketing_roas' => $marketingDashboard['roas'] ?? null,
            'automation_assisted_revenue' => $this->automationAssistedRevenue(),
            'is_estimated' => true,
        ];
    }

    protected function commerceFunnel(): array
    {
        $behaviorFunnel = $this->behaviorEventService->commerceFunnel();
        $cartAdds = (int) $behaviorFunnel['cart_adds'];
        $checkoutStarted = (int) $behaviorFunnel['checkout_started'];
        $orderSubmitted = (int) $behaviorFunnel['order_submitted'];

        return [
            'stages' => [
                ['label' => 'Product Views', 'value' => (int) $behaviorFunnel['product_views'], 'available' => true, 'note' => 'Internal product_viewed behavior events.'],
                ['label' => 'Cart Adds', 'value' => $cartAdds, 'available' => true, 'note' => 'Internal added_to_cart behavior events.'],
                ['label' => 'Checkout Started', 'value' => $checkoutStarted, 'available' => true, 'note' => 'Internal checkout_started behavior events.'],
                ['label' => 'Orders Created', 'value' => Order::query()->count(), 'available' => true, 'note' => 'All order records.'],
                ['label' => 'Order Submitted Events', 'value' => $orderSubmitted, 'available' => true, 'note' => 'Internal order_submitted behavior events.'],
                ['label' => 'Verified Orders', 'value' => Order::query()->where('verification_status', 'verified')->count(), 'available' => true, 'note' => 'Orders marked verified.'],
                ['label' => 'Courier Assigned', 'value' => Shipment::query()->whereNotNull('courier_provider_id')->count(), 'available' => true, 'note' => 'Shipments with assigned courier provider.'],
                ['label' => 'Delivered Orders', 'value' => Order::query()->where('status', 'delivered')->count(), 'available' => true, 'note' => 'Delivered orders.'],
            ],
            'rates' => [
                'cart_to_checkout' => $cartAdds > 0 ? round(($checkoutStarted / $cartAdds) * 100, 2) : 0.0,
                'checkout_to_order' => $checkoutStarted > 0 ? round(($orderSubmitted / $checkoutStarted) * 100, 2) : 0.0,
            ],
        ];
    }

    protected function recoveryFunnel(): array
    {
        $activeStatuses = ['open', 'contacted', 'called', 'no_answer', 'interested'];
        $totalRecoverable = (int) CheckoutRecovery::query()
            ->whereIn('status', array_merge($activeStatuses, ['recovered', 'lost', 'closed']))
            ->count();
        $recovered = (int) CheckoutRecovery::query()->where('status', 'recovered')->count();

        return [
            'abandoned_carts' => (int) CheckoutRecovery::query()
                ->whereIn('status', $activeStatuses)
                ->where(fn (Builder $query) => $query->whereNull('customer_phone')->orWhere('customer_phone', ''))
                ->count(),
            'incomplete_checkouts' => (int) CheckoutRecovery::query()
                ->whereIn('status', $activeStatuses)
                ->where(fn (Builder $query) => $query
                    ->whereNotNull('customer_phone')
                    ->orWhereNotNull('customer_email')
                    ->orWhereNotNull('customer_name')
                    ->orWhereNotNull('address'))
                ->count(),
            'callback_queue' => (int) CheckoutRecovery::query()
                ->whereIn('status', $activeStatuses)
                ->whereNotNull('customer_phone')
                ->count(),
            'contacted' => (int) CheckoutRecovery::query()->whereIn('status', ['contacted', 'called', 'no_answer', 'interested', 'not_interested'])->count(),
            'recovered' => $recovered,
            'lost' => (int) CheckoutRecovery::query()->whereIn('status', ['lost', 'closed', 'not_interested'])->count(),
            'recovered_revenue' => $this->recoveredRevenue(),
            'recovery_rate' => $totalRecoverable > 0 ? round(($recovered / $totalRecoverable) * 100, 2) : 0.0,
        ];
    }

    protected function verificationFunnel(): array
    {
        $finished = (int) Order::query()->whereIn('verification_status', ['verified', 'failed', 'cancelled'])->count();
        $verified = (int) Order::query()->where('verification_status', 'verified')->count();

        return [
            'pending' => (int) Order::query()->whereNotIn('verification_status', ['verified', 'cancelled'])->count(),
            'called' => (int) Order::query()->whereHas('verificationAttempts')->count(),
            'verified' => $verified,
            'callback_requested' => (int) Order::query()->whereIn('verification_status', ['call_later', 'no_answer'])->count(),
            'no_answer' => (int) OrderVerificationAttempt::query()->where('outcome', 'no_answer')->count(),
            'failed_verification' => (int) Order::query()->where('verification_status', 'failed')->count(),
            'cancelled' => (int) Order::query()->where('verification_status', 'cancelled')->count(),
            'success_rate' => $finished > 0 ? round(($verified / $finished) * 100, 2) : 0.0,
        ];
    }

    protected function courierFunnel(): array
    {
        $completed = (int) Shipment::query()->whereIn('status', ['delivered', 'returned', 'cancelled', 'failed'])->count();
        $delivered = (int) Shipment::query()->where('status', 'delivered')->count();
        $returned = (int) Shipment::query()->whereIn('status', ['returned', 'failed'])->count();
        $providerRows = $this->courierProviderRows();
        $best = collect($providerRows)->where('total', '>', 0)->sortByDesc('success_rate')->first();
        $worst = collect($providerRows)->where('total', '>', 0)->sortBy('success_rate')->first();

        return [
            'courier_assigned' => (int) Shipment::query()->whereNotNull('courier_provider_id')->count(),
            'in_transit' => (int) Shipment::query()->whereIn('status', ['pending', 'assigned', 'shipped'])->count(),
            'delivered' => $delivered,
            'returned_rto' => $returned,
            'cancelled' => (int) Shipment::query()->where('status', 'cancelled')->count(),
            'delivery_success_rate' => $this->rate($delivered, $completed),
            'rto_rate' => $this->rate($returned, $completed),
            'best_courier' => $best['provider'] ?? null,
            'worst_courier' => $worst['provider'] ?? null,
            'providers' => $providerRows,
        ];
    }

    protected function marketingFunnel(array $marketingCommand): array
    {
        $dashboard = $marketingCommand['dashboard'] ?? [];

        return [
            'campaigns' => MarketingCampaign::query()->count(),
            'coupon_uses' => CouponUsage::query()->count(),
            'landing_page_orders' => (int) ($dashboard['landing_page_orders'] ?? 0),
            'attributed_orders' => (int) ($dashboard['orders_from_marketing'] ?? 0),
            'marketing_revenue' => (float) ($dashboard['marketing_revenue'] ?? 0),
            'ad_spend' => (float) ($dashboard['ad_spend'] ?? 0),
            'profit_after_ads' => (float) ($dashboard['profit_after_ads'] ?? 0),
            'roas' => $dashboard['roas'] ?? null,
            'cpa' => $dashboard['cpa'] ?? null,
            'sources' => $marketingCommand['sources'] ?? [],
            'is_estimated' => (bool) ($dashboard['is_estimated'] ?? true),
        ];
    }

    protected function financeIntelligence(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();
        $orders = Order::query()
            ->selectRaw("DATE(created_at) as bucket")
            ->selectRaw("COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'returned') THEN total ELSE 0 END), 0) as revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as delivered_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN gross_profit ELSE 0 END), 0) as profit")
            ->selectRaw("COALESCE(SUM(courier_cost_total), 0) as courier_cost")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN total ELSE 0 END), 0) as rto_loss")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');
        $expenses = Expense::query()
            ->selectRaw("DATE(expense_date) as bucket")
            ->selectRaw('COALESCE(SUM(amount), 0) as expense')
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');
        $adSpend = AdSpend::query()
            ->selectRaw("DATE(spend_date) as bucket")
            ->selectRaw('COALESCE(SUM(amount), 0) as ad_spend')
            ->whereDate('spend_date', '>=', $start->toDateString())
            ->whereDate('spend_date', '<=', $end->toDateString())
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $trend = [];
        $deliveredRevenueTotal = 0.0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $orderRow = $orders->get($key);
            $revenue = (float) ($orderRow?->revenue ?? 0);
            $deliveredRevenue = (float) ($orderRow?->delivered_revenue ?? 0);
            $deliveredRevenueTotal += $deliveredRevenue;
            $profit = (float) ($orderRow?->profit ?? 0);

            $trend[] = [
                'date' => $key,
                'revenue' => round($revenue, 2),
                'profit' => round($profit, 2),
                'expense' => round((float) ($expenses->get($key)?->expense ?? 0), 2),
                'ad_spend' => round((float) ($adSpend->get($key)?->ad_spend ?? 0), 2),
                'courier_cost' => round((float) ($orderRow?->courier_cost ?? 0), 2),
                'rto_loss' => round((float) ($orderRow?->rto_loss ?? 0), 2),
                // Margin is profit over the SAME delivered-only revenue, not
                // the broader pipeline revenue above it — otherwise a high
                // not-yet-delivered order volume would silently depress
                // margin against profit that hasn't happened yet.
                'margin' => $this->rate($profit, $deliveredRevenue),
            ];
        }

        return [
            'trend' => $trend,
            'summary' => [
                'revenue' => round((float) collect($trend)->sum('revenue'), 2),
                'profit' => round((float) collect($trend)->sum('profit'), 2),
                'expenses' => round((float) collect($trend)->sum('expense'), 2),
                'ad_spend' => round((float) collect($trend)->sum('ad_spend'), 2),
                'courier_cost' => round((float) collect($trend)->sum('courier_cost'), 2),
                'rto_loss' => round((float) collect($trend)->sum('rto_loss'), 2),
                'margin' => $this->rate((float) collect($trend)->sum('profit'), $deliveredRevenueTotal),
            ],
        ];
    }

    protected function productIntelligence(array $marketingCommand): array
    {
        return [
            'top_by_revenue' => $this->productAggregate('revenue'),
            'top_by_orders' => $this->productAggregate('orders_count'),
            'top_packages' => $this->topPackages(),
            'low_stock_high_demand' => $this->lowStockHighDemand(),
            'high_revenue_low_profit' => $this->highRevenueLowProfitProducts(),
            'rto_impact' => $this->productRtoImpact(),
            'marketing_performance' => $marketingCommand['products'] ?? [],
            'behavior_funnel' => $this->behaviorEventService->productFunnel(),
        ];
    }

    protected function customerIntelligence(): array
    {
        $segmentSummary = $this->marketingSegmentAnalytics();
        $revenue = (float) Order::query()->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)->sum('total');
        $profit = (float) Order::query()->where('status', 'delivered')->sum('gross_profit');
        $customers = max(1, Customer::query()->count());
        $orders = max(1, Order::query()->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)->count());

        return [
            'new_customers' => $segmentSummary['new_customers'] ?? 0,
            'repeat_customers' => $segmentSummary['repeat_customers'] ?? 0,
            'vip_customers' => $segmentSummary['vip_customers'] ?? 0,
            'dormant_customers' => $segmentSummary['dormant_customers'] ?? 0,
            'coupon_lovers' => $segmentSummary['coupon_lovers'] ?? 0,
            'high_value_customers' => (int) ($this->marketingSegmentEngine->segmentSummary()['HIGH_VALUE_CUSTOMER']['count'] ?? 0),
            'lifetime_revenue' => round($revenue, 2),
            'lifetime_profit' => round($profit, 2),
            'average_order_value' => round($revenue / $orders, 2),
            'average_customer_value' => round($revenue / $customers, 2),
            'top_customers' => $this->topCustomersByRevenue(),
        ];
    }

    protected function geographicIntelligence(): array
    {
        $orders = Order::query()
            ->with('shipment:id,order_id,status,courier_provider_id')
            ->latest()
            ->limit(5000)
            ->get(['id', 'address', 'total', 'status']);

        $rows = $orders
            ->groupBy(fn (Order $order): string => $this->districtFromAddress((string) $order->address))
            ->map(function ($districtOrders, string $district): array {
                $ordersCount = $districtOrders->count();
                $delivered = $districtOrders->where('status', 'delivered')->count();
                $returned = $districtOrders->where('status', 'returned')->count();
                $revenue = (float) $districtOrders->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)->sum('total');
                $shipments = $districtOrders->pluck('shipment')->filter();
                $shipmentCompleted = $shipments->whereIn('status', ['delivered', 'returned', 'cancelled', 'failed'])->count();

                return [
                    'district' => $district,
                    'orders' => $ordersCount,
                    'revenue' => round($revenue, 2),
                    'delivered' => $delivered,
                    'returned_rto' => $returned,
                    'rto_rate' => $this->rate($returned, max(1, $delivered + $returned)),
                    'courier_success_rate' => $this->rate($shipments->where('status', 'delivered')->count(), $shipmentCompleted),
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        return [
            'top_districts' => $rows->take(10)->values()->all(),
            'risky_districts' => $rows->where('orders', '>', 0)->sortByDesc('rto_rate')->take(10)->values()->all(),
            'data_note' => 'Districts are parsed from free-text addresses and Unknown is used when no clear district is available.',
        ];
    }

    protected function timeAnalytics(): array
    {
        $ranges = [
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'last_30_days' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'month_to_date' => [now()->startOfMonth(), now()->endOfDay()],
        ];
        $rangeRows = [];

        foreach ($ranges as $key => [$start, $end]) {
            $orders = $this->revenueQuery($start, $end);
            $deliveredOrders = $this->deliveredOrdersQuery($start, $end);
            $revenue = (float) (clone $orders)->sum('total');
            $deliveredRevenue = (float) (clone $deliveredOrders)->sum('total');
            $profit = (float) (clone $deliveredOrders)->sum('gross_profit');

            $rangeRows[$key] = [
                'orders' => (int) (clone $orders)->count(),
                'revenue' => round($revenue, 2),
                'profit' => round($profit, 2),
                // Margin uses delivered-only revenue as its denominator so
                // it stays paired with the delivered-only profit above it.
                'margin' => $this->rate($profit, $deliveredRevenue),
            ];
        }

        $hourExpression = $this->hourExpression('created_at');
        $hourly = Order::query()
            ->selectRaw($hourExpression.' as hour')
            ->selectRaw('COUNT(*) as orders_count')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn ($row): array => [
                'hour' => (int) $row->hour,
                'label' => Carbon::createFromTime((int) $row->hour)->format('g A'),
                'orders' => (int) $row->orders_count,
            ])
            ->all();

        return [
            'ranges' => $rangeRows,
            'daily_trend' => $this->dailyTrend(now()->subDays(13)->startOfDay(), now()->endOfDay()),
            'hourly_order_trend' => $hourly,
            'best_selling_day' => collect($this->dailyTrend(now()->subDays(29)->startOfDay(), now()->endOfDay()))->sortByDesc('orders')->first(),
            'best_selling_time' => collect($hourly)->sortByDesc('orders')->first(),
        ];
    }

    protected function ruleBasedInsights(array $kpis, array $recovery, array $verification, array $courier, array $marketing, array $products): array
    {
        $insights = [];

        if (($verification['pending'] ?? 0) >= 10) {
            $insights[] = $this->insight('Pending verification is building up', 'Verification queue has '.$verification['pending'].' orders waiting.', 'warning', 'order-verifications.index');
        }

        if (($recovery['callback_queue'] ?? 0) >= 5) {
            $insights[] = $this->insight('Callback queue needs attention', $recovery['callback_queue'].' recovery leads have phone numbers and need follow-up.', 'warning', 'recoveries.index');
        }

        if (($courier['rto_rate'] ?? 0) >= 20) {
            $insights[] = $this->insight('Courier RTO rate is elevated', 'Current courier RTO rate is '.number_format((float) $courier['rto_rate'], 1).'%.', 'danger', 'courier-performance.index');
        }

        if (($recovery['recovery_rate'] ?? 0) < 10 && (($recovery['abandoned_carts'] ?? 0) + ($recovery['incomplete_checkouts'] ?? 0)) > 0) {
            $insights[] = $this->insight('Recovery rate is low', 'Recovery rate is below 10% while open recovery opportunities exist.', 'warning', 'recoveries.index');
        }

        if (($marketing['roas'] ?? null) !== null && (float) $marketing['roas'] < 2) {
            $insights[] = $this->insight('Marketing ROAS below target', 'ROAS is below 2x based on available attribution.', 'warning', 'marketing.index');
        }

        if (count($products['high_revenue_low_profit'] ?? []) > 0) {
            // This is an estimated, pipeline-scoped margin signal (product
            // cost only, not yet delivered-restricted) meant to flag a
            // pricing/discounting issue early — not a claim that realized
            // profit has already been lost.
            $insights[] = $this->insight('High revenue products may have thin margins', 'Review product cost, courier cost, and discounting for low-margin sellers based on estimated order-pipeline margin.', 'warning', 'finance.index');
        }

        if (($kpis['delivery_success_rate'] ?? 0) === 0.0 && Shipment::query()->count() === 0) {
            $insights[] = $this->insight('Courier data not ready yet', 'No shipment history exists yet, so courier funnel rates are unavailable.', 'info', 'courier-performance.index');
        }

        return $insights ?: [
            $this->insight('No major bottleneck detected', 'Current operational signals do not cross alert thresholds.', 'success', 'analytics.index'),
        ];
    }

    protected function topPackages(int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->whereNotNull('order_items.variant_id')
            ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES)
            ->select('order_items.variant_id')
            ->selectRaw('MAX(COALESCE(product_variants.name, order_items.sku)) as package_name')
            ->selectRaw('MAX(order_items.product_name) as product_name')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->groupBy('order_items.variant_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'variant_id' => (int) $row->variant_id,
                'package_name' => (string) $row->package_name,
                'product_name' => (string) $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'orders_count' => (int) $row->orders_count,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    protected function lowStockHighDemand(int $limit = 10): array
    {
        return DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('order_items', 'order_items.variant_id', '=', 'product_variants.id')
            ->leftJoin('orders', function ($join): void {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES)
                    ->where('orders.created_at', '>=', now()->subDays(29)->startOfDay());
            })
            ->where('product_variants.status', 'active')
            ->select('product_variants.id', 'product_variants.name', 'product_variants.stock', 'products.name as product_name')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.id IS NOT NULL THEN order_items.quantity ELSE 0 END), 0) as recent_units')
            ->groupBy('product_variants.id', 'product_variants.name', 'product_variants.stock', 'products.name')
            ->having('recent_units', '>', 0)
            ->orderBy('product_variants.stock')
            ->orderByDesc('recent_units')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'variant_id' => (int) $row->id,
                'product_name' => (string) $row->product_name,
                'package_name' => (string) $row->name,
                'stock' => (int) $row->stock,
                'recent_units' => (int) $row->recent_units,
            ])
            ->all();
    }

    protected function highRevenueLowProfitProducts(int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES)
            ->select('order_items.product_id', 'order_items.product_name')
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->selectRaw('COALESCE(SUM(order_items.quantity * COALESCE(product_variants.cost_price, products.cost_price, 0)), 0) as estimated_cost')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('revenue')
            ->limit($limit * 2)
            ->get()
            ->map(function ($row): array {
                $revenue = (float) $row->revenue;
                $cost = (float) $row->estimated_cost;
                $profit = $revenue - $cost;

                return [
                    'product_id' => $row->product_id,
                    'product_name' => (string) $row->product_name,
                    'revenue' => round($revenue, 2),
                    'estimated_cost' => round($cost, 2),
                    'profit' => round($profit, 2),
                    'margin' => $this->rate($profit, $revenue),
                    'is_estimated' => true,
                ];
            })
            ->filter(fn (array $row): bool => $row['revenue'] > 0 && $row['margin'] < 20)
            ->take($limit)
            ->values()
            ->all();
    }

    protected function productRtoImpact(int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.product_id', 'order_items.product_name')
            ->selectRaw("SUM(CASE WHEN orders.status = 'returned' THEN order_items.quantity ELSE 0 END) as returned_units")
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 'returned' THEN order_items.subtotal ELSE 0 END), 0) as rto_value")
            ->selectRaw('SUM(order_items.quantity) as total_units')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->having('returned_units', '>', 0)
            ->orderByDesc('rto_value')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id,
                'product_name' => (string) $row->product_name,
                'returned_units' => (int) $row->returned_units,
                'total_units' => (int) $row->total_units,
                'rto_value' => round((float) $row->rto_value, 2),
                'rto_rate' => $this->rate((int) $row->returned_units, (int) $row->total_units),
            ])
            ->all();
    }

    protected function courierProviderRows(): array
    {
        return DB::table('shipments')
            ->leftJoin('courier_providers', 'courier_providers.id', '=', 'shipments.courier_provider_id')
            ->selectRaw("COALESCE(courier_providers.name, 'Unassigned') as provider")
            ->selectRaw('COUNT(shipments.id) as total')
            ->selectRaw("SUM(CASE WHEN shipments.status = 'delivered' THEN 1 ELSE 0 END) as delivered")
            ->selectRaw("SUM(CASE WHEN shipments.status IN ('returned', 'failed') THEN 1 ELSE 0 END) as returned")
            ->selectRaw("SUM(CASE WHEN shipments.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw('COALESCE(SUM(shipments.courier_cost), 0) as courier_cost')
            ->groupBy('provider')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row): array {
                $total = (int) $row->total;
                $delivered = (int) $row->delivered;
                $returned = (int) $row->returned;

                return [
                    'provider' => (string) $row->provider,
                    'total' => $total,
                    'delivered' => $delivered,
                    'returned' => $returned,
                    'cancelled' => (int) $row->cancelled,
                    'success_rate' => $this->rate($delivered, $total),
                    'rto_rate' => $this->rate($returned, $total),
                    'courier_cost' => round((float) $row->courier_cost, 2),
                ];
            })
            ->all();
    }

    protected function recoveredRevenue(): float
    {
        return round((float) CheckoutRecovery::query()
            ->with(['product:id,price', 'variant:id,price'])
            ->where('status', 'recovered')
            ->get()
            ->sum(fn (CheckoutRecovery $recovery): float => (float) ($recovery->variant?->price ?? $recovery->product?->price ?? 0) * max(1, (int) $recovery->quantity)), 2);
    }

    protected function automationAssistedRevenue(): float
    {
        $subjectTypes = [
            Order::class,
            (new Order())->getMorphClass(),
            'order',
        ];

        $orderIds = AutomationRun::query()
            ->where('status', 'completed')
            ->whereIn('subject_type', array_unique($subjectTypes))
            ->whereNotNull('subject_id')
            ->distinct()
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        if ($orderIds === []) {
            return 0.0;
        }

        return (float) Order::query()
            ->whereIn('id', $orderIds)
            ->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)
            ->sum('total');
    }

    protected function districtFromAddress(string $address): string
    {
        $address = trim(strip_tags($address));

        if ($address === '') {
            return 'Unknown';
        }

        $parts = collect(preg_split('/[,|\n]+/', $address) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        return $parts->last() ?: 'Unknown';
    }

    protected function hourExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(HOUR FROM {$column})",
            default => "HOUR({$column})",
        };
    }

    protected function insight(string $title, string $body, string $tone, string $route): array
    {
        return compact('title', 'body', 'tone', 'route');
    }

    protected function drilldownLinks(): array
    {
        return [
            ['label' => 'Orders', 'route' => 'orders.index', 'permission' => 'order.view'],
            ['label' => 'Products', 'route' => 'products.index', 'permission' => 'product.view'],
            ['label' => 'Customers', 'route' => 'customers.index', 'permission' => 'customer.view'],
            ['label' => 'Recovery', 'route' => 'recoveries.index', 'permission' => 'recovery.view'],
            ['label' => 'Verification', 'route' => 'order-verifications.index', 'permission' => 'verification.view'],
            ['label' => 'Courier', 'route' => 'courier-performance.index', 'permission' => 'courier.performance.view'],
            ['label' => 'Finance', 'route' => 'finance.index', 'permission' => 'finance.view'],
            ['label' => 'Marketing', 'route' => 'marketing.index', 'permission' => 'marketing.command.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'permission' => 'report.view'],
        ];
    }

    protected function reportLinks(): array
    {
        return [
            ['label' => 'Sales Report', 'report' => 'sales'],
            ['label' => 'Profit Report', 'report' => 'profit'],
            ['label' => 'Customer Report', 'report' => 'customers'],
            ['label' => 'Product Report', 'report' => 'products'],
            ['label' => 'Courier Report', 'report' => 'couriers'],
            ['label' => 'Campaign Report', 'report' => 'campaigns'],
            ['label' => 'Coupon Report', 'report' => 'coupons'],
            ['label' => 'Marketing ROI Report', 'report' => 'marketing_roi'],
        ];
    }

    protected function rate(float|int $part, float|int $total): float
    {
        return $total > 0 ? round(((float) $part / (float) $total) * 100, 2) : 0.0;
    }

    protected function revenueQuery(?Carbon $start = null, ?Carbon $end = null): Builder
    {
        $query = Order::query()->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES);

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }

    /**
     * COD money is only realized on delivery, so every profit figure (as
     * opposed to revenue/pipeline figures, which intentionally stay on
     * revenueQuery()'s broader not-cancelled/returned scope) is computed
     * from this narrower, delivered-only query instead.
     */
    protected function deliveredOrdersQuery(?Carbon $start = null, ?Carbon $end = null): Builder
    {
        $query = Order::query()->where('status', 'delivered');

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }

    protected function revenueFor(?Carbon $start = null, ?Carbon $end = null): float
    {
        return (float) $this->revenueQuery($start, $end)->sum('total');
    }

    protected function profitFor(?Carbon $start = null, ?Carbon $end = null): float
    {
        return (float) $this->deliveredOrdersQuery($start, $end)
            ->selectRaw('COALESCE(SUM(total - product_cost_total - courier_cost_total), 0) as aggregate')
            ->value('aggregate');
    }

    protected function profitBreakdown(?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = $this->deliveredOrdersQuery($start, $end);
        $revenue = (float) (clone $query)->sum('total');
        $productCost = (float) (clone $query)->sum('product_cost_total');
        $courierCost = (float) (clone $query)->sum('courier_cost_total');

        return [
            'revenue' => $revenue,
            'product_cost' => $productCost,
            'courier_cost' => $courierCost,
            'gross_profit' => $revenue - $productCost - $courierCost,
        ];
    }

    protected function dailyTrend(Carbon $start, Carbon $end): array
    {
        $rows = Order::query()
            ->selectRaw("DATE(created_at) as bucket, COUNT(*) as orders_count, COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'returned') THEN total ELSE 0 END), 0) as revenue")
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $trend = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            $trend[] = [
                'date' => $key,
                'orders' => (int) ($row?->orders_count ?? 0),
                'revenue' => (float) ($row?->revenue ?? 0),
            ];
        }

        return $trend;
    }

    protected function topCustomersByRevenue(): array
    {
        return Customer::query()
            ->withCount('orders')
            ->withSum(['orders as revenue_total' => fn (Builder $query) => $query->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)], 'total')
            ->orderByDesc('revenue_total')
            ->limit(10)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'orders' => (int) $customer->orders_count,
                'revenue' => (float) ($customer->revenue_total ?? 0),
            ])
            ->all();
    }

    protected function topCustomersByOrders(): array
    {
        return Customer::query()
            ->withCount('orders')
            ->withSum(['orders as revenue_total' => fn (Builder $query) => $query->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)], 'total')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'orders' => (int) $customer->orders_count,
                'revenue' => (float) ($customer->revenue_total ?? 0),
            ])
            ->all();
    }

    protected function productAggregate(string $orderBy): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES)
            ->select([
                'order_items.product_id',
                DB::raw('MAX(order_items.product_name) as product_name'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as quantity_sold'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders_count'),
            ])
            ->groupBy('order_items.product_id')
            ->orderByDesc($orderBy)
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'orders_count' => (int) $row->orders_count,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    protected function lowPerformanceProducts(): array
    {
        return Product::query()
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function ($join): void {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES);
            })
            ->select([
                'products.id',
                'products.name',
                'products.status',
                DB::raw('COALESCE(SUM(CASE WHEN orders.id IS NOT NULL THEN order_items.quantity ELSE 0 END), 0) as quantity_sold'),
                DB::raw('COALESCE(SUM(CASE WHEN orders.id IS NOT NULL THEN order_items.subtotal ELSE 0 END), 0) as revenue'),
            ])
            ->groupBy('products.id', 'products.name', 'products.status')
            ->orderBy('quantity_sold')
            ->orderBy('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->id,
                'product_name' => $row->name,
                'status' => $row->status,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    protected function activeCouponsQuery(): Builder
    {
        return Coupon::query()
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    protected function couponRevenue(): float
    {
        return (float) CouponUsage::query()
            ->join('orders', 'orders.id', '=', 'coupon_usages.order_id')
            ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES)
            ->sum('orders.total');
    }

    protected function topCoupons(): array
    {
        return Coupon::query()
            ->leftJoin('coupon_usages', 'coupons.id', '=', 'coupon_usages.coupon_id')
            ->leftJoin('orders', function ($join): void {
                $join->on('orders.id', '=', 'coupon_usages.order_id')
                    ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES);
            })
            ->select([
                'coupons.id',
                'coupons.code',
                'coupons.name',
                'coupons.status',
                DB::raw('COUNT(coupon_usages.id) as usage_count'),
                DB::raw('COALESCE(SUM(coupon_usages.discount_amount), 0) as discount_given'),
                DB::raw('COALESCE(SUM(orders.total), 0) as revenue_generated'),
            ])
            ->groupBy('coupons.id', 'coupons.code', 'coupons.name', 'coupons.status')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'status' => $row->status,
                'usage_count' => (int) $row->usage_count,
                'discount_given' => (float) $row->discount_given,
                'revenue_generated' => (float) $row->revenue_generated,
            ])
            ->all();
    }

    protected function marketingSummary(?Carbon $start = null, ?Carbon $end = null): array
    {
        $orders = Order::query()->where('status', 'delivered');
        $spend = AdSpend::query();

        if ($start) {
            $orders->where('created_at', '>=', $start);
            $spend->where('spend_date', '>=', $start->toDateString());
        }

        if ($end) {
            $orders->where('created_at', '<=', $end);
            $spend->where('spend_date', '<=', $end->toDateString());
        }

        $revenue = (float) (clone $orders)->sum('total');
        $productCost = (float) (clone $orders)->sum('product_cost_total');
        $courierCost = (float) (clone $orders)->sum('courier_cost_total');
        $adSpend = (float) $spend->sum('amount');
        $ordersCount = (int) (clone $orders)->count();
        $profit = $revenue - $productCost - $courierCost - $adSpend;

        return [
            'ad_spend' => $adSpend,
            'revenue' => $revenue,
            'profit' => $profit,
            'roas' => $adSpend > 0 ? $revenue / $adSpend : null,
            'cpa' => $adSpend > 0 && $ordersCount > 0 ? $adSpend / $ordersCount : null,
            'orders' => $ordersCount,
        ];
    }
}
