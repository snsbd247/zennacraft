<?php

namespace App\Modules\Finance\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountTransaction;
use App\Modules\Finance\Models\FundTransfer;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountService
{
    // No admin page picks a channel yet (Studio's Orders/Expenses pages
    // aren't rebuilt), so every automatically-recorded transaction lands
    // in this default channel until that UI exists.
    public const DEFAULT_ACCOUNT_SLUG = 'cash';

    public function defaultAccount(): Account
    {
        // Self-healing rather than firstOrFail(): this fires from inside
        // OrderManagementService::updateStatus()'s transaction, so if
        // AccountsSeeder somehow hasn't run yet, a real order status
        // change must never fail because of it.
        return Account::query()->firstOrCreate(
            ['slug' => self::DEFAULT_ACCOUNT_SLUG],
            ['name' => 'Cash', 'sort_order' => 0]
        );
    }

    public function recordDeliveredOrderCredit(Order $order): ?AccountTransaction
    {
        return AccountTransaction::firstOrCreate(
            ['order_id' => $order->id],
            [
                'account_id' => $this->defaultAccount()->id,
                'type' => AccountTransaction::TYPE_CREDIT,
                'amount' => $order->total,
                'description' => 'Order '.$order->order_number.' delivered',
                'transaction_date' => now()->toDateString(),
            ]
        );
    }

    public function recordExpenseDebit(Expense $expense): ?AccountTransaction
    {
        return AccountTransaction::firstOrCreate(
            ['expense_id' => $expense->id],
            [
                'account_id' => $this->defaultAccount()->id,
                'type' => AccountTransaction::TYPE_DEBIT,
                'amount' => $expense->amount,
                'description' => $expense->description ?: 'Expense',
                'transaction_date' => $expense->expense_date,
            ]
        );
    }

    /**
     * Move money between two channels. Records the FundTransfer plus a paired
     * debit (out of "from", full amount) and credit (into "to", amount minus
     * transfer cost) so both show up in the Income/Expense ledger and the
     * channel balances reflect the move. Deleting the transfer cascades the
     * paired transactions away, so balances self-correct.
     */
    public function transfer(array $data): FundTransfer
    {
        return DB::transaction(function () use ($data): FundTransfer {
            $amount = round((float) $data['amount'], 2);
            $cost = round((float) ($data['cost'] ?? 0), 2);
            $transferAmount = round($amount - $cost, 2);
            $date = $data['transfer_date'] ?? now()->toDateString();
            $staffId = $data['staff_user_id'] ?? null;

            $transfer = FundTransfer::create([
                'from_account_id' => $data['from_account_id'],
                'to_account_id' => $data['to_account_id'],
                'amount' => $amount,
                'cost' => $cost,
                'transfer_amount' => $transferAmount,
                'comment' => $data['comment'] ?? null,
                'staff_user_id' => $staffId,
                'transfer_date' => $date,
            ]);

            $fromName = Account::whereKey($data['from_account_id'])->value('name');
            $toName = Account::whereKey($data['to_account_id'])->value('name');

            AccountTransaction::create([
                'account_id' => $transfer->from_account_id,
                'type' => AccountTransaction::TYPE_DEBIT,
                'purpose' => 'Fund Transfer',
                'amount' => $amount,
                'description' => trim(($data['comment'] ? $data['comment'].' — ' : '').'Transfer to '.$toName),
                'transaction_date' => $date,
                'fund_transfer_id' => $transfer->id,
                'staff_user_id' => $staffId,
            ]);

            AccountTransaction::create([
                'account_id' => $transfer->to_account_id,
                'type' => AccountTransaction::TYPE_CREDIT,
                'purpose' => 'Fund Transfer',
                'amount' => $transferAmount,
                'description' => trim(($data['comment'] ? $data['comment'].' — ' : '').'Transfer from '.$fromName),
                'transaction_date' => $date,
                'fund_transfer_id' => $transfer->id,
                'staff_user_id' => $staffId,
            ]);

            return $transfer;
        });
    }

    /**
     * @return Collection<int, float> keyed by account_id
     */
    public function todayCredit(): Collection
    {
        return $this->sumByAccount(AccountTransaction::TYPE_CREDIT, now()->toDateString());
    }

    /**
     * @return Collection<int, float> keyed by account_id
     */
    public function todayDebit(): Collection
    {
        return $this->sumByAccount(AccountTransaction::TYPE_DEBIT, now()->toDateString());
    }

    /**
     * @return Collection<int, float> keyed by account_id — credits minus debits, all-time.
     */
    public function totalBalance(): Collection
    {
        $credits = $this->sumByAccount(AccountTransaction::TYPE_CREDIT);
        $debits = $this->sumByAccount(AccountTransaction::TYPE_DEBIT);

        return Account::query()->pluck('id')->mapWithKeys(fn (int $accountId) => [
            $accountId => (float) ($credits[$accountId] ?? 0) - (float) ($debits[$accountId] ?? 0),
        ]);
    }

    protected function sumByAccount(string $type, ?string $onDate = null): Collection
    {
        return AccountTransaction::query()
            ->where('type', $type)
            ->when($onDate, fn ($query) => $query->whereDate('transaction_date', $onDate))
            ->selectRaw('account_id, sum(amount) as aggregate')
            ->groupBy('account_id')
            ->pluck('aggregate', 'account_id')
            ->map(fn ($value) => (float) $value);
    }

    /**
     * Backfills transactions for delivered orders/expenses that predate
     * this feature — safe to re-run, firstOrCreate() skips anything
     * already recorded.
     */
    public function backfill(): void
    {
        Order::query()->where('status', 'delivered')->each(
            fn (Order $order) => $this->recordDeliveredOrderCredit($order)
        );

        Expense::query()->each(
            fn (Expense $expense) => $this->recordExpenseDebit($expense)
        );
    }
}
