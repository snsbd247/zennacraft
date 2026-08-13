<?php

namespace Tests\Feature\Finance;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Customer\Models\Customer;
use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Finance\Services\FinanceService;
use App\Modules\Marketing\Models\AdSpend;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Services\MarketingCampaignService;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * gross_profit (FinanceService::summary(), ReportService::profitReport())
 * and net_profit (FinanceService::profitEngine(), MarketingAnalyticsService
 * ::profitSummary()) are legitimately different accounting concepts, not
 * two competing formulas for the same question — they differ in which
 * COSTS are subtracted (product+courier vs product+courier+ads+overhead).
 *
 * As of Phase B.1 (B7), they both also share the same ORDER-STATUS scope:
 * delivered only. This is a Cash-on-Delivery business — money changes
 * hands on delivery, so a cancelled/returned/RTO'd order never generated
 * real revenue and must not contribute to gross_profit either, exactly
 * like it already didn't contribute to net_profit. Before this fix,
 * gross_profit summed ALL order statuses while net_profit summed
 * delivered-only, so the gap between the two headline figures silently
 * mixed "different costs" with "different order sets" — the exact bug
 * class this phase exists to kill.
 */
class ProfitConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_and_returned_orders_contribute_zero_to_gross_profit(): void
    {
        // Two delivered orders.
        Order::create([
            'order_number' => 'ZC-PROFIT-1', 'customer_name' => 'A', 'customer_phone' => '01711110001',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'product_cost_total' => 300, 'courier_cost_total' => 50, 'gross_profit' => 700,
            'status' => 'delivered',
        ]);
        Order::create([
            'order_number' => 'ZC-PROFIT-2', 'customer_name' => 'B', 'customer_phone' => '01711110002',
            'address' => 'Dhaka', 'subtotal' => 2000, 'delivery_fee' => 0, 'total' => 2000,
            'product_cost_total' => 600, 'courier_cost_total' => 100, 'gross_profit' => 1400,
            'status' => 'delivered',
        ]);

        // A cancelled order and an RTO'd (returned) order: the customer
        // never paid for either. Before B7 these still contributed their
        // stored gross_profit (350 and 220) to the headline figure — money
        // that was never received counted as profit.
        Order::create([
            'order_number' => 'ZC-PROFIT-3', 'customer_name' => 'C', 'customer_phone' => '01711110003',
            'address' => 'Dhaka', 'subtotal' => 500, 'delivery_fee' => 0, 'total' => 500,
            'product_cost_total' => 150, 'courier_cost_total' => 0, 'gross_profit' => 350,
            'status' => 'cancelled',
        ]);
        Order::create([
            'order_number' => 'ZC-PROFIT-4', 'customer_name' => 'D', 'customer_phone' => '01711110004',
            'address' => 'Dhaka', 'subtotal' => 300, 'delivery_fee' => 0, 'total' => 300,
            'product_cost_total' => 80, 'courier_cost_total' => 40, 'gross_profit' => 220,
            'status' => 'returned',
        ]);
        // A pending order too: not cancelled/RTO'd, but also not yet
        // delivered — hasn't generated real revenue yet either.
        Order::create([
            'order_number' => 'ZC-PROFIT-5', 'customer_name' => 'E', 'customer_phone' => '01711110005',
            'address' => 'Dhaka', 'subtotal' => 400, 'delivery_fee' => 0, 'total' => 400,
            'product_cost_total' => 100, 'courier_cost_total' => 0, 'gross_profit' => 300,
            'status' => 'pending',
        ]);

        AdSpend::create(['spend_date' => now()->toDateString(), 'platform' => 'facebook', 'amount' => 200]);

        $category = ExpenseCategory::create(['name' => 'Test Overhead', 'slug' => 'test-overhead-'.uniqid(), 'status' => 'active']);
        Expense::create(['expense_category_id' => $category->id, 'expense_date' => now()->toDateString(), 'amount' => 100, 'description' => 'Test overhead expense']);

        $commandCenter = app(FinanceService::class)->commandCenter();

        $grossProfit = (float) $commandCenter['summaries']['overall']['gross_profit'];
        $netProfit = (float) $commandCenter['profit_engine']['net_profit'];

        // Only the two delivered orders count: 700 + 1400 = 2100. The
        // cancelled (350), returned (220), and pending (300) orders'
        // gross_profit — 870 total — must NOT be in this number.
        $this->assertEquals(2100.0, $grossProfit);

        // net_profit was already delivered-only, unaffected by B7:
        // (1000+2000) - (300+600) - (50+100) - 200 - 100.
        $this->assertEquals(1650.0, $netProfit);

        // The two headline figures now describe the EXACT SAME set of
        // orders (delivered only), differing only in which costs are
        // subtracted — courier cost, ad spend, and business expense.
        // This relationship is simpler and more direct than before B7,
        // when it also had to account for a different order-status scope.
        $courierCost = 50 + 100;
        $adSpend = 200;
        $businessExpense = 100;
        $this->assertEquals(
            $grossProfit - $courierCost - $adSpend - $businessExpense,
            $netProfit
        );
    }

    public function test_profit_report_gross_profit_card_excludes_non_delivered_orders(): void
    {
        Order::create([
            'order_number' => 'ZC-RPT-1', 'customer_name' => 'A', 'customer_phone' => '01711120001',
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'product_cost_total' => 300, 'courier_cost_total' => 50, 'gross_profit' => 700,
            'status' => 'delivered',
        ]);
        // A pending order, not a cancelled one: revenueOrdersInRange()
        // already excluded cancelled/returned via REVENUE_EXCLUDED_STATUSES
        // before this fix, so a cancelled order wouldn't have
        // distinguished old behavior from new. 'pending' is the case that
        // actually differs — included by the old "not cancelled/returned"
        // scope, excluded by the new "delivered only" scope.
        Order::create([
            'order_number' => 'ZC-RPT-2', 'customer_name' => 'B', 'customer_phone' => '01711120002',
            'address' => 'Dhaka', 'subtotal' => 500, 'delivery_fee' => 0, 'total' => 500,
            'product_cost_total' => 150, 'courier_cost_total' => 0, 'gross_profit' => 350,
            'status' => 'pending',
        ]);

        $report = app(\App\Modules\Report\Services\ReportService::class)->report('profit', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]);

        $grossProfitCard = collect($report['summary'])->firstWhere('label', 'Gross Profit');

        $this->assertNotNull($grossProfitCard);
        // Only the delivered order's 700 — the pending order's 350 must
        // not appear in this card, even though it isn't cancelled/returned.
        $this->assertSame('700.00', $grossProfitCard['value']);
    }

    protected function attributableOrder(MarketingCampaign $campaign, string $phone, float $total): Order
    {
        $customer = Customer::create(['name' => 'Attribution Test', 'phone' => $phone]);

        $message = CommunicationMessage::create([
            'customer_id' => $customer->id,
            'channel' => 'sms',
            'recipient' => $phone,
            'template' => 'coupon_campaign',
            'body' => 'Test campaign message',
            'variables' => ['campaign_id' => $campaign->id],
            'status' => 'sent',
        ]);
        $message->forceFill(['created_at' => now()->subHour()])->save();

        return Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ZC-ATTR-'.uniqid(),
            'customer_name' => 'Attribution Test',
            'customer_phone' => $phone,
            'address' => 'Dhaka',
            'subtotal' => $total,
            'delivery_fee' => 0,
            'total' => $total,
            'status' => 'delivered',
        ]);
    }

    public function test_campaign_revenue_accumulator_correctly_sums_multiple_attributions(): void
    {
        $campaign = MarketingCampaign::create([
            'name' => 'Accumulator Test Campaign',
            'slug' => 'accumulator-test-campaign-'.uniqid(),
            'campaign_type' => 'coupon',
            'status' => 'running',
            'total_converted' => 0,
            'total_revenue' => 0,
        ]);

        $orderA = $this->attributableOrder($campaign, '01711120001', 1000.0);
        $orderB = $this->attributableOrder($campaign, '01711120002', 1500.0);

        $service = app(MarketingCampaignService::class);
        $service->recordOrderAttribution($orderA);
        $service->recordOrderAttribution($orderB);

        $campaign->refresh();

        $this->assertSame(2, $campaign->total_converted);
        $this->assertEqualsWithDelta(2500.0, (float) $campaign->total_revenue, 0.01);
    }

    /**
     * A real interleaved race (two concurrent requests, both reading before
     * either writes) can't be reproduced inside a single-threaded PHPUnit
     * test — and testing recordOrderAttribution() itself can't distinguish
     * old from new code here either: it does its own fresh find() at the
     * start of every call, so two *sequential* calls to it never actually
     * race against each other regardless of which pattern it uses
     * internally (confirmed empirically — stashing this fix and rerunning
     * the sequential-attribution test above still passes against the old
     * code, because sequential calls were never the failure mode).
     *
     * What genuinely distinguishes the two patterns is what each one's
     * write depends on. This test reproduces the exact old line
     * (forceFill(['total_revenue' => (float) $stale->total_revenue +
     * $amount])->save(), taken from git history prior to this fix) next to
     * the new one (increment()), both applied to model instances that are
     * already stale relative to each other — i.e. the precise shape of the
     * race window — to prove the old pattern loses an update under that
     * condition and the new one doesn't.
     */
    public function test_old_pattern_loses_updates_under_staleness_and_new_pattern_does_not(): void
    {
        $old = MarketingCampaign::create([
            'name' => 'Old Pattern Comparison', 'slug' => 'old-pattern-'.uniqid(),
            'campaign_type' => 'coupon', 'status' => 'running', 'total_revenue' => 0,
        ]);
        $oldStaleA = MarketingCampaign::find($old->id);
        $oldStaleB = MarketingCampaign::find($old->id);

        // The exact pattern this task removed from MarketingCampaignService
        // ::recordOrderAttribution(), applied to two instances that both
        // read total_revenue=0 before either writes.
        $oldStaleA->forceFill(['total_revenue' => (float) $oldStaleA->total_revenue + 100.0])->save();
        $oldStaleB->forceFill(['total_revenue' => (float) $oldStaleB->total_revenue + 50.0])->save();

        $old->refresh();
        // B's write, computed from the stale pre-A value of 0, clobbers A's.
        $this->assertEqualsWithDelta(50.0, (float) $old->total_revenue, 0.01);

        $new = MarketingCampaign::create([
            'name' => 'New Pattern Comparison', 'slug' => 'new-pattern-'.uniqid(),
            'campaign_type' => 'coupon', 'status' => 'running', 'total_revenue' => 0,
        ]);
        $newStaleA = MarketingCampaign::find($new->id);
        $newStaleB = MarketingCampaign::find($new->id);

        // The pattern MarketingCampaignService::recordOrderAttribution()
        // uses now, applied to the same staleness condition.
        $newStaleA->increment('total_revenue', 100.0);
        $newStaleB->increment('total_revenue', 50.0);

        $new->refresh();
        // Both increments land — increment() issues `total_revenue =
        // total_revenue + ?` against the column itself, never the
        // in-memory attribute, so staleness of either read is irrelevant.
        $this->assertEqualsWithDelta(150.0, (float) $new->total_revenue, 0.01);
    }
}
