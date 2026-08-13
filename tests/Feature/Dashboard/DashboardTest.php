<?php

namespace Tests\Feature\Dashboard;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Customer\Models\Customer;
use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Facebook\Models\FacebookEvent;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the rebuilt Studio dashboard (2026-07-24, expanded
 * with Income/Expense/source/CAPI panels shortly after): status counts,
 * delivered-only profit scoping (the same COD principle enforced
 * everywhere else in Studio — money is only real once delivered), and
 * every panel come from real Order/Customer/Expense/FacebookEvent data,
 * never fixtures. There is deliberately no "Accounts" (cash/bank balance)
 * panel — no such ledger exists in this codebase, so nothing fakes one.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);

        $staffUser = StaffUser::create([
            'name' => 'Dashboard Owner', 'email' => 'dashboard-owner@zennacraft.test',
            'phone' => '+8801799990088', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staffUser->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staffUser;
    }

    protected function order(string $status, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ZC-DASH-'.uniqid(),
            'customer_name' => 'Test Customer', 'customer_phone' => '017'.rand(10000000, 99999999),
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'product_cost_total' => 300, 'courier_cost_total' => 50, 'gross_profit' => 650,
            'status' => $status,
        ], $overrides));
    }

    public function test_dashboard_renders_status_counts_and_customer_total(): void
    {
        $this->order('pending');
        $this->order('pending');
        $this->order('processing');
        $this->order('delivered');
        Customer::create(['name' => 'Loyal Buyer', 'phone' => '01799990001']);
        Customer::create(['name' => 'Another Buyer', 'phone' => '01799990002']);

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $response->assertSee('zc-kpi__value">4', false); // Total Orders
        $this->assertSame(4, $response->viewData('totalOrders'));
        $this->assertSame(2, (int) $response->viewData('statusCounts')['pending']);
        $this->assertSame(1, (int) $response->viewData('statusCounts')['processing']);
        $this->assertSame(1, (int) $response->viewData('statusCounts')['delivered']);
        $this->assertSame(2, $response->viewData('totalCustomers'));
    }

    public function test_dashboard_shows_new_orders_today_and_per_status_amounts(): void
    {
        $this->order('pending', ['total' => 500]);
        $this->order('pending', ['total' => 700]);
        // created_at isn't mass-assignable — force it after creation to
        // simulate an order placed a few days ago.
        $this->order('delivered', ['total' => 1200])
            ->forceFill(['created_at' => now()->subDays(3)])
            ->save();

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        // The two pending orders were created "now" (today); the delivered
        // one was backdated 3 days, so it must not count as "New Order".
        $this->assertSame(2, $response->viewData('newOrdersToday'));
        $this->assertEquals(1200.0, $response->viewData('newOrdersTodayAmount'));
        $this->assertEquals(1200.0, $response->viewData('statusAmounts')['pending']);
        $this->assertEquals(1200.0, $response->viewData('statusAmounts')['delivered']);
        $this->assertEquals(2400.0, $response->viewData('totalOrdersAmount'));
    }

    public function test_dashboard_profit_figures_are_delivered_only(): void
    {
        $this->order('delivered', ['gross_profit' => 650]);
        $this->order('pending', ['gross_profit' => 4900]); // must never count toward profit

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $this->assertEquals(650.0, $response->viewData('totalProfit'));
        $this->assertEquals(650.0, $response->viewData('monthProfit'));
    }

    public function test_dashboard_requires_staff_authentication(): void
    {
        $response = $this->get('/studio');

        $response->assertRedirect('/'.config('admin.path').'/login');
    }

    public function test_dashboard_income_panel_lists_delivered_orders_only(): void
    {
        $delivered = $this->order('delivered', ['order_number' => 'ZC-DASH-INCOME', 'total' => 1500]);
        $this->order('pending', ['order_number' => 'ZC-DASH-NOTINCOME']);

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $response->assertSee('ZC-DASH-INCOME');
        $this->assertCount(1, $response->viewData('recentIncome'));
        $this->assertSame($delivered->id, $response->viewData('recentIncome')->first()->id);
    }

    public function test_dashboard_expense_panel_shows_real_expenses(): void
    {
        $category = ExpenseCategory::create(['name' => 'Packaging', 'slug' => 'packaging-'.uniqid(), 'status' => 'active']);
        Expense::create([
            'expense_category_id' => $category->id,
            'expense_date' => now(),
            'amount' => 750,
            'description' => 'Box supplies',
        ]);

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $response->assertSee('Packaging');
        $response->assertSee('Box supplies');
        $this->assertEquals(750.0, $response->viewData('monthExpenses'));
    }

    public function test_dashboard_groups_orders_by_source(): void
    {
        $this->order('pending', ['source' => 'website']);
        $this->order('pending', ['source' => 'website']);
        $this->order('pending', ['source' => 'whatsapp']);

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $this->assertSame(2, (int) $response->viewData('ordersBySource')['website']);
        $this->assertSame(1, (int) $response->viewData('ordersBySource')['whatsapp']);
    }

    public function test_dashboard_shows_facebook_capi_event_counts(): void
    {
        FacebookEvent::create([
            'event_id' => 'evt-'.uniqid(), 'event_name' => 'Purchase',
            'event_time' => now(), 'status' => 'sent', 'sent_at' => now(),
        ]);
        FacebookEvent::create([
            'event_id' => 'evt-'.uniqid(), 'event_name' => 'Purchase',
            'event_time' => now(), 'status' => 'failed',
        ]);

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $response->assertSee('Facebook CAPI');
        $this->assertSame(1, (int) $response->viewData('facebookCapiCounts')['sent']);
        $this->assertSame(1, (int) $response->viewData('facebookCapiCounts')['failed']);
        $this->assertSame(2, $response->viewData('facebookTotal'));
    }

    public function test_dashboard_kpi_cards_are_not_clickable_before_orders_page_exists(): void
    {
        $this->order('pending');

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        // orders.index doesn't exist yet in this rebuild — the cards must
        // degrade to plain (non-link) cards, not throw or link to a 404.
        $response->assertDontSee('href="'.'/studio/orders', false);
    }

    protected function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category-'.uniqid(), 'status' => 'active']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Test Product', 'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()), 'price' => 1000.00, 'stock' => 10, 'status' => 'active',
        ], $overrides));
    }

    public function test_dashboard_shows_top_viewed_products_ranked_by_view_count(): void
    {
        $popular = $this->product(['name' => 'Popular Product']);
        $quiet = $this->product(['name' => 'Quiet Product']);

        foreach (range(1, 3) as $i) {
            CustomerBehaviorEvent::create([
                'product_id' => $popular->id,
                'event_type' => BehaviorEventService::EVENT_PRODUCT_VIEWED,
                'occurred_at' => now()->subMinutes($i),
            ]);
        }
        CustomerBehaviorEvent::create([
            'product_id' => $quiet->id,
            'event_type' => BehaviorEventService::EVENT_PRODUCT_VIEWED,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $topViewed = $response->viewData('topViewedProducts');
        $this->assertSame($popular->id, $topViewed->first()['product']->id);
        $this->assertSame(3, $topViewed->first()['views']);
        $this->assertSame(1, $topViewed->last()['views']);
    }

    public function test_dashboard_groups_orders_by_delivery_zone_and_ignores_zoneless_orders(): void
    {
        // Orders capture a delivery zone (not a free-text district), so the
        // "Top Selling by Zone" panel groups by that, mapped to human labels.
        $this->order('pending', ['delivery_zone' => 'inside_dhaka']);
        $this->order('pending', ['delivery_zone' => 'inside_dhaka']);
        $this->order('pending', ['delivery_zone' => 'outside_dhaka']);
        $this->order('pending', ['delivery_zone' => null]); // must not appear at all

        $response = $this->actingAs($this->owner(), 'staff')->get('/studio');

        $response->assertOk();
        $topDistricts = $response->viewData('topDistricts')->keyBy('district');
        $this->assertSame(2, (int) $topDistricts['Inside Dhaka']->aggregate);
        $this->assertSame(1, (int) $topDistricts['Outside Dhaka']->aggregate);
        $this->assertCount(2, $topDistricts);
    }
}
