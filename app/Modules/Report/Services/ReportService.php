<?php

namespace App\Modules\Report\Services;

use App\Modules\Courier\Models\CourierMetric;
use App\Modules\Customer\Models\Customer;
use App\Modules\Expense\Models\Expense;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportService
{
    public const REPORTS = [
        'sales' => [
            'title' => 'Sales Report',
            'description' => 'Order volume, gross sales, net revenue, and status movement by date.',
            'tone' => 'studio-badge--info',
        ],
        'profit' => [
            'title' => 'Profit Report',
            'description' => 'Revenue, product cost, courier cost, gross profit, and margin.',
            'tone' => 'studio-badge--success',
        ],
        'products' => [
            'title' => 'Product Report',
            'description' => 'Best-selling products by quantity, order count, and revenue.',
            'tone' => 'studio-badge--neutral',
        ],
        'customers' => [
            'title' => 'Customer Report',
            'description' => 'Customer order value and repeat purchase behavior without exposing contact details.',
            'tone' => 'studio-badge--neutral',
        ],
        'campaigns' => [
            'title' => 'Campaign Report',
            'description' => 'Marketing campaign queue, conversion, and revenue performance.',
            'tone' => 'studio-badge--info',
        ],
        'fraud' => [
            'title' => 'Fraud Report',
            'description' => 'Fraud events by type, severity, score, and review priority.',
            'tone' => 'studio-badge--danger',
        ],
        'coupons' => [
            'title' => 'Coupon Report',
            'description' => 'Coupon usage, discount, revenue, and delivered revenue.',
            'tone' => 'studio-badge--warning',
        ],
        'couriers' => [
            'title' => 'Courier Report',
            'description' => 'Courier metric snapshot covering delivery, return, and failure performance.',
            'tone' => 'studio-badge--info',
        ],
        'expenses' => [
            'title' => 'Expense Report',
            'description' => 'Business expenses by category for the selected period.',
            'tone' => 'studio-badge--warning',
        ],
        'marketing_roi' => [
            'title' => 'Marketing ROI Report',
            'description' => 'Delivered revenue, ad spend, business expense, profit, and ROI by date.',
            'tone' => 'studio-badge--success',
        ],
    ];

    public const REVENUE_EXCLUDED_STATUSES = ['cancelled', 'returned'];

    public function __construct(private CacheService $cacheService) {}

    public function dashboard(array $filters = []): array
    {
        $range = $this->normalizeFilters($filters);
        $selectedReport = $this->normalizeReportKey((string) ($filters['report'] ?? 'sales'));
        $cards = [];

        foreach ($this->definitions() as $key => $definition) {
            $report = $this->report($key, $range);
            $primary = $report['summary'][0] ?? [
                'label' => 'Rows',
                'value' => count($report['rows']),
                'meta' => 'Cached report',
                'tone' => 'studio-badge--neutral',
            ];

            $cards[] = [
                'key' => $key,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'tone' => $definition['tone'],
                'primary_label' => $primary['label'],
                'primary_value' => $primary['value'],
                'primary_meta' => $primary['meta'],
                'active' => $selectedReport === $key,
            ];
        }

        return [
            'definitions' => $this->definitions(),
            'filters' => $range,
            'selected_report' => $selectedReport,
            'cards' => $cards,
            'report' => $this->report($selectedReport, $range),
        ];
    }

    public function report(string $report, array $filters = []): array
    {
        $range = $this->normalizeFilters($filters);
        $report = $this->normalizeReportKey($report);
        $version = $this->cacheService->reportCacheVersion();

        return $this->cacheService->remember(
            CacheKeyRegistry::reportDashboard($report, $range['date_from'], $range['date_to'], $version),
            fn (): array => $this->buildReport($report, $range),
            300,
            [CacheKeyRegistry::REPORTS_TAG]
        );
    }

    public function definitions(): array
    {
        return self::REPORTS;
    }

    public function reportKeys(): array
    {
        return array_keys(self::REPORTS);
    }

    public function normalizeFilters(array $filters = []): array
    {
        $from = filled($filters['date_from'] ?? null)
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = filled($filters['date_to'] ?? null)
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($to->lt($from)) {
            $to = $from->copy()->endOfDay();
        }

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'from' => $from,
            'to' => $to,
        ];
    }

    protected function buildReport(string $report, array $range): array
    {
        return match ($report) {
            'sales' => $this->salesReport($range),
            'profit' => $this->profitReport($range),
            'products' => $this->productReport($range),
            'customers' => $this->customerReport($range),
            'campaigns' => $this->campaignReport($range),
            'fraud' => $this->fraudReport($range),
            'coupons' => $this->couponReport($range),
            'couriers' => $this->courierReport($range),
            'expenses' => $this->expenseReport($range),
            'marketing_roi' => $this->marketingRoiReport($range),
            default => $this->salesReport($range),
        };
    }

    protected function salesReport(array $range): array
    {
        $orders = $this->ordersInRange($range);
        $netOrders = $this->revenueOrdersInRange($range);
        $totalOrders = (int) (clone $orders)->count();
        $netRevenue = (float) (clone $netOrders)->sum('total');

        $rows = Order::query()
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->selectRaw(
                "DATE(created_at) as report_date,
                COUNT(*) as total_orders,
                SUM(total) as gross_sales,
                SUM(CASE WHEN status NOT IN (?, ?) THEN total ELSE 0 END) as net_revenue,
                SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as delivered_revenue,
                SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END) as cancelled_revenue,
                SUM(CASE WHEN status = 'returned' THEN total ELSE 0 END) as returned_revenue",
                self::REVENUE_EXCLUDED_STATUSES
            )
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->limit(60)
            ->get()
            ->map(fn ($row): array => [
                'report_date' => (string) $row->report_date,
                'total_orders' => (int) $row->total_orders,
                'gross_sales' => round((float) $row->gross_sales, 2),
                'net_revenue' => round((float) $row->net_revenue, 2),
                'delivered_revenue' => round((float) $row->delivered_revenue, 2),
                'cancelled_revenue' => round((float) $row->cancelled_revenue, 2),
                'returned_revenue' => round((float) $row->returned_revenue, 2),
            ])
            ->all();

        return $this->payload('sales', $range, [
            $this->card('Orders', $this->number($totalOrders), 'Orders in selected date range', 'studio-badge--neutral'),
            $this->card('Net Revenue', $this->money($netRevenue), 'Excludes cancelled and returned orders', 'studio-badge--info'),
            $this->card('Delivered Revenue', $this->money((float) (clone $orders)->where('status', 'delivered')->sum('total')), 'Delivered orders only', 'studio-badge--success'),
            $this->card('Average Order', $this->money($totalOrders > 0 ? $netRevenue / $totalOrders : 0), 'Net revenue divided by all orders', 'studio-badge--neutral'),
        ], [
            $this->column('report_date', 'Date'),
            $this->column('total_orders', 'Orders', 'number'),
            $this->column('gross_sales', 'Gross Sales', 'money'),
            $this->column('net_revenue', 'Net Revenue', 'money'),
            $this->column('delivered_revenue', 'Delivered', 'money'),
            $this->column('cancelled_revenue', 'Cancelled', 'money'),
            $this->column('returned_revenue', 'Returned', 'money'),
        ], $rows);
    }

    protected function profitReport(array $range): array
    {
        // Revenue/Product Cost/Courier Cost stay "pipeline" figures (all
        // non-cancelled/returned orders, as their own card labels already
        // say) — that scope isn't this fix's target. Gross Profit is a
        // realised-money claim in a COD business: an order only becomes
        // real revenue on delivery, so it's computed from delivered orders
        // only, consistent with net_profit elsewhere in Finance.
        $orders = $this->revenueOrdersInRange($range);
        $revenue = (float) (clone $orders)->sum('total');
        $productCost = (float) (clone $orders)->sum('product_cost_total');
        $courierCost = (float) (clone $orders)->sum('courier_cost_total');
        $grossProfit = (float) $this->ordersInRange($range)->where('status', 'delivered')->sum('gross_profit');

        $rows = Order::query()
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES)
            ->selectRaw(
                "DATE(created_at) as report_date,
                SUM(total) as revenue,
                SUM(product_cost_total) as product_cost,
                SUM(courier_cost_total) as courier_cost,
                SUM(CASE WHEN status = 'delivered' THEN gross_profit ELSE 0 END) as gross_profit,
                SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as delivered_revenue"
            )
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->limit(60)
            ->get()
            ->map(fn ($row): array => [
                'report_date' => (string) $row->report_date,
                'revenue' => round((float) $row->revenue, 2),
                'product_cost' => round((float) $row->product_cost, 2),
                'courier_cost' => round((float) $row->courier_cost, 2),
                'gross_profit' => round((float) $row->gross_profit, 2),
                // Margin is gross profit over the SAME delivered-only
                // revenue it was computed from, not the row's broader
                // pipeline revenue — otherwise a day with a lot of
                // still-pending revenue would show an artificially
                // depressed margin against profit that hasn't happened yet.
                'margin' => $this->rate((float) $row->gross_profit, (float) $row->delivered_revenue),
            ])
            ->all();

        return $this->payload('profit', $range, [
            $this->card('Revenue', $this->money($revenue), 'Cancelled and returned orders excluded', 'studio-badge--info'),
            $this->card('Product Cost', $this->money($productCost), 'Stored order snapshots', 'studio-badge--warning'),
            $this->card('Courier Cost', $this->money($courierCost), 'Stored courier cost snapshots', 'studio-badge--warning'),
            $this->card('Gross Profit', $this->money($grossProfit), 'Delivered orders only — matches Net Profit\'s order scope', 'studio-badge--success'),
        ], [
            $this->column('report_date', 'Date'),
            $this->column('revenue', 'Revenue', 'money'),
            $this->column('product_cost', 'Product Cost', 'money'),
            $this->column('courier_cost', 'Courier Cost', 'money'),
            $this->column('gross_profit', 'Gross Profit', 'money'),
            $this->column('margin', 'Margin %', 'percent'),
        ], $rows);
    }

    protected function productReport(array $range): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$range['from'], $range['to']])
            ->whereNotIn('orders.status', self::REVENUE_EXCLUDED_STATUSES)
            ->selectRaw(
                'order_items.product_id,
                MAX(order_items.product_name) as product_name,
                MAX(order_items.sku) as sku,
                SUM(order_items.quantity) as quantity_sold,
                COUNT(DISTINCT order_items.order_id) as order_count,
                SUM(order_items.subtotal) as revenue,
                AVG(order_items.price) as average_price'
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id ? (int) $row->product_id : null,
                'product_name' => (string) ($row->product_name ?: 'Unknown product'),
                'sku' => (string) ($row->sku ?: '-'),
                'quantity_sold' => (int) $row->quantity_sold,
                'order_count' => (int) $row->order_count,
                'revenue' => round((float) $row->revenue, 2),
                'average_price' => round((float) $row->average_price, 2),
            ])
            ->all();

        return $this->payload('products', $range, [
            $this->card('Products Sold', $this->number(collect($rows)->sum('quantity_sold')), 'Total item quantities', 'studio-badge--neutral'),
            $this->card('Product Revenue', $this->money(collect($rows)->sum('revenue')), 'Top product rows shown', 'studio-badge--info'),
            $this->card('Products Ordered', $this->number(count($rows)), 'Distinct product rows', 'studio-badge--neutral'),
            $this->card('Best Seller', (string) (collect($rows)->first()['product_name'] ?? 'None yet'), 'Highest revenue product', 'studio-badge--success'),
        ], [
            $this->column('product_name', 'Product'),
            $this->column('sku', 'SKU'),
            $this->column('quantity_sold', 'Qty Sold', 'number'),
            $this->column('order_count', 'Orders', 'number'),
            $this->column('revenue', 'Revenue', 'money'),
            $this->column('average_price', 'Avg Price', 'money'),
        ], $rows);
    }

    protected function customerReport(array $range): array
    {
        $rows = Order::query()
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereBetween('orders.created_at', [$range['from'], $range['to']])
            ->selectRaw(
                "customers.id as customer_id,
                MAX(COALESCE(customers.name, orders.customer_name, 'Customer')) as customer_name,
                COUNT(orders.id) as total_orders,
                SUM(CASE WHEN orders.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN orders.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN orders.status = 'returned' THEN 1 ELSE 0 END) as returned_orders,
                SUM(CASE WHEN orders.status NOT IN (?, ?) THEN orders.total ELSE 0 END) as revenue,
                MAX(orders.created_at) as last_order_at",
                self::REVENUE_EXCLUDED_STATUSES
            )
            ->groupBy('customers.id')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => (string) $row->customer_name,
                'total_orders' => (int) $row->total_orders,
                'delivered_orders' => (int) $row->delivered_orders,
                'cancelled_orders' => (int) $row->cancelled_orders,
                'returned_orders' => (int) $row->returned_orders,
                'revenue' => round((float) $row->revenue, 2),
                'last_order_at' => $row->last_order_at ? Carbon::parse($row->last_order_at)->toDateTimeString() : null,
            ])
            ->all();

        $activeCustomers = collect($rows)->count();

        return $this->payload('customers', $range, [
            $this->card('Active Customers', $this->number($activeCustomers), 'Customers with orders in range', 'studio-badge--neutral'),
            $this->card('New Customers', $this->number(Customer::query()->whereBetween('created_at', [$range['from'], $range['to']])->count()), 'Customer records created in range', 'studio-badge--success'),
            $this->card('Customer Revenue', $this->money(collect($rows)->sum('revenue')), 'Phone/email/address not exposed', 'studio-badge--info'),
            $this->card('Repeat Customers', $this->number(collect($rows)->where('total_orders', '>', 1)->count()), '2+ orders in selected range', 'studio-badge--success'),
        ], [
            $this->column('customer_id', 'Customer ID', 'number'),
            $this->column('customer_name', 'Customer'),
            $this->column('total_orders', 'Orders', 'number'),
            $this->column('delivered_orders', 'Delivered', 'number'),
            $this->column('cancelled_orders', 'Cancelled', 'number'),
            $this->column('returned_orders', 'Returned', 'number'),
            $this->column('revenue', 'Revenue', 'money'),
            $this->column('last_order_at', 'Last Order'),
        ], $rows);
    }

    protected function campaignReport(array $range): array
    {
        $campaigns = MarketingCampaign::query()
            ->whereBetween('created_at', [$range['from'], $range['to']]);

        $rows = (clone $campaigns)
            ->with('segment')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (MarketingCampaign $campaign): array => [
                'name' => $campaign->name,
                'campaign_type' => $campaign->campaign_type,
                'status' => $campaign->status,
                'audience_type' => $campaign->audience_type,
                'segment' => $campaign->segment?->name ?: '-',
                'recipients' => (int) $campaign->total_recipients,
                'queued' => (int) $campaign->total_queued,
                'sent' => (int) $campaign->total_sent,
                'conversions' => (int) $campaign->total_converted,
                'revenue' => round((float) $campaign->total_revenue, 2),
                'starts_at' => $campaign->starts_at?->toDateTimeString(),
            ])
            ->all();

        return $this->payload('campaigns', $range, [
            $this->card('Campaigns', $this->number((clone $campaigns)->count()), 'Created in range', 'studio-badge--neutral'),
            $this->card('Running', $this->number((clone $campaigns)->where('status', 'running')->count()), 'Currently running campaigns', 'studio-badge--success'),
            $this->card('Conversions', $this->number((int) (clone $campaigns)->sum('total_converted')), 'Attributed orders', 'studio-badge--success'),
            $this->card('Revenue', $this->money((float) (clone $campaigns)->sum('total_revenue')), 'Campaign-attributed revenue', 'studio-badge--info'),
        ], [
            $this->column('name', 'Campaign'),
            $this->column('campaign_type', 'Type'),
            $this->column('status', 'Status', 'badge'),
            $this->column('audience_type', 'Audience'),
            $this->column('segment', 'Segment'),
            $this->column('queued', 'Queued', 'number'),
            $this->column('conversions', 'Conversions', 'number'),
            $this->column('revenue', 'Revenue', 'money'),
            $this->column('starts_at', 'Starts'),
        ], $rows);
    }

    protected function fraudReport(array $range): array
    {
        $events = FraudEvent::query()->whereBetween('created_at', [$range['from'], $range['to']]);
        $rows = (clone $events)
            ->selectRaw('type, severity, COUNT(*) as event_count, AVG(score) as average_score, MAX(created_at) as latest_at')
            ->groupBy('type', 'severity')
            ->orderByDesc('event_count')
            ->orderByDesc('average_score')
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'type' => (string) $row->type,
                'severity' => (string) $row->severity,
                'event_count' => (int) $row->event_count,
                'average_score' => round((float) $row->average_score, 2),
                'latest_at' => $row->latest_at ? Carbon::parse($row->latest_at)->toDateTimeString() : null,
            ])
            ->all();

        return $this->payload('fraud', $range, [
            $this->card('Fraud Events', $this->number((clone $events)->count()), 'Events in selected range', 'studio-badge--danger'),
            $this->card('High Severity', $this->number((clone $events)->where('severity', 'high')->count()), 'High severity events', 'studio-badge--warning'),
            $this->card('Critical', $this->number((clone $events)->where('severity', 'critical')->count()), 'Critical severity events', 'studio-badge--danger'),
            $this->card('Duplicate Signals', $this->number((clone $events)->whereIn('type', ['duplicate_order', 'duplicate_order_blocked'])->count()), 'Duplicate-order related events', 'studio-badge--info'),
        ], [
            $this->column('type', 'Type'),
            $this->column('severity', 'Severity', 'badge'),
            $this->column('event_count', 'Events', 'number'),
            $this->column('average_score', 'Avg Score', 'number'),
            $this->column('latest_at', 'Latest'),
        ], $rows);
    }

    protected function couponReport(array $range): array
    {
        $usages = CouponUsage::query()->whereBetween('used_at', [$range['from'], $range['to']]);
        $rows = (clone $usages)
            ->leftJoin('orders', 'orders.id', '=', 'coupon_usages.order_id')
            ->selectRaw(
                "coupon_usages.code,
                COUNT(coupon_usages.id) as usage_count,
                SUM(coupon_usages.discount_amount) as discount_given,
                SUM(CASE WHEN orders.id IS NOT NULL THEN orders.total ELSE 0 END) as revenue,
                SUM(CASE WHEN orders.status = 'delivered' THEN orders.total ELSE 0 END) as delivered_revenue,
                MAX(coupon_usages.used_at) as latest_used_at"
            )
            ->groupBy('coupon_usages.code')
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'code' => (string) $row->code,
                'usage_count' => (int) $row->usage_count,
                'discount_given' => round((float) $row->discount_given, 2),
                'revenue' => round((float) $row->revenue, 2),
                'delivered_revenue' => round((float) $row->delivered_revenue, 2),
                'latest_used_at' => $row->latest_used_at ? Carbon::parse($row->latest_used_at)->toDateTimeString() : null,
            ])
            ->all();

        return $this->payload('coupons', $range, [
            $this->card('Coupon Uses', $this->number((clone $usages)->count()), 'Usage records in range', 'studio-badge--neutral'),
            $this->card('Discount Given', $this->money((float) (clone $usages)->sum('discount_amount')), 'Total customer discount', 'studio-badge--warning'),
            $this->card('Coupon Revenue', $this->money(collect($rows)->sum('revenue')), 'Orders linked to coupon usage', 'studio-badge--info'),
            $this->card('Active Coupons', $this->number(Coupon::query()->where('status', 'active')->count()), 'Active coupon definitions', 'studio-badge--success'),
        ], [
            $this->column('code', 'Code'),
            $this->column('usage_count', 'Uses', 'number'),
            $this->column('discount_given', 'Discount', 'money'),
            $this->column('revenue', 'Revenue', 'money'),
            $this->column('delivered_revenue', 'Delivered Revenue', 'money'),
            $this->column('latest_used_at', 'Latest Use'),
        ], $rows);
    }

    protected function courierReport(array $range): array
    {
        $metrics = CourierMetric::query()->with('courierProvider');
        $rows = (clone $metrics)
            ->orderByDesc('success_rate')
            ->orderBy('return_rate')
            ->get()
            ->map(fn (CourierMetric $metric): array => [
                'provider' => $metric->courierProvider?->name ?: 'Unassigned',
                'total_shipments' => (int) $metric->total_shipments,
                'delivered_shipments' => (int) $metric->delivered_shipments,
                'returned_shipments' => (int) $metric->returned_shipments,
                'failed_shipments' => (int) $metric->failed_shipments,
                'success_rate' => round((float) $metric->success_rate, 2),
                'return_rate' => round((float) $metric->return_rate, 2),
                'failure_rate' => round((float) $metric->failure_rate, 2),
                'courier_cost' => round((float) $metric->total_courier_cost, 2),
                'last_calculated_at' => $metric->last_calculated_at?->toDateTimeString(),
            ])
            ->all();

        return $this->payload('couriers', $range, [
            $this->card('Shipments', $this->number(collect($rows)->sum('total_shipments')), 'Courier metric snapshot', 'studio-badge--neutral'),
            $this->card('Delivered', $this->number(collect($rows)->sum('delivered_shipments')), 'Delivered shipments', 'studio-badge--success'),
            $this->card('Returned', $this->number(collect($rows)->sum('returned_shipments')), 'Returned shipments', 'studio-badge--warning'),
            $this->card('Courier Cost', $this->money(collect($rows)->sum('courier_cost')), 'Total courier cost in metrics', 'studio-badge--info'),
        ], [
            $this->column('provider', 'Courier'),
            $this->column('total_shipments', 'Total', 'number'),
            $this->column('delivered_shipments', 'Delivered', 'number'),
            $this->column('returned_shipments', 'Returned', 'number'),
            $this->column('failed_shipments', 'Failed', 'number'),
            $this->column('success_rate', 'Success %', 'percent'),
            $this->column('return_rate', 'Return %', 'percent'),
            $this->column('courier_cost', 'Courier Cost', 'money'),
        ], $rows, 'Courier metrics are current snapshots; date filters are shown for dashboard consistency.');
    }

    protected function expenseReport(array $range): array
    {
        $expenses = Expense::query()->whereBetween('expense_date', [$range['date_from'], $range['date_to']]);
        $rows = (clone $expenses)
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw(
                "COALESCE(expense_categories.name, 'Uncategorized') as category,
                COUNT(expenses.id) as expense_count,
                SUM(expenses.amount) as total_amount,
                AVG(expenses.amount) as average_amount,
                MAX(expenses.expense_date) as latest_expense_date"
            )
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row): array => [
                'category' => (string) $row->category,
                'expense_count' => (int) $row->expense_count,
                'total_amount' => round((float) $row->total_amount, 2),
                'average_amount' => round((float) $row->average_amount, 2),
                'latest_expense_date' => (string) $row->latest_expense_date,
            ])
            ->all();

        return $this->payload('expenses', $range, [
            $this->card('Expense Total', $this->money((float) (clone $expenses)->sum('amount')), 'Selected date range', 'studio-badge--warning'),
            $this->card('Expense Records', $this->number((clone $expenses)->count()), 'Number of expense entries', 'studio-badge--neutral'),
            $this->card('Categories', $this->number(count($rows)), 'Categories with spend', 'studio-badge--info'),
            $this->card('Average Expense', $this->money((float) (clone $expenses)->avg('amount')), 'Average entry amount', 'studio-badge--neutral'),
        ], [
            $this->column('category', 'Category'),
            $this->column('expense_count', 'Entries', 'number'),
            $this->column('total_amount', 'Total', 'money'),
            $this->column('average_amount', 'Average', 'money'),
            $this->column('latest_expense_date', 'Latest Date'),
        ], $rows);
    }

    protected function marketingRoiReport(array $range): array
    {
        $orderRows = Order::query()
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->selectRaw(
                'DATE(created_at) as report_date,
                SUM(total) as revenue,
                SUM(product_cost_total) as product_cost,
                SUM(courier_cost_total) as courier_cost,
                SUM(gross_profit) as gross_profit'
            )
            ->groupBy('report_date')
            ->get()
            ->keyBy('report_date');
        $adSpendRows = AdSpend::query()
            ->whereBetween('spend_date', [$range['date_from'], $range['date_to']])
            ->selectRaw('spend_date as report_date, SUM(amount) as ad_spend')
            ->groupBy('report_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->report_date)->toDateString());
        $expenseRows = Expense::query()
            ->whereBetween('expense_date', [$range['date_from'], $range['date_to']])
            ->selectRaw('expense_date as report_date, SUM(amount) as business_expense')
            ->groupBy('report_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->report_date)->toDateString());

        $rows = [];
        $cursor = Carbon::parse($range['date_to'])->startOfDay();
        $end = Carbon::parse($range['date_from'])->startOfDay();

        while ($cursor->gte($end)) {
            $date = $cursor->toDateString();
            $order = $orderRows->get($date);
            $adSpend = (float) ($adSpendRows->get($date)?->ad_spend ?? 0);
            $businessExpense = (float) ($expenseRows->get($date)?->business_expense ?? 0);
            $revenue = (float) ($order?->revenue ?? 0);
            $productCost = (float) ($order?->product_cost ?? 0);
            $courierCost = (float) ($order?->courier_cost ?? 0);
            $profit = $revenue - $productCost - $courierCost - $adSpend - $businessExpense;

            if ($revenue > 0 || $adSpend > 0 || $businessExpense > 0) {
                $rows[] = [
                    'report_date' => $date,
                    'revenue' => round($revenue, 2),
                    'product_cost' => round($productCost, 2),
                    'courier_cost' => round($courierCost, 2),
                    'ad_spend' => round($adSpend, 2),
                    'business_expense' => round($businessExpense, 2),
                    'net_profit' => round($profit, 2),
                    'roi' => $adSpend > 0 ? round(($profit / $adSpend) * 100, 2) : null,
                ];
            }

            $cursor->subDay();
        }

        $revenue = collect($rows)->sum('revenue');
        $adSpend = collect($rows)->sum('ad_spend');
        $businessExpense = collect($rows)->sum('business_expense');
        $profit = collect($rows)->sum('net_profit');

        return $this->payload('marketing_roi', $range, [
            $this->card('Delivered Revenue', $this->money($revenue), 'Delivered orders only', 'studio-badge--info'),
            $this->card('Ad Spend', $this->money($adSpend), 'Manual ad spend records', 'studio-badge--warning'),
            $this->card('Net Profit', $this->money($profit), 'Revenue minus costs, ad spend, and expenses', 'studio-badge--success'),
            $this->card('ROI', $adSpend > 0 ? $this->percent(($profit / $adSpend) * 100) : 'N/A', 'Profit divided by ad spend', 'studio-badge--neutral'),
        ], [
            $this->column('report_date', 'Date'),
            $this->column('revenue', 'Revenue', 'money'),
            $this->column('product_cost', 'Product Cost', 'money'),
            $this->column('courier_cost', 'Courier Cost', 'money'),
            $this->column('ad_spend', 'Ad Spend', 'money'),
            $this->column('business_expense', 'Business Expense', 'money'),
            $this->column('net_profit', 'Net Profit', 'money'),
            $this->column('roi', 'ROI %', 'percent'),
        ], $rows);
    }

    protected function payload(string $key, array $range, array $summary, array $columns, array $rows, ?string $note = null): array
    {
        $definition = self::REPORTS[$key];

        return [
            'key' => $key,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'tone' => $definition['tone'],
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'summary' => $summary,
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => count($rows),
            'note' => $note,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    protected function ordersInRange(array $range): Builder
    {
        return Order::query()->whereBetween('created_at', [$range['from'], $range['to']]);
    }

    protected function revenueOrdersInRange(array $range): Builder
    {
        return $this->ordersInRange($range)->whereNotIn('status', self::REVENUE_EXCLUDED_STATUSES);
    }

    protected function normalizeReportKey(string $key): string
    {
        return array_key_exists($key, self::REPORTS) ? $key : 'sales';
    }

    protected function card(string $label, string $value, string $meta, string $tone): array
    {
        return compact('label', 'value', 'meta', 'tone');
    }

    protected function column(string $key, string $label, string $type = 'text'): array
    {
        return compact('key', 'label', 'type');
    }

    protected function money(float|int|null $value): string
    {
        return number_format((float) $value, 2);
    }

    protected function number(float|int|null $value): string
    {
        return number_format((float) $value, is_float($value) ? 2 : 0);
    }

    protected function percent(float|int|null $value): string
    {
        return number_format((float) $value, 2).'%';
    }

    protected function rate(float $part, float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0.0;
    }
}
