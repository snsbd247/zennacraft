<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BalanceController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    public function index(): View
    {
        $accounts = Account::orderBy('sort_order')->orderBy('name')->get();
        $todayCredit = $this->accountService->todayCredit();
        $todayDebit = $this->accountService->todayDebit();
        $total = $this->accountService->totalBalance();

        return view('studio.accounts.balance.index', [
            'accounts' => $accounts,
            'todayCredit' => $todayCredit,
            'todayDebit' => $todayDebit,
            'total' => $total,
            'todayCreditTotal' => (float) $todayCredit->sum(),
            'todayDebitTotal' => (float) $todayDebit->sum(),
            'grandTotal' => (float) $total->sum(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:100']])['name'];
        Account::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'sort_order' => (int) Account::max('sort_order') + 1,
            'active' => true,
        ]);

        return back()->with('success', 'Balance channel added.');
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back()->with('success', 'Balance channel updated.');
    }

    public function toggle(Request $request, Account $account): JsonResponse|RedirectResponse
    {
        $account->update(['active' => ! $account->active]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'active' => (bool) $account->active])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, Account $account): JsonResponse|RedirectResponse
    {
        if ($account->transactions()->exists()) {
            $message = 'This channel has transactions and cannot be deleted. Disable it instead.';

            return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
        }
        $account->delete();

        return $request->expectsJson() ? response()->json(['message' => 'Channel deleted.']) : back()->with('success', 'Channel deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'account';
        $slug = $base;
        $i = 2;
        while (Account::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
