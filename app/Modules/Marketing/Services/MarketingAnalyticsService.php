<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerPersonalizationService;
use App\Modules\Expense\Services\ExpenseService;
use App\Modules\Facebook\Models\FacebookEvent;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Models\MarketingCampaignLog;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Review\Services\ProductReviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsService
{
    public const PLATFORMS = [
        'facebook',
        'google',
        'tiktok',
        'youtube',
        'other',
    ];

    public function __construct(
        private ExpenseService $expenseService,
        private CacheService $cacheService,
        private BehaviorEventService $behaviorEventService,
        private CustomerPersonalizationService $personalizationService,
        private ProductReviewService $reviewService,
    ) {}

    public function commandCenter(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::MARKETING_COMMAND_CENTER,
            fn (): array => $this->buildCommandCenter(),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::MARKETING_CAMPAIGNS_TAG]
        );
    }

    public function customerMarketingProfile(Customer $customer): array
    {
        $campaignMessage = $customer->communicationMessages()
            ->latest()
            ->limit(30)
            ->get()
            ->first(fn (CommunicationMessage $message): bool => filled(data_get($message->variables, 'campaign_id')));

        $campaign = $campaignMessage
            ? MarketingCampaign::query()->find((int) data_get($campaignMessage->variables, 'campaign_id'))
            : null;

        $couponRevenue = (float) $customer->couponUsages()
            ->whereNotNull('order_id')
            ->whereHas('order', fn (Builder $query) => $query->whereNotIn('status', ['cancelled', 'returned']))
            ->with('order:id,total')
            ->get()
            ->sum(fn (CouponUsage $usage): float => (float) $usage->order?->total);

        $campaignRevenue = (float) MarketingCampaignLog::query()
            ->where('log_type', 'conversion')
            ->where('metadata->customer_id', $customer->id)
            ->get(['metadata'])
            ->sum(fn (MarketingCampaignLog $log): float => (float) data_get($log->metadata, 'revenue', 0));

        return [
            'first_source' => $campaign ? 'Campaign: '.$campaign->name : ($customer->couponUsages()->exists() ? 'Coupon' : 'Unknown'),
            'last_campaign' => $campaign?->name,
            'last_campaign_id' => $campaign?->id,
            'coupon_usage_count' => (int) $customer->couponUsages()->count(),
            'coupon_discount_total' => (float) $customer->couponUsages()->sum('discount_amount'),
            'segment_names' => $customer->marketingSegments()->pluck('name')->all(),
            'marketing_message_count' => (int) $customer->communicationMessages()
                ->whereNotNull('variables->campaign_id')
                ->count(),
            'recovery_message_count' => (int) $customer->communicationMessages()->whereNotNull('recovery_id')->count(),
            'total_attributed_revenue' => $campaignRevenue + $couponRevenue,
            'is_estimated' => true,
        ];
    }

    public function orderMarketingProfile(Order $order): array
    {
        $campaign = $this->campaignForOrder($order);
        $facebookTracked = FacebookEvent::query()
            ->where('order_id', $order->id)
            ->where('event_name', 'Purchase')
            ->exists();

        $source = $campaign
            ? 'Campaign'
            : ($order->coupon_code ? 'Coupon' : ($facebookTracked ? 'Facebook' : 'Unknown'));

        return [
            'campaign_id' => $campaign?->id,
            'campaign_name' => $campaign?->name,
            'coupon_code' => $order->coupon_code,
            'landing_page' => null,
            'attribution_source' => $source,
            'discount_amount' => (float) $order->coupon_discount_amount,
            'marketing_cost_estimate' => null,
            'profit_after_ads' => (float) $order->gross_profit,
            'facebook_purchase_tracked' => $facebookTracked,
            'is_estimated' => $campaign === null || ! $facebookTracked,
        ];
    }

    public function saveSpend(array $data): AdSpend
    {
        $spendDate = Carbon::parse($data['spend_date'])->toDateString();
        $adSpend = AdSpend::whereDate('spend_date', $spendDate)->first();

        if ($adSpend) {
            return $this->updateSpend($adSpend, $data);
        }

        return AdSpend::create($this->spendPayload($data));
    }

    public function updateSpend(AdSpend $adSpend, array $data): AdSpend
    {
        $adSpend->update($this->spendPayload($data));

        return $adSpend->refresh();
    }

    public function dailySpend(?string $date = null): float
    {
        $spendDate = Carbon::parse($date ?? now())->toDateString();

        return (float) AdSpend::whereDate('spend_date', $spendDate)->sum('amount');
    }

    public function monthlySpend(?int $year = null, ?int $month = null): float
    {
        $date = now();
        $year ??= (int) $date->year;
        $month ??= (int) $date->month;

        return (float) AdSpend::query()
            ->whereYear('spend_date', $year)
            ->whereMonth('spend_date', $month)
            ->sum('amount');
    }

    public function totalSpend(): float
    {
        return (float) AdSpend::sum('amount');
    }

    public function dailyProfit(?string $date = null): array
    {
        $spendDate = Carbon::parse($date ?? now())->toDateString();

        return $this->profitSummary(
            $this->deliveredOrdersQuery()->whereDate('created_at', $spendDate),
            $this->dailySpend($spendDate),
            $this->expenseService->dailyExpense($spendDate)
        );
    }

    public function monthlyProfit(?int $year = null, ?int $month = null): array
    {
        $date = now();
        $year ??= (int) $date->year;
        $month ??= (int) $date->month;

        return $this->profitSummary(
            $this->deliveredOrdersQuery()
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month),
            $this->monthlySpend($year, $month),
            $this->expenseService->monthlyExpense($year, $month)
        );
    }

    public function overallProfit(): array
    {
        return $this->profitSummary(
            $this->deliveredOrdersQuery(),
            $this->totalSpend(),
            $this->expenseService->totalExpense()
        );
    }

    public function recentSpends(int $perPage = 15): LengthAwarePaginator
    {
        return AdSpend::query()
            ->with('staffUser')
            ->orderByDesc('spend_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function platforms(): array
    {
        return self::PLATFORMS;
    }

    protected function buildCommandCenter(): array
    {
        $overall = $this->overallProfit();
        $campaigns = $this->campaignPerformance();
        $coupons = $this->couponPerformance();
        $landingPages = $this->landingPagePerformance();
        $sources = $this->sourceAttribution();
        $recovery = $this->recoveryAttribution();

        return [
            'dashboard' => $this->commandDashboard($overall, $campaigns, $coupons, $landingPages, $recovery),
            'campaigns' => $campaigns,
            'coupons' => $coupons,
            'landing_pages' => $landingPages,
            'sources' => $sources,
            'behavior_sources' => $this->behaviorSourcePerformance(),
            'products' => $this->productMarketingPerformance(),
            'segments' => $this->segmentPerformance(),
            'recovery' => $recovery,
            'personalization' => $this->personalizationService->marketingIntentSummary(),
            'trust' => $this->reviewService->reviewMetrics(),
            'roi_by_date' => $this->roiByDate(),
            'data_notes' => [
                'Order-level UTM/source fields are partial; behavior source events use internal tracking and unknown traffic appears as Unknown.',
                'Landing page views appear when behavior source tracking can infer them; otherwise landing rows use coupon-targeted order data when available.',
                'Campaign profit after ads is estimated because campaign-specific spend is not stored yet.',
            ],
        ];
    }

    protected function behaviorSourcePerformance(): array
    {
        return $this->behaviorEventService->sourceSummary();
    }

    protected function commandDashboard(array $overall, array $campaigns, array $coupons, array $landingPages, array $recovery): array
    {
        $campaignRevenue = (float) collect($campaigns)->sum('revenue');
        $couponRevenue = (float) collect($coupons)->sum('revenue');
        $marketingRevenue = $campaignRevenue > 0 ? $campaignRevenue : $couponRevenue;
        $ordersFromMarketing = (int) collect($campaigns)->sum('orders') + (int) collect($coupons)->sum('orders');
        $adSpend = (float) $overall['ad_spend'];

        return [
            'marketing_revenue' => $marketingRevenue,
            'orders_from_marketing' => $ordersFromMarketing,
            'ad_spend' => $adSpend,
            'profit_after_ads' => (float) $overall['net_business_profit'],
            'roas' => $adSpend > 0 ? round($marketingRevenue / $adSpend, 2) : null,
            'cpa' => $ordersFromMarketing > 0 ? round($adSpend / $ordersFromMarketing, 2) : null,
            'active_campaigns' => MarketingCampaign::query()->whereIn('status', ['scheduled', 'running'])->count(),
            'active_coupons' => Coupon::query()
                ->where('status', 'active')
                ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->count(),
            'landing_page_orders' => (int) collect($landingPages)->sum('orders'),
            'recovery_revenue' => (float) $recovery['recovered_revenue'],
            'is_estimated' => true,
        ];
    }

    protected function campaignPerformance(int $limit = 12): array
    {
        return MarketingCampaign::query()
            ->with('segment:id,name')
            ->orderByDesc('total_revenue')
            ->orderByDesc('total_converted')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (MarketingCampaign $campaign): array {
                $queued = max(0, (int) $campaign->total_queued);
                $orders = (int) $campaign->total_converted;
                $revenue = (float) $campaign->total_revenue;

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'type' => $campaign->campaign_type,
                    'status' => $campaign->status,
                    'audience' => $campaign->audience_type === 'segment'
                        ? ($campaign->segment?->name ?: 'Segment')
                        : str_replace('_', ' ', ucfirst($campaign->audience_type)),
                    'budget' => null,
                    'spend' => null,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'discount' => null,
                    'profit_after_ads' => null,
                    'roas' => null,
                    'conversion_rate' => $queued > 0 ? round(($orders / $queued) * 100, 2) : 0.0,
                    'starts_at' => $campaign->starts_at?->format('M j, Y'),
                    'ends_at' => $campaign->ends_at?->format('M j, Y'),
                    'is_estimated' => true,
                ];
            })
            ->all();
    }

    protected function couponPerformance(int $limit = 12): array
    {
        return DB::table('coupons')
            ->leftJoin('coupon_usages', 'coupon_usages.coupon_id', '=', 'coupons.id')
            ->leftJoin('orders', 'orders.id', '=', 'coupon_usages.order_id')
            ->select([
                'coupons.id',
                'coupons.code',
                'coupons.discount_type',
                'coupons.discount_value',
                'coupons.status',
                'coupons.ends_at',
            ])
            ->selectRaw('COUNT(coupon_usages.id) as uses_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN orders.status NOT IN ('cancelled', 'returned') THEN orders.id END) as orders_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status NOT IN ('cancelled', 'returned') THEN orders.total ELSE 0 END), 0) as revenue")
            ->selectRaw('COALESCE(SUM(coupon_usages.discount_amount), 0) as discount_given')
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 'delivered' THEN orders.gross_profit ELSE 0 END), 0) as profit_estimate")
            ->groupBy('coupons.id', 'coupons.code', 'coupons.discount_type', 'coupons.discount_value', 'coupons.status', 'coupons.ends_at')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'type' => (string) $row->discount_type,
                'discount_value' => (float) $row->discount_value,
                'uses' => (int) $row->uses_count,
                'orders' => (int) $row->orders_count,
                'revenue' => round((float) $row->revenue, 2),
                'discount_given' => round((float) $row->discount_given, 2),
                'profit_estimate' => round((float) $row->profit_estimate, 2),
                'average_order_value' => (int) $row->orders_count > 0 ? round((float) $row->revenue / (int) $row->orders_count, 2) : 0.0,
                'conversion_signal' => (int) $row->uses_count > 0 ? 'Used in checkout' : 'No usage yet',
                'expiry' => $row->ends_at ? Carbon::parse($row->ends_at)->format('M j, Y') : 'No expiry',
                'status' => (string) $row->status,
            ])
            ->all();
    }

    protected function landingPagePerformance(int $limit = 10): array
    {
        return LandingPage::query()
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->map(function (LandingPage $landingPage): array {
                $orderIds = $this->landingPageOrderIds($landingPage->id);
                $orders = $orderIds === []
                    ? collect()
                    : Order::query()->whereIn('id', $orderIds)->whereNotIn('status', ['cancelled', 'returned'])->get(['id', 'total']);

                return [
                    'id' => $landingPage->id,
                    'title' => $landingPage->title,
                    'slug' => $landingPage->slug,
                    'status' => $landingPage->status,
                    'views' => null,
                    'orders' => $orders->count(),
                    'revenue' => round((float) $orders->sum('total'), 2),
                    'conversion_rate' => null,
                    'best_product' => $this->bestProductForOrderIds($orderIds),
                    'data_note' => 'Views unavailable; orders use landing-page coupon target records when present.',
                ];
            })
            ->all();
    }

    protected function sourceAttribution(): array
    {
        $sourceRows = [];
        $knownOrderIds = [];
        $spendByPlatform = AdSpend::query()
            ->select('platform')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->groupBy('platform')
            ->pluck('amount', 'platform')
            ->map(fn ($value): float => (float) $value)
            ->all();

        $facebookOrderIds = FacebookEvent::query()
            ->where('event_name', 'Purchase')
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $sourceRows[] = $this->sourceRow('Facebook', $facebookOrderIds, $spendByPlatform['facebook'] ?? 0.0);
        $knownOrderIds = array_merge($knownOrderIds, $facebookOrderIds);

        $couponOrderIds = CouponUsage::query()
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $sourceRows[] = $this->sourceRow('Coupon', $couponOrderIds, 0.0);
        $knownOrderIds = array_merge($knownOrderIds, $couponOrderIds);

        $campaignOrderIds = $this->campaignOrderIds();
        $sourceRows[] = $this->sourceRow('Campaign', $campaignOrderIds, 0.0);
        $knownOrderIds = array_merge($knownOrderIds, $campaignOrderIds);

        foreach (['google' => 'Google', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'other' => 'Other Paid'] as $platform => $label) {
            if (($spendByPlatform[$platform] ?? 0) > 0) {
                $sourceRows[] = $this->sourceRow($label, [], (float) $spendByPlatform[$platform]);
            }
        }

        $unknownQuery = Order::query()->whereNotIn('status', ['cancelled', 'returned']);
        $knownOrderIds = array_values(array_unique(array_filter($knownOrderIds)));

        if ($knownOrderIds !== []) {
            $unknownQuery->whereNotIn('id', $knownOrderIds);
        }

        $sourceRows[] = $this->sourceRow('Unknown', $unknownQuery->pluck('id')->map(fn ($id): int => (int) $id)->all(), 0.0);

        return collect($sourceRows)
            ->filter(fn (array $row): bool => $row['orders'] > 0 || $row['ad_spend'] > 0 || $row['source'] === 'Unknown')
            ->values()
            ->all();
    }

    protected function productMarketingPerformance(int $limit = 12): array
    {
        $marketingOrderIds = array_values(array_unique(array_merge(
            $this->campaignOrderIds(),
            CouponUsage::query()->whereNotNull('order_id')->pluck('order_id')->map(fn ($id): int => (int) $id)->all()
        )));

        if ($marketingOrderIds === []) {
            return [];
        }

        $campaignOrderIdsByCampaign = $this->campaignOrderIdsByCampaign();

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->leftJoin('coupon_usages', 'coupon_usages.order_id', '=', 'orders.id')
            ->whereIn('orders.id', $marketingOrderIds)
            ->whereNotIn('orders.status', ['cancelled', 'returned'])
            ->select('order_items.product_id', 'order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as packages_sold')
            ->selectRaw('COUNT(DISTINCT orders.id) as marketing_orders')
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT coupon_usages.id) as coupon_usage')
            ->selectRaw('COALESCE(SUM(order_items.quantity * COALESCE(product_variants.cost_price, products.cost_price, 0)), 0) as estimated_cost')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($campaignOrderIdsByCampaign): array {
                $revenue = (float) $row->revenue;
                $profit = $revenue - (float) $row->estimated_cost;

                return [
                    'product_id' => $row->product_id,
                    'product_name' => (string) $row->product_name,
                    'packages_sold' => (int) $row->packages_sold,
                    'orders_from_campaigns' => (int) $row->marketing_orders,
                    'revenue_from_campaigns' => round($revenue, 2),
                    'coupon_usage' => (int) $row->coupon_usage,
                    'profit_after_estimated_marketing_cost' => round($profit, 2),
                    'best_campaign' => $this->bestCampaignForProduct((int) $row->product_id, $campaignOrderIdsByCampaign),
                    'best_coupon' => $this->bestCouponForProduct((int) $row->product_id),
                    'is_estimated' => true,
                ];
            })
            ->all();
    }

    protected function segmentPerformance(int $limit = 10): array
    {
        $segmentMessageCounts = $this->segmentMessageCounts();

        return MarketingSegment::query()
            ->withCount('memberships')
            ->whereIn('slug', [
                'new_customer',
                'repeat_customer',
                'vip_customer',
                'high_value_customer',
                'dormant_customer',
                'coupon_lover',
                'abandoned_checkout_customer',
                'at_risk_customer',
            ])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function (MarketingSegment $segment) use ($segmentMessageCounts): array {
                $customerIds = $segment->memberships()->pluck('customer_id')->all();
                $ordersQuery = Order::query()->whereIn('customer_id', $customerIds)->whereNotIn('status', ['cancelled', 'returned']);
                $recoveredCount = $this->recoveredCountForCustomerIds($customerIds);

                return [
                    'id' => $segment->id,
                    'name' => $segment->name,
                    'slug' => $segment->slug,
                    'customers' => (int) ($segment->memberships_count ?? 0),
                    'orders' => (int) (clone $ordersQuery)->count(),
                    'revenue' => (float) (clone $ordersQuery)->sum('total'),
                    'profit_estimate' => (float) (clone $ordersQuery)->where('status', 'delivered')->sum('gross_profit'),
                    'campaign_response' => (int) ($segmentMessageCounts[$segment->id] ?? 0),
                    'coupon_usage' => (int) CouponUsage::query()->whereIn('customer_id', $customerIds)->count(),
                    'recovery_rate' => count($customerIds) > 0 ? round(($recoveredCount / count($customerIds)) * 100, 2) : 0.0,
                ];
            })
            ->all();
    }

    protected function recoveryAttribution(): array
    {
        $recoveries = CheckoutRecovery::query()->with(['product', 'variant'])->get();
        $recovered = $recoveries->where('status', 'recovered');
        $callbackRecovered = $recovered->filter(fn (CheckoutRecovery $recovery): bool => filled($recovery->customer_phone));
        $abandonedSegment = MarketingSegment::query()
            ->withCount('memberships')
            ->where('slug', 'abandoned_checkout_customer')
            ->first();

        return [
            'messages_queued' => (int) CommunicationMessage::query()->whereNotNull('recovery_id')->count(),
            'recovered_orders' => $recovered->count(),
            'recovered_revenue' => round((float) $recovered->sum(fn (CheckoutRecovery $recovery): float => $this->recoveryValue($recovery)), 2),
            'coupon_used_in_recovery' => null,
            'best_recovery_segment' => $abandonedSegment ? [
                'id' => $abandonedSegment->id,
                'name' => $abandonedSegment->name,
                'customers' => (int) $abandonedSegment->memberships_count,
            ] : null,
            'callback_recovered_value' => round((float) $callbackRecovered->sum(fn (CheckoutRecovery $recovery): float => $this->recoveryValue($recovery)), 2),
        ];
    }

    protected function roiByDate(int $days = 14): array
    {
        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo): array {
                $date = now()->subDays($daysAgo)->toDateString();
                $orders = Order::query()->whereDate('created_at', $date)->where('status', 'delivered');
                $revenue = (float) (clone $orders)->sum('total');
                $profit = (float) (clone $orders)->sum('gross_profit');
                $orderCount = (int) (clone $orders)->count();
                $adSpend = $this->dailySpend($date);
                $profitAfterAds = $profit - $adSpend;

                return [
                    'date' => $date,
                    'ad_spend' => $adSpend,
                    'revenue' => $revenue,
                    'orders' => $orderCount,
                    'profit_after_ads' => $profitAfterAds,
                    'roas' => $adSpend > 0 ? round($revenue / $adSpend, 2) : null,
                    'cpa' => $orderCount > 0 ? round($adSpend / $orderCount, 2) : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function sourceRow(string $source, array $orderIds, float $adSpend): array
    {
        $orders = $orderIds === []
            ? collect()
            : Order::query()->whereIn('id', $orderIds)->whereNotIn('status', ['cancelled', 'returned'])->get(['id', 'total', 'status', 'gross_profit', 'coupon_discount_amount']);

        $ordersCount = $orders->count();
        $revenue = (float) $orders->sum('total');
        $discount = (float) $orders->sum('coupon_discount_amount');
        $profitAfterAds = (float) $orders->where('status', 'delivered')->sum('gross_profit') - $adSpend;

        return [
            'source' => $source,
            'orders' => $ordersCount,
            'revenue' => round($revenue, 2),
            'discount' => round($discount, 2),
            'ad_spend' => round($adSpend, 2),
            'profit_after_ads' => round($profitAfterAds, 2),
            'roas' => $adSpend > 0 ? round($revenue / $adSpend, 2) : null,
            'is_estimated' => true,
        ];
    }

    protected function campaignForOrder(Order $order): ?MarketingCampaign
    {
        $campaignLog = MarketingCampaignLog::query()
            ->with('campaign')
            ->where('log_type', 'conversion')
            ->latest('created_at')
            ->get()
            ->first(fn (MarketingCampaignLog $log): bool => (int) data_get($log->metadata, 'order_id') === (int) $order->id);

        if ($campaignLog?->campaign) {
            return $campaignLog->campaign;
        }

        if (! $order->customer_id) {
            return null;
        }

        $message = CommunicationMessage::query()
            ->where('customer_id', $order->customer_id)
            ->where('created_at', '<=', $order->created_at)
            ->latest()
            ->limit(20)
            ->get()
            ->first(fn (CommunicationMessage $message): bool => filled(data_get($message->variables, 'campaign_id')));

        return $message ? MarketingCampaign::query()->find((int) data_get($message->variables, 'campaign_id')) : null;
    }

    protected function campaignOrderIds(): array
    {
        return MarketingCampaignLog::query()
            ->where('log_type', 'conversion')
            ->get()
            ->map(fn (MarketingCampaignLog $log): int => (int) data_get($log->metadata, 'order_id'))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function campaignOrderIdsByCampaign(): array
    {
        return MarketingCampaignLog::query()
            ->where('log_type', 'conversion')
            ->get()
            ->groupBy('marketing_campaign_id')
            ->map(fn (Collection $logs): array => $logs
                ->map(fn (MarketingCampaignLog $log): int => (int) data_get($log->metadata, 'order_id'))
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    protected function landingPageOrderIds(int $landingPageId): array
    {
        return DB::table('coupon_targets')
            ->join('coupon_usages', 'coupon_usages.coupon_id', '=', 'coupon_targets.coupon_id')
            ->where('coupon_targets.target_type', 'landing_page')
            ->where('coupon_targets.target_id', $landingPageId)
            ->whereNotNull('coupon_usages.order_id')
            ->distinct()
            ->pluck('coupon_usages.order_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected function bestProductForOrderIds(array $orderIds): ?string
    {
        if ($orderIds === []) {
            return null;
        }

        return DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->select('product_name')
            ->selectRaw('SUM(quantity) as quantity_sold')
            ->groupBy('product_name')
            ->orderByDesc('quantity_sold')
            ->value('product_name');
    }

    protected function bestCampaignForProduct(int $productId, array $campaignOrderIdsByCampaign): ?string
    {
        if ($productId <= 0 || $campaignOrderIdsByCampaign === []) {
            return null;
        }

        $best = null;
        $bestRevenue = 0.0;

        foreach ($campaignOrderIdsByCampaign as $campaignId => $orderIds) {
            if ($orderIds === []) {
                continue;
            }

            $revenue = (float) DB::table('order_items')
                ->where('product_id', $productId)
                ->whereIn('order_id', $orderIds)
                ->sum('subtotal');

            if ($revenue > $bestRevenue) {
                $bestRevenue = $revenue;
                $best = MarketingCampaign::query()->whereKey($campaignId)->value('name');
            }
        }

        return $best;
    }

    protected function bestCouponForProduct(int $productId): ?string
    {
        if ($productId <= 0) {
            return null;
        }

        return DB::table('coupon_usages')
            ->join('order_items', 'order_items.order_id', '=', 'coupon_usages.order_id')
            ->where('order_items.product_id', $productId)
            ->select('coupon_usages.code')
            ->selectRaw('COUNT(*) as usage_count')
            ->groupBy('coupon_usages.code')
            ->orderByDesc('usage_count')
            ->value('coupon_usages.code');
    }

    protected function segmentMessageCounts(): array
    {
        return CommunicationMessage::query()
            ->latest()
            ->limit(5000)
            ->get()
            ->map(fn (CommunicationMessage $message): int => (int) data_get($message->variables, 'segment_id'))
            ->filter(fn (int $id): bool => $id > 0)
            ->countBy()
            ->all();
    }

    protected function recoveredCountForCustomerIds(array $customerIds): int
    {
        if ($customerIds === []) {
            return 0;
        }

        $phones = Customer::query()
            ->whereIn('id', $customerIds)
            ->pluck('phone')
            ->filter()
            ->all();

        return $phones === []
            ? 0
            : CheckoutRecovery::query()->where('status', 'recovered')->whereIn('customer_phone', $phones)->count();
    }

    protected function recoveryValue(CheckoutRecovery $recovery): float
    {
        $price = $recovery->variant
            ? (float) $recovery->variant->price
            : (float) ($recovery->product?->price ?? 0);

        return $price * max(1, (int) $recovery->quantity);
    }

    protected function spendPayload(array $data): array
    {
        return [
            'spend_date' => Carbon::parse($data['spend_date'])->toDateString(),
            'platform' => $data['platform'],
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
            'staff_user_id' => $data['staff_user_id'] ?? auth()->guard('staff')->id(),
        ];
    }

    protected function deliveredOrdersQuery(): Builder
    {
        return Order::query()->where('status', 'delivered');
    }

    protected function profitSummary(Builder $ordersQuery, float $adSpend, float $businessExpense): array
    {
        $revenue = (float) (clone $ordersQuery)->sum('total');
        $productCost = (float) (clone $ordersQuery)->sum('product_cost_total');
        $courierCost = (float) (clone $ordersQuery)->sum('courier_cost_total');
        $profit = $revenue - $productCost - $courierCost - $adSpend - $businessExpense;

        return [
            'revenue' => $revenue,
            'product_cost' => $productCost,
            'courier_cost' => $courierCost,
            'ad_spend' => $adSpend,
            'business_expense' => $businessExpense,
            'profit' => $profit,
            'net_business_profit' => $profit,
            'roi' => $adSpend > 0 ? ($profit / $adSpend) * 100 : null,
        ];
    }
}
