<?php

namespace Tests\Feature\Finance;

use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Services\ExpenseService;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountTransaction;
use App\Modules\Finance\Services\AccountService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderManagementService;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression guard for the Accounts ledger added 2026-07-24 alongside the
 * Dashboard's Accounts panel. No Studio page yet lets staff pick a
 * channel, so every transaction is auto-recorded into Cash — these tests
 * cover that wiring (OrderManagementService::updateStatus() on delivery,
 * ExpenseService::createExpense()), the self-healing default account
 * (must never break a real order status change even if AccountsSeeder
 * hasn't run), and that backfill is idempotent.
 */
class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    protected function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ZC-ACCT-'.uniqid(),
            'customer_name' => 'Test Customer', 'customer_phone' => '017'.rand(10000000, 99999999),
            'address' => 'Dhaka', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000,
            'product_cost_total' => 300, 'courier_cost_total' => 50, 'gross_profit' => 650,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_marking_an_order_delivered_records_a_cash_credit_transaction(): void
    {
        $this->seed(AccountsSeeder::class);
        $order = $this->order();

        app(OrderManagementService::class)->updateStatus($order, 'delivered');

        $cash = Account::where('slug', 'cash')->firstOrFail();
        $this->assertDatabaseHas('account_transactions', [
            'order_id' => $order->id,
            'account_id' => $cash->id,
            'type' => AccountTransaction::TYPE_CREDIT,
            'amount' => 1000.00,
        ]);
    }

    public function test_default_account_self_heals_when_accounts_seeder_has_not_run(): void
    {
        // Deliberately not seeding AccountsSeeder — a real order status
        // change must succeed regardless.
        $order = $this->order();

        app(OrderManagementService::class)->updateStatus($order, 'delivered');

        $this->assertDatabaseHas('accounts', ['slug' => 'cash']);
        $this->assertDatabaseHas('account_transactions', ['order_id' => $order->id]);
    }

    public function test_marking_an_order_delivered_twice_does_not_duplicate_the_transaction(): void
    {
        $this->seed(AccountsSeeder::class);
        $order = $this->order();

        app(OrderManagementService::class)->updateStatus($order, 'delivered');
        app(OrderManagementService::class)->updateStatus($order->fresh(), 'delivered'); // no-op: already delivered

        $this->assertSame(1, AccountTransaction::where('order_id', $order->id)->count());
    }

    public function test_creating_an_expense_records_a_cash_debit_transaction(): void
    {
        $this->seed(AccountsSeeder::class);
        $category = ExpenseCategory::create(['name' => 'Packaging', 'slug' => 'packaging-'.uniqid(), 'status' => 'active']);

        $expense = app(ExpenseService::class)->createExpense([
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => 500,
            'description' => 'Box supplies',
        ]);

        $cash = Account::where('slug', 'cash')->firstOrFail();
        $this->assertDatabaseHas('account_transactions', [
            'expense_id' => $expense->id,
            'account_id' => $cash->id,
            'type' => AccountTransaction::TYPE_DEBIT,
            'amount' => 500.00,
        ]);
    }

    public function test_today_and_total_balance_aggregates_are_correct(): void
    {
        $this->seed(AccountsSeeder::class);
        $order = $this->order(['total' => 1200]);
        app(OrderManagementService::class)->updateStatus($order, 'delivered');

        $category = ExpenseCategory::create(['name' => 'Transport', 'slug' => 'transport-'.uniqid(), 'status' => 'active']);
        app(ExpenseService::class)->createExpense([
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => 300,
            'description' => 'Fuel',
        ]);

        $service = app(AccountService::class);
        $cash = Account::where('slug', 'cash')->firstOrFail();

        $this->assertSame(1200.0, $service->todayCredit()[$cash->id]);
        $this->assertSame(300.0, $service->todayDebit()[$cash->id]);
        $this->assertSame(900.0, $service->totalBalance()[$cash->id]); // 1200 credit - 300 debit
    }

    public function test_backfill_is_idempotent_and_covers_existing_data(): void
    {
        $this->seed(AccountsSeeder::class);
        // Created directly (bypassing the service), simulating data that
        // predates this feature.
        $order = $this->order(['status' => 'delivered', 'total' => 800]);

        app(AccountService::class)->backfill();
        app(AccountService::class)->backfill(); // must not duplicate on re-run

        $this->assertSame(1, AccountTransaction::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('account_transactions', [
            'order_id' => $order->id,
            'amount' => 800.00,
            'type' => AccountTransaction::TYPE_CREDIT,
        ]);
    }
}
