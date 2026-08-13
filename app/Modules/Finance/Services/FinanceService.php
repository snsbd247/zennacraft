<?php

namespace App\Modules\Finance\Services;

use App\Modules\Courier\Models\Shipment;
use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Services\ExpenseService;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Services\MarketingAnalyticsService;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(
        private CacheService $cacheService,
        private ExpenseService $expenseService,
        private MarketingAnalyticsService $marketingAnalyticsService,
    ) {}

    public function commandCenter(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::FINANCE_COMMAND_CENTER,
            fn (): array => $this->buildCommandCenter(),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::FINANCE_TAG]
        );
    }

    public function dailySummary(): array
    {
        return $this->summary(
            Order::query()->whereDate('created_at', now()->toDateString())
        );
    }

    public function monthlySummary(): array
    {
        return $this->summary(
            Order::query()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
        );
    }

    public function overallSummary(): array
    {
        return $this->summary(Order::query());
    }

    public function orderProfit(Order $order): array
    {
        $margin = (float) $order->total > 0
            ? (((float) $order->gross_profit / (float) $order->total) * 100)
            : 0.0;

        return [
            'revenue' => (float) $order->total,
            'product_revenue' => (float) $order->subtotal,
            'product_cost' => (float) $order->product_cost_total,
            'courier_cost' => (float) $order->courier_cost_total,
            'coupon_discount' => (float) $order->coupon_discount_amount,
            'gross_profit' => (float) $order->gross_profit,
            'margin' => round($margin, 2),
            'collection_status' => $this->collectionStatusForOrder($order),
        ];
    }

    public function syncCourierCostForOrder(Order $order): Order
    {
        $courierCost = (float) $order->shipment()->sum('courier_cost');

        $order->forceFill([
            'courier_cost_total' => $courierCost,
            'gross_profit' => (float) $order->total - (float) $order->product_cost_total - $courierCost,
        ])->save();

        return $order->refresh();
    }

    protected function summary($query): array
    {
        $row = (clone $query)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as delivered_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN total ELSE 0 END), 0) as pending_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END), 0) as cancelled_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN total ELSE 0 END), 0) as returned_revenue")
            ->selectRaw('COALESCE(SUM(product_cost_total), 0) as product_cost')
            ->selectRaw('COALESCE(SUM(courier_cost_total), 0) as courier_cost')
            // Delivered orders only: this is a COD store, so an order that
            // is cancelled/returned/pending never generated real revenue,
            // and gross_profit sits right next to profitEngine()'s
            // net_profit in commandCenter() — both must describe the same
            // set of orders, or the gap between them stops meaning "costs"
            // and silently starts meaning "a different set of orders" too.
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN gross_profit ELSE 0 END), 0) as gross_profit")
            ->first();

        return [
            'total_orders' => (int) ($row?->total_orders ?? 0),
            'total_revenue' => (float) ($row?->total_revenue ?? 0),
            'delivered_revenue' => (float) ($row?->delivered_revenue ?? 0),
            'pending_revenue' => (float) ($row?->pending_revenue ?? 0),
            'cancelled_revenue' => (float) ($row?->cancelled_revenue ?? 0),
            'returned_revenue' => (float) ($row?->returned_revenue ?? 0),
            'product_cost' => (float) ($row?->product_cost ?? 0),
            'courier_cost' => (float) ($row?->courier_cost ?? 0),
            'gross_profit' => (float) ($row?->gross_profit ?? 0),
        ];
    }

    protected function buildCommandCenter(): array
    {
        $summaries = [
            'today' => $this->dailySummary(),
            'month' => $this->monthlySummary(),
            'overall' => $this->overallSummary(),
        ];

        $marketing = $this->marketingAnalyticsService->overallProfit();
        $cashflow = $this->codCashflow();
        $profitEngine = $this->profitEngine($marketing);

        return [
            'summaries' => $summaries,
            'dashboard' => $this->dashboard($summaries['overall'], $marketing, $cashflow, $profitEngine),
            'cashflow' => $cashflow,
            'profit_engine' => $profitEngine,
            'recent_orders' => $this->recentFinancialOrders(),
            'expense_overview' => $this->expenseOverview(),
            'product_economics' => $this->productEconomics(),
            'courier_finance' => $this->courierFinance(),
            'campaign_economics' => $this->campaignEconomics(),
            'marketing_roi' => $this->marketingRoi($marketing),
        ];
    }

    protected function dashboard(array $overall, array $marketing, array $cashflow, array $profitEngine): array
    {
        $grossRevenue = (float) $overall['total_revenue'];
        $netRevenue = $grossRevenue - (float) $overall['cancelled_revenue'] - (float) $overall['returned_revenue'];
        $deliveredRevenue = (float) $overall['delivered_revenue'];
        $netProfit = (float) $profitEngine['net_profit'];

        return [
            'gross_revenue' => $grossRevenue,
            'net_revenue' => $netRevenue,
            'delivered_revenue' => $deliveredRevenue,
            'pending_revenue' => (float) $overall['pending_revenue'],
            'cod_collectable' => (float) $cashflow['cod_collectable'],
            'courier_holding_amount' => (float) $cashflow['courier_holding_amount'],
            'total_expenses' => (float) $profitEngine['operational_expense'],
            'net_profit' => $netProfit,
            'profit_margin' => $this->rate($netProfit, $deliveredRevenue),
            'ad_spend' => (float) $marketing['ad_spend'],
            'roi' => $marketing['roi'] === null ? null : round((float) $marketing['roi'], 2),
        ];
    }

    protected function codCashflow(): array
    {
        $orderRow = Order::query()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as delivered_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'returned' THEN total ELSE 0 END), 0) as returned_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END), 0) as cancelled_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'returned') THEN total ELSE 0 END), 0) as collectable_total")
            ->first();
        $shipmentRow = Shipment::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN cod_amount ELSE 0 END), 0) as delivered_cod")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('assigned', 'shipped') THEN cod_amount ELSE 0 END), 0) as pending_cod")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('returned', 'failed') THEN courier_cost ELSE 0 END), 0) as returned_courier_cost")
            ->first();
        $totalCodOrders = (int) ($orderRow?->total_orders ?? 0);
        $deliveredOrderAmount = (float) ($orderRow?->delivered_total ?? 0);
        $deliveredShipmentCod = (float) ($shipmentRow?->delivered_cod ?? 0);
        $deliveredCodAmount = $deliveredShipmentCod > 0 ? $deliveredShipmentCod : $deliveredOrderAmount;
        $courierPendingCollection = (float) ($shipmentRow?->pending_cod ?? 0);
        $unsettledAmount = $deliveredCodAmount + $courierPendingCollection;
        $returnedRevenue = (float) ($orderRow?->returned_total ?? 0);
        $returnedCourierCost = (float) ($shipmentRow?->returned_courier_cost ?? 0);

        return [
            'total_cod_orders' => $totalCodOrders,
            'delivered_cod_amount' => $deliveredCodAmount,
            'courier_pending_collection' => $courierPendingCollection,
            'settled_amount' => null,
            'unsettled_amount' => $unsettledAmount,
            'returned_rto_loss' => $returnedRevenue + $returnedCourierCost,
            'cancelled_revenue' => (float) ($orderRow?->cancelled_total ?? 0),
            'cod_collectable' => (float) ($orderRow?->collectable_total ?? 0),
            'courier_holding_amount' => $unsettledAmount,
            'cash_collection_status' => 'Estimated from delivered and in-transit COD shipment amounts. Courier settlement records are not tracked yet.',
            'is_estimated' => true,
        ];
    }

    protected function profitEngine(array $marketing): array
    {
        $orders = Order::query()->where('status', 'delivered');
        $revenue = (float) (clone $orders)->sum('total');
        $productCost = (float) (clone $orders)->sum('product_cost_total');
        $courierCost = (float) (clone $orders)->sum('courier_cost_total');
        $marketingCost = (float) $marketing['ad_spend'];
        $operationalExpense = $this->expenseService->totalExpense();
        $netProfit = $revenue - $productCost - $courierCost - $marketingCost - $operationalExpense;

        return [
            'revenue' => $revenue,
            'product_cost' => $productCost,
            'courier_cost' => $courierCost,
            'marketing_cost' => $marketingCost,
            'operational_expense' => $operationalExpense,
            'net_profit' => $netProfit,
            'margin' => $this->rate($netProfit, $revenue),
        ];
    }

    protected function recentFinancialOrders(int $limit = 12): array
    {
        return Order::query()
            ->with(['customer:id,name,phone', 'shipment.courierProvider:id,name'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Order $order): array {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer?->name ?: $order->customer_name,
                    'status' => $order->status,
                    'revenue' => (float) $order->total,
                    'product_cost' => (float) $order->product_cost_total,
                    'courier_cost' => (float) $order->courier_cost_total,
                    'discount' => (float) $order->coupon_discount_amount,
                    'profit' => (float) $order->gross_profit,
                    'margin' => $this->rate((float) $order->gross_profit, (float) $order->total),
                    'collection_status' => $this->collectionStatusForOrder($order),
                    'courier' => $order->shipment?->courierProvider?->name,
                    'created_at' => $order->created_at?->format('M j, Y g:i A'),
                ];
            })
            ->all();
    }

    protected function expenseOverview(): array
    {
        $today = now()->toDateString();
        $sevenDaysAgo = now()->subDays(6)->toDateString();
        $thirtyDaysAgo = now()->subDays(29)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        return [
            'today' => $this->expenseService->dailyExpense($today),
            'seven_days' => (float) Expense::query()->whereDate('expense_date', '>=', $sevenDaysAgo)->sum('amount'),
            'thirty_days' => (float) Expense::query()->whereDate('expense_date', '>=', $thirtyDaysAgo)->sum('amount'),
            'month_to_date' => (float) Expense::query()->whereDate('expense_date', '>=', $monthStart)->sum('amount'),
            'categories' => ExpenseCategory::query()
                ->leftJoin('expenses', function ($join) use ($monthStart): void {
                    $join->on('expense_categories.id', '=', 'expenses.expense_category_id')
                        ->whereDate('expenses.expense_date', '>=', $monthStart);
                })
                ->select('expense_categories.name')
                ->selectRaw('COALESCE(SUM(expenses.amount), 0) as amount')
                ->groupBy('expense_categories.id', 'expense_categories.name')
                ->orderBy('expense_categories.name')
                ->get()
                ->map(fn ($row): array => [
                    'category' => (string) $row->name,
                    'amount' => round((float) $row->amount, 2),
                ])
                ->all(),
        ];
    }

    protected function productEconomics(int $limit = 12): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->select('order_items.product_id', 'order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw("SUM(CASE WHEN orders.status NOT IN ('cancelled', 'returned') THEN order_items.subtotal ELSE 0 END) as product_revenue")
            ->selectRaw("SUM(CASE WHEN orders.status NOT IN ('cancelled', 'returned') THEN order_items.quantity * COALESCE(product_variants.cost_price, products.cost_price, 0) ELSE 0 END) as estimated_product_cost")
            ->selectRaw("SUM(CASE WHEN orders.status = 'returned' THEN order_items.subtotal ELSE 0 END) as return_rto_value")
            ->selectRaw("SUM(CASE WHEN orders.status = 'cancelled' THEN order_items.subtotal ELSE 0 END) as cancelled_value")
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('product_revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $revenue = (float) $row->product_revenue;
                $cost = (float) $row->estimated_product_cost;
                $profit = $revenue - $cost;

                return [
                    'product_id' => $row->product_id,
                    'product_name' => (string) $row->product_name,
                    'units_sold' => (int) $row->units_sold,
                    'product_revenue' => round($revenue, 2),
                    'product_cost' => round($cost, 2),
                    'gross_profit' => round($profit, 2),
                    'margin' => $this->rate($profit, $revenue),
                    'return_rto_value' => round((float) $row->return_rto_value, 2),
                    'cancelled_value' => round((float) $row->cancelled_value, 2),
                    'is_cost_estimated' => true,
                ];
            })
            ->all();
    }

    protected function courierFinance(): array
    {
        $totalShipments = Shipment::query()->count();
        $courierCost = (float) Shipment::query()->sum('courier_cost');
        $rtoCost = (float) Shipment::query()->whereIn('status', ['returned', 'failed'])->sum('courier_cost');

        return [
            'summary' => [
                'courier_cost' => $courierCost,
                'courier_cost_per_order' => $totalShipments > 0 ? round($courierCost / $totalShipments, 2) : 0.0,
                'rto_cost' => $rtoCost,
                'courier_pending_cod' => (float) Shipment::query()->whereIn('status', ['assigned', 'shipped'])->sum('cod_amount'),
                'delivered_cod' => (float) Shipment::query()->where('status', 'delivered')->sum('cod_amount'),
                'settlement_status' => 'Courier settlement records are not tracked yet; COD values are estimated from shipments.',
            ],
            'rows' => DB::table('shipments')
                ->leftJoin('courier_providers', 'courier_providers.id', '=', 'shipments.courier_provider_id')
                ->selectRaw("COALESCE(courier_providers.name, 'Unassigned') as courier")
                ->selectRaw('COUNT(shipments.id) as shipment_count')
                ->selectRaw("SUM(CASE WHEN shipments.status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
                ->selectRaw("SUM(CASE WHEN shipments.status IN ('returned', 'failed') THEN 1 ELSE 0 END) as rto_count")
                ->selectRaw('COALESCE(SUM(shipments.courier_cost), 0) as courier_cost')
                ->selectRaw('COALESCE(SUM(shipments.cod_amount), 0) as cod_amount')
                ->groupBy('courier')
                ->orderByDesc('shipment_count')
                ->limit(10)
                ->get()
                ->map(fn ($row): array => [
                    'courier' => (string) $row->courier,
                    'shipment_count' => (int) $row->shipment_count,
                    'delivered_count' => (int) $row->delivered_count,
                    'rto_count' => (int) $row->rto_count,
                    'courier_cost' => round((float) $row->courier_cost, 2),
                    'cod_amount' => round((float) $row->cod_amount, 2),
                    'cost_per_shipment' => (int) $row->shipment_count > 0 ? round((float) $row->courier_cost / (int) $row->shipment_count, 2) : 0.0,
                ])
                ->all(),
        ];
    }

    protected function campaignEconomics(): array
    {
        return MarketingCampaign::query()
            ->orderByDesc('total_revenue')
            ->orderByDesc('total_converted')
            ->limit(8)
            ->get()
            ->map(fn (MarketingCampaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'type' => $campaign->campaign_type,
                'revenue' => (float) $campaign->total_revenue,
                'conversions' => (int) $campaign->total_converted,
                'queued' => (int) $campaign->total_queued,
                'sent' => (int) $campaign->total_sent,
                'roi_note' => 'Campaign spend is not attributed per campaign yet.',
            ])
            ->all();
    }

    protected function marketingRoi(array $marketing): array
    {
        $campaignRevenue = (float) MarketingCampaign::query()->sum('total_revenue');
        $campaignConversions = (int) MarketingCampaign::query()->sum('total_converted');
        $adSpend = (float) AdSpend::query()->sum('amount');

        return [
            'ad_spend' => $adSpend,
            'revenue_attributed' => $campaignRevenue,
            'orders_attributed' => $campaignConversions,
            'cpa' => $campaignConversions > 0 ? round($adSpend / $campaignConversions, 2) : null,
            'roas' => $adSpend > 0 ? round($campaignRevenue / $adSpend, 2) : null,
            'profit_after_ads' => (float) $marketing['net_business_profit'],
            'is_estimated' => $campaignRevenue <= 0,
            'fallback_revenue' => (float) $marketing['revenue'],
        ];
    }

    protected function collectionStatusForOrder(Order $order): string
    {
        if (in_array($order->status, ['cancelled', 'returned'], true)) {
            return ucfirst($order->status);
        }

        if ($order->status === 'delivered') {
            return 'Estimated courier holding';
        }

        if ($order->shipment?->status && in_array($order->shipment->status, ['assigned', 'shipped'], true)) {
            return 'Courier pending';
        }

        return 'COD pending';
    }

    protected function rate(float $part, float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0.0;
    }
}
