<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\AccountPurpose;
use App\Modules\Finance\Models\AccountTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The Income (credit) and Expense (debit) ledgers. Both read/write the shared
 * account_transactions table; $type ('credit'|'debit') comes from a route
 * default so one controller drives both pages. Channel balances are computed
 * from this ledger, so edits/deletes here always keep balances correct.
 */
class LedgerController extends Controller
{
    public function index(Request $request, string $type = 'credit'): mixed
    {
        $query = AccountTransaction::query()->with(['account', 'accountPurpose', 'staffUser'])
            ->where('type', $type)
            ->when($request->string('q')->trim()->value(), fn ($q, $t) => $q->where(fn ($w) => $w->where('purpose', 'like', "%{$t}%")->orWhere('description', 'like', "%{$t}%")))
            ->when($request->date('from'), fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d))
            ->when($request->integer('account_id'), fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->integer('account_purpose_id'), fn ($q, $id) => $q->where('account_purpose_id', $id))
            ->orderByDesc('transaction_date')->orderByDesc('id');

        if ($request->get('export') === 'csv') {
            return $this->exportCsv($query->get(), $type);
        }

        // Stable type-wide overview for the stat strip (independent of the
        // table's live AJAX filters, so it never shows a stale figure).
        $typeQuery = AccountTransaction::query()->where('type', $type);

        $rows = $query->paginate($request->integer('per_page') ?: 50)->withQueryString();
        $data = [
            'type' => $type,
            'rows' => $rows,
            'accounts' => $this->accounts(),
            'purposes' => AccountPurpose::orderBy('name')->get(),
            'typeTotal' => (float) (clone $typeQuery)->sum('amount'),
            'typeCount' => (int) (clone $typeQuery)->count(),
            'monthTotal' => (float) (clone $typeQuery)
                ->whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
        ];

        return $request->boolean('partial') ? view('studio.accounts.ledger._rows', $data) : view('studio.accounts.ledger.index', $data);
    }

    private function exportCsv($rows, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $isCredit = $type === 'credit';
        $filename = ($isCredit ? 'income' : 'expense').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $isCredit) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Invoice', 'Purpose', $isCredit ? 'Credit In' : 'Debit From', 'Amount', 'Comment', 'Inserted']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    optional($r->transaction_date)->format('Y-m-d'), $r->invoice, $r->purpose,
                    $r->account?->name, (float) $r->amount, $r->description, $r->staffUser?->name ?: 'System',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(string $type = 'credit'): View
    {
        return view('studio.accounts.ledger.form', [
            'type' => $type,
            'transaction' => new AccountTransaction(['type' => $type, 'transaction_date' => now()->toDateString()]),
            'accounts' => $this->accounts(),
            'purposes' => AccountPurpose::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $type = 'credit'): RedirectResponse
    {
        AccountTransaction::create($this->validated($request, $type));

        return redirect()->route($this->indexRoute($type))->with('success', ucfirst($type === 'credit' ? 'income' : 'expense').' recorded.');
    }

    public function edit(AccountTransaction $transaction): View
    {
        return view('studio.accounts.ledger.form', [
            'type' => $transaction->type,
            'transaction' => $transaction,
            'accounts' => $this->accounts(),
            'purposes' => AccountPurpose::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AccountTransaction $transaction): RedirectResponse
    {
        $transaction->update($this->validated($request, $transaction->type));

        return redirect()->route($this->indexRoute($transaction->type))->with('success', 'Record updated.');
    }

    public function destroy(Request $request, AccountTransaction $transaction): mixed
    {
        $type = $transaction->type;
        $transaction->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Record deleted.'])
            : redirect()->route($this->indexRoute($type))->with('success', 'Record deleted.');
    }

    private function validated(Request $request, string $type): array
    {
        $rules = [
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'account_id' => ['required', 'exists:accounts,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
        if ($type === 'debit') {
            $rules['account_purpose_id'] = ['required', 'exists:account_purposes,id'];
        } else {
            $rules['purpose'] = ['nullable', 'string', 'max:191'];
        }
        $data = $request->validate($rules);

        $data['type'] = $type;
        $data['staff_user_id'] = auth()->guard('staff')->id();
        if ($type === 'debit') {
            $data['purpose'] = AccountPurpose::whereKey($data['account_purpose_id'])->value('name');
        }

        return $data;
    }

    private function accounts()
    {
        return Account::where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function indexRoute(string $type): string
    {
        return $type === 'credit' ? 'accounts.income.index' : 'accounts.expense.index';
    }
}
