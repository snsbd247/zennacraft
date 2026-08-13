<?php

namespace Tests\Feature\Accounts;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountPurpose;
use App\Modules\Finance\Models\AccountTransaction;
use App\Modules\Finance\Models\BillStatement;
use App\Modules\Finance\Models\Employee;
use App\Modules\Finance\Models\FundTransfer;
use App\Modules\Finance\Services\AccountService;
use App\Modules\Order\Models\Order;
use Database\Seeders\AccountsSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $this->seed(AccountsSeeder::class);

        $staff = StaffUser::create([
            'name' => 'Acc Owner', 'email' => 'acc-owner@zennacraft.test',
            'phone' => '+8801700000099', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    private function cash(): Account
    {
        return Account::where('slug', 'cash')->firstOrFail();
    }

    public function test_all_account_pages_render(): void
    {
        $owner = $this->owner();
        foreach (['income', 'expense', 'due', 'balance', 'transfer', 'purpose', 'salary', 'bill'] as $seg) {
            $this->actingAs($owner, 'staff')->get(route("accounts.$seg.index"))->assertOk();
        }
    }

    public function test_add_credit_increases_channel_balance(): void
    {
        $owner = $this->owner();
        $cash = $this->cash();

        $this->actingAs($owner, 'staff')->post(route('accounts.income.store'), [
            'transaction_date' => now()->toDateString(), 'purpose' => 'Office sale',
            'amount' => 1500, 'account_id' => $cash->id, 'description' => 'Invoice S-76',
        ])->assertRedirect(route('accounts.income.index'));

        $this->assertDatabaseHas('account_transactions', ['type' => 'credit', 'account_id' => $cash->id, 'amount' => 1500, 'purpose' => 'Office sale']);
        $this->assertEquals(1500.0, app(AccountService::class)->totalBalance()[$cash->id]);
    }

    public function test_add_debit_uses_purpose_and_decreases_balance(): void
    {
        $owner = $this->owner();
        $cash = $this->cash();
        $purpose = AccountPurpose::create(['name' => 'Supplier Bill', 'type' => 'not_expense']);

        $this->actingAs($owner, 'staff')->post(route('accounts.expense.store'), [
            'transaction_date' => now()->toDateString(), 'account_purpose_id' => $purpose->id,
            'amount' => 400, 'account_id' => $cash->id,
        ])->assertRedirect(route('accounts.expense.index'));

        $this->assertDatabaseHas('account_transactions', ['type' => 'debit', 'account_id' => $cash->id, 'amount' => 400, 'purpose' => 'Supplier Bill', 'account_purpose_id' => $purpose->id]);
        $this->assertEquals(-400.0, app(AccountService::class)->totalBalance()[$cash->id]);
    }

    public function test_fund_transfer_moves_money_via_paired_entries(): void
    {
        $owner = $this->owner();
        $from = $this->cash();
        $to = Account::where('slug', 'city_bank')->firstOrFail();

        $this->actingAs($owner, 'staff')->post(route('accounts.transfer.store'), [
            'from_account_id' => $from->id, 'to_account_id' => $to->id,
            'amount' => 1000, 'cost' => 20, 'comment' => 'move',
        ])->assertRedirect(route('accounts.transfer.index'));

        $this->assertDatabaseCount('fund_transfers', 1);
        $this->assertDatabaseHas('fund_transfers', ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 1000, 'cost' => 20, 'transfer_amount' => 980]);
        // paired ledger entries
        $this->assertDatabaseHas('account_transactions', ['type' => 'debit', 'account_id' => $from->id, 'amount' => 1000]);
        $this->assertDatabaseHas('account_transactions', ['type' => 'credit', 'account_id' => $to->id, 'amount' => 980]);
        $balances = app(AccountService::class)->totalBalance();
        $this->assertEquals(-1000.0, $balances[$from->id]);
        $this->assertEquals(980.0, $balances[$to->id]);

        // deleting the transfer reverts both balances (cascade removes paired entries)
        $transfer = FundTransfer::first();
        $this->actingAs($owner, 'staff')->deleteJson(route('accounts.transfer.destroy', $transfer))->assertOk();
        $this->assertDatabaseCount('account_transactions', 0);
    }

    public function test_account_purpose_crud(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('accounts.purpose.store'), ['name' => 'Courier Charge', 'type' => 'fixed_expense'])
            ->assertRedirect(route('accounts.purpose.index'));
        $purpose = AccountPurpose::where('name', 'Courier Charge')->firstOrFail();
        $this->assertSame('fixed_expense', $purpose->type);

        $this->actingAs($owner, 'staff')->put(route('accounts.purpose.update', $purpose), ['name' => 'Courier', 'type' => 'not_expense'])->assertRedirect();
        $this->assertSame('Courier', $purpose->fresh()->name);
    }

    public function test_bill_crud_and_toggle(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('accounts.bill.store'), ['name' => 'Office Rent', 'status' => 'active'])->assertRedirect();
        $bill = BillStatement::where('name', 'Office Rent')->firstOrFail();

        $this->actingAs($owner, 'staff')->postJson(route('accounts.bill.toggle', $bill))->assertOk()->assertJson(['status' => 'inactive']);
        $this->assertFalse($bill->fresh()->isActive());
    }

    public function test_employee_crud_and_toggle(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('accounts.salary.store'), [
            'name' => 'Samiul', 'position' => 'Manager', 'email' => 'samiul@x.com', 'phone' => '01300000000',
            'salary' => 20000, 'status' => 'active',
        ])->assertRedirect(route('accounts.salary.index'));
        $emp = Employee::where('name', 'Samiul')->firstOrFail();
        $this->assertEquals(20000.0, (float) $emp->salary);

        $this->actingAs($owner, 'staff')->postJson(route('accounts.salary.toggle', $emp))->assertOk()->assertJson(['status' => 'inactive']);
    }

    public function test_balance_channel_add_and_toggle(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('accounts.balance.store'), ['name' => 'Rocket'])->assertRedirect();
        $acc = Account::where('name', 'Rocket')->firstOrFail();
        $this->assertTrue($acc->active);

        $this->actingAs($owner, 'staff')->postJson(route('accounts.balance.toggle', $acc))->assertOk()->assertJson(['active' => false]);
    }

    public function test_due_get_paid_records_credit_and_clears_due(): void
    {
        $owner = $this->owner();
        $cash = $this->cash();
        $order = Order::create([
            'order_number' => 'ORD-1001', 'customer_name' => 'Karim', 'customer_phone' => '01711100616',
            'address' => 'Dhaka', 'subtotal' => 3250, 'delivery_fee' => 0, 'total' => 3250, 'paid_amount' => 0, 'status' => 'pending',
        ]);

        $this->actingAs($owner, 'staff')->get(route('accounts.due.index'))->assertOk()->assertSee('ORD-1001');

        $this->actingAs($owner, 'staff')->post(route('accounts.due.paid', $order), ['account_id' => $cash->id])->assertRedirect();
        $this->assertEquals(3250.0, (float) $order->fresh()->paid_amount);
        $this->assertDatabaseHas('account_transactions', ['type' => 'credit', 'account_id' => $cash->id, 'amount' => 3250, 'purpose' => 'Due amount paid']);
    }
}
