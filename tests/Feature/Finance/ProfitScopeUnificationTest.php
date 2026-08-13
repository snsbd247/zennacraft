<?php

namespace Tests\Feature\Finance;

use App\Modules\Analytics\Services\ExecutiveAnalyticsService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\Customer360Service;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Models\MarketingSegmentMembership;
use App\Modules\Marketing\Services\MarketingAnalyticsService;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Promotion\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B.2's analysis (docs/profit-scope-analysis.md) found that
 * ExecutiveAnalyticsService, MarketingAnalyticsService, Customer360Service,
 * CouponService, and CustomerController each computed "profit" over a
 * broader-than-delivered order set (either "not cancelled/returned" or no
 * status filter at all), producing a 6x spread against the already-fixed
 * delivered-only figures in FinanceService/ReportService (B7). This phase
 * (B.3/D1) rescopes all of them to delivered-only, matching the COD
 * principle that money is only real once an order is delivered.
 *
 * Every order pair below uses a "pending" order, not a cancelled one, as
 * the differentiator — a cancelled order was already excluded by several
 * of these methods' pre-fix "not cancelled/returned" scope, so it wouldn't
 * distinguish old behavior from new (the same lesson B.1's report
 * documented catching in ReportService's test). Pending is included by the
 * old broad scope and excluded by the new delivered-only scope.
 */
class ProfitScopeUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected function deliveredOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ZC-DELIV-'.uniqid(),
            'customer_name' => 'Delivered Buyer',
            'customer_phone' => '01711130001',
            'address' => 'Dhaka',
            'subtotal' => 1000,
            'delivery_fee' => 0,
            'total' => 1000,
            'product_cost_total' => 300,
            'courier_cost_total' => 50,
            'gross_profit' => 650,
            'status' => 'delivered',
        ], $overrides));
    }

    protected function pendingOrder(array $overrides = []): Order
    {
        // A large, profitable-looking order that simply hasn't been
        // delivered yet — exactly the "money never received" case Part 4
        // of the analysis called indefensible to count as profit.
        return Order::create(array_merge([
            'order_number' => 'ZC-PEND-'.uniqid(),
            'customer_name' => 'Pending Buyer',
            'customer_phone' => '01711130002',
            'address' => 'Dhaka',
            'subtotal' => 5000,
            'delivery_fee' => 0,
            'total' => 5000,
            'product_cost_total' => 100,
            'courier_cost_total' => 0,
            'gross_profit' => 4900,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_executive_analytics_profit_figures_exclude_non_delivered_orders(): void
    {
        $this->deliveredOrder();
        $this->pendingOrder();

        $service = app(ExecutiveAnalyticsService::class);

        $executiveMetrics = $service->executiveMetrics();
        $profitAnalytics = $service->profitAnalytics();
        $intelligence = $service->intelligenceCenter();

        // D1: headline KPI row (today_profit) and the profit breakdown
        // panel both recompute total - product_cost - courier_cost from
        // deliveredOrdersQuery() now, not revenueQuery()'s broader scope.
        $this->assertEquals(650.0, $executiveMetrics['today_profit']);
        $this->assertEquals(650.0, $profitAnalytics['today']['gross_profit']);

        // D1: the mislabelled 'net_profit' KPI (ExecutiveAnalyticsService
        // ::intelligenceKpis(), which simply calls profitFor()) inherits
        // the same fix automatically.
        $this->assertEquals(650.0, $intelligence['kpis']['net_profit']);

        // C1: finance intelligence trend/summary 'profit', with 'margin'
        // now measured against delivered-only revenue (650 / 1000 = 65%),
        // not the broader pipeline revenue that would include the pending
        // order's 5000.
        $this->assertEquals(650.0, $intelligence['finance']['summary']['profit']);
        $this->assertEqualsWithDelta(65.0, $intelligence['finance']['summary']['margin'], 0.01);

        // C2: customer intelligence 'lifetime_profit'.
        $this->assertEquals(650.0, $intelligence['customers']['lifetime_profit']);

        // C3: time-range breakdown for 'today', same margin-consistency
        // fix as C1.
        $this->assertEquals(650.0, $intelligence['time']['ranges']['today']['profit']);
        $this->assertEqualsWithDelta(65.0, $intelligence['time']['ranges']['today']['margin'], 0.01);
    }

    public function test_marketing_coupon_and_segment_profit_estimates_exclude_non_delivered_orders(): void
    {
        $delivered = $this->deliveredOrder();
        $pending = $this->pendingOrder();

        // C4: coupon performance's profit_estimate.
        $coupon = Coupon::create([
            'code' => 'PROFITSCOPE',
            'name' => 'Profit Scope Test Coupon',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'status' => 'active',
        ]);
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $delivered->id,
            'code' => $coupon->code,
            'discount_amount' => 50,
            'used_at' => now(),
        ]);
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $pending->id,
            'code' => $coupon->code,
            'discount_amount' => 50,
            'used_at' => now(),
        ]);

        // C5: segment performance's profit_estimate.
        $customer = Customer::create(['name' => 'Segment Test Customer', 'phone' => '01711130003']);
        $segment = MarketingSegment::create([
            'name' => 'VIP Customers', 'slug' => 'vip_customer', 'type' => 'behavioral', 'active' => true,
        ]);
        MarketingSegmentMembership::create([
            'marketing_segment_id' => $segment->id,
            'customer_id' => $customer->id,
            'joined_at' => now(),
        ]);
        $this->deliveredOrder(['customer_id' => $customer->id, 'customer_phone' => '01711130003']);
        $this->pendingOrder(['customer_id' => $customer->id, 'customer_phone' => '01711130003']);

        $commandCenter = app(MarketingAnalyticsService::class)->commandCenter();

        $couponRow = collect($commandCenter['coupons'])->firstWhere('id', $coupon->id);
        $this->assertNotNull($couponRow);
        $this->assertEquals(650.0, $couponRow['profit_estimate']);
        // Both usages still count toward orders/revenue — those fields
        // were never in scope for this fix.
        $this->assertEquals(2, $couponRow['orders']);

        $segmentRow = collect($commandCenter['segments'])->firstWhere('slug', 'vip_customer');
        $this->assertNotNull($segmentRow);
        $this->assertEquals(650.0, $segmentRow['profit_estimate']);
        $this->assertEquals(2, $segmentRow['orders']);
    }

    public function test_marketing_source_attribution_profit_after_ads_excludes_non_delivered_orders(): void
    {
        // Neither order is linked to a coupon, campaign, or Facebook
        // event, so both fall into the 'Unknown' source bucket, which is
        // built from the same sourceRow() method every other channel uses.
        $this->deliveredOrder();
        $this->pendingOrder();

        $commandCenter = app(MarketingAnalyticsService::class)->commandCenter();
        $unknownRow = collect($commandCenter['sources'])->firstWhere('source', 'Unknown');

        $this->assertNotNull($unknownRow);
        // E2: profit_after_ads = delivered-only gross_profit - ad_spend
        // (ad_spend is 0 here) = 650, not 650 + 4900 = 5550.
        $this->assertEquals(650.0, $unknownRow['profit_after_ads']);
        // orders/revenue/roas are unaffected — both orders still counted.
        $this->assertEquals(2, $unknownRow['orders']);
        $this->assertEquals(6000.0, $unknownRow['revenue']);
    }

    public function test_customer_360_financial_metrics_exclude_non_delivered_orders(): void
    {
        $customer = Customer::create(['name' => 'Customer 360 Test', 'phone' => '01711130004']);
        $this->deliveredOrder(['customer_id' => $customer->id, 'customer_phone' => '01711130004']);
        $this->pendingOrder(['customer_id' => $customer->id, 'customer_phone' => '01711130004']);

        $metrics = app(Customer360Service::class)->financialMetrics($customer);

        // G1: gross_profit_total now sums only the delivered order (650),
        // not both (5550) — this was previously the broadest, unscoped
        // ("ALL statuses") figure in the whole codebase.
        $this->assertEquals(650.0, $metrics['gross_profit_total']);
        // profit_margin is now profit / delivered_revenue (650/1000=65%),
        // not profit / total_revenue (which would include the pending
        // order's 5000 and understate the margin).
        $this->assertEqualsWithDelta(65.0, $metrics['profit_margin'], 0.01);
        $this->assertEquals('High Profit', $metrics['profitability_tier']);
        // average_profit_per_order now divides by delivered order count
        // (1), not total order count (2).
        $this->assertEquals(650.0, $metrics['average_profit_per_order']);
        // total_revenue itself stays unscoped — it's a revenue figure,
        // not a profit claim, and was never in scope for this fix.
        $this->assertEquals(6000.0, $metrics['total_revenue']);
    }

    public function test_coupon_usage_stats_gross_profit_excludes_non_delivered_orders(): void
    {
        $delivered = $this->deliveredOrder();
        $pending = $this->pendingOrder();

        $coupon = Coupon::create([
            'code' => 'USAGESTATS',
            'name' => 'Usage Stats Test Coupon',
            'discount_type' => 'fixed',
            'discount_value' => 20,
            'status' => 'active',
        ]);
        CouponUsage::create(['coupon_id' => $coupon->id, 'order_id' => $delivered->id, 'code' => $coupon->code, 'discount_amount' => 20, 'used_at' => now()]);
        CouponUsage::create(['coupon_id' => $coupon->id, 'order_id' => $pending->id, 'code' => $coupon->code, 'discount_amount' => 20, 'used_at' => now()]);

        $stats = app(CouponService::class)->usageStats($coupon);

        // G2: previously summed gross_profit with NO status filter at all.
        $this->assertEquals(650.0, $stats['gross_profit']);
        $this->assertEquals(2, $stats['total_usage']);
    }
}
