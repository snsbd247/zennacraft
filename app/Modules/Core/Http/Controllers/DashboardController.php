<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Expense\Models\Expense;
use App\Modules\Facebook\Models\FacebookEvent;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Services\AccountService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private MediaService $mediaService,
    ) {}

    // Spec-locked order status vocabulary — never inferred from the data,
    // so a status with zero orders still renders its own zeroed row.
    private const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'returned', 'cancelled'];

    // Order::SOURCES — kept as a local list (rather than importing the
    // constant) only because the dashboard needs it purely for grouping/
    // labels, not for any write path.
    private const SOURCES = ['website', 'landing', 'custom', 'whatsapp'];

    private const FACEBOOK_STATUSES = ['sent', 'pending', 'failed', 'skipped'];

    public function index(): View
    {
        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusAmounts = Order::query()
            ->selectRaw('status, sum(total) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value) => (float) $value);

        $totalOrders = (int) $statusCounts->sum();
        $newOrdersToday = Order::query()->whereDate('created_at', now()->toDateString())->count();
        $newOrdersTodayAmount = (float) Order::query()->whereDate('created_at', now()->toDateString())->sum('total');

        // COD principle used throughout Studio: money is only real once an
        // order is delivered, so profit/revenue headline figures are
        // scoped to delivered orders only, never the full pipeline.
        $delivered = Order::query()->where('status', 'delivered');
        $totalProfit = (float) (clone $delivered)->sum('gross_profit');
        $monthProfit = (float) (clone $delivered)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('gross_profit');
        $monthRevenue = (float) (clone $delivered)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        // "Income" has no dedicated ledger in this codebase — it's the
        // real payment side of delivered orders, not a separate table, so
        // this reads Order directly rather than inventing a model.
        $recentIncome = (clone $delivered)->latest()->limit(8)->get([
            'id', 'order_number', 'total', 'created_at',
        ]);

        $recentExpenses = Expense::query()->with('category')->latest('expense_date')->limit(8)->get();
        $monthExpenses = (float) Expense::query()
            ->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        // Grouped in PHP rather than SQL's MONTH() — that function isn't
        // portable across the MySQL (production) and SQLite (test) drivers
        // this app runs on.
        $monthlyOrderCounts = Order::query()
            ->whereYear('created_at', now()->year)
            ->get(['created_at'])
            ->groupBy(fn (Order $order) => $order->created_at->month)
            ->map->count();

        $ordersBySource = Order::query()
            ->selectRaw('source, count(*) as aggregate')
            ->groupBy('source')
            ->pluck('aggregate', 'source');

        $facebookCapiCounts = FacebookEvent::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $accounts = Account::query()->orderBy('sort_order')->get();
        $todayCredit = $this->accountService->todayCredit();
        $todayDebit = $this->accountService->todayDebit();
        $totalBalance = $this->accountService->totalBalance();

        $topViewedRows = CustomerBehaviorEvent::query()
            ->where('event_type', BehaviorEventService::EVENT_PRODUCT_VIEWED)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, count(*) as aggregate, max(occurred_at) as last_viewed_at')
            ->groupBy('product_id')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get();
        $productsById = Product::query()
            ->whereIn('id', $topViewedRows->pluck('product_id'))
            ->with('thumbnail')
            ->get()
            ->keyBy('id');
        $topViewedProducts = $topViewedRows
            ->map(fn ($row) => [
                'product' => $productsById->get($row->product_id),
                'views' => (int) $row->aggregate,
                'last_viewed_at' => $row->last_viewed_at,
            ])
            ->filter(fn (array $row) => $row['product'] !== null)
            ->values();

        // Orders capture a delivery ZONE (Inside Dhaka / Sub-urban / Outside
        // Dhaka), not a free-text district — the district column is never
        // populated by the live checkout flows. Group by the field that is
        // actually filled so the panel isn't perpetually empty, mapping each
        // zone key to its human label.
        $zoneLabels = \App\Modules\Checkout\Services\DeliveryChargeService::ZONES;
        $topDistricts = Order::query()
            ->whereNotNull('delivery_zone')->where('delivery_zone', '!=', '')
            ->selectRaw('delivery_zone, count(*) as aggregate')
            ->groupBy('delivery_zone')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($zoneLabels) {
                $row->district = $zoneLabels[$row->delivery_zone] ?? ucfirst(str_replace('_', ' ', (string) $row->delivery_zone));

                return $row;
            });

        return view('studio.dashboard', [
            'statuses' => self::STATUSES,
            'statusCounts' => $statusCounts,
            'statusAmounts' => $statusAmounts,
            'totalOrders' => $totalOrders,
            'totalOrdersAmount' => (float) $statusAmounts->sum(),
            'newOrdersToday' => $newOrdersToday,
            'newOrdersTodayAmount' => $newOrdersTodayAmount,
            'totalProfit' => $totalProfit,
            'monthProfit' => $monthProfit,
            'monthRevenue' => $monthRevenue,
            'totalCustomers' => Customer::count(),
            'recentIncome' => $recentIncome,
            'recentExpenses' => $recentExpenses,
            'monthExpenses' => $monthExpenses,
            'monthlyOrderCounts' => $monthlyOrderCounts,
            'ordersBySource' => $ordersBySource,
            'sources' => self::SOURCES,
            'facebookCapiCounts' => $facebookCapiCounts,
            'facebookStatuses' => self::FACEBOOK_STATUSES,
            'facebookTotal' => (int) $facebookCapiCounts->sum(),
            'accounts' => $accounts,
            'todayCredit' => $todayCredit,
            'todayDebit' => $todayDebit,
            'totalBalance' => $totalBalance,
            'topViewedProducts' => $topViewedProducts,
            'topDistricts' => $topDistricts,
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }
}
