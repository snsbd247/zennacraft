<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FundTransfer;
use App\Modules\Finance\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class FundTransferController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    public function index(Request $request): View
    {
        return view('studio.accounts.transfer.index', [
            'transfers' => FundTransfer::with(['fromAccount', 'toAccount', 'staffUser'])
                ->orderByDesc('transfer_date')->orderByDesc('id')
                ->paginate($request->integer('per_page') ?: 50)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('studio.accounts.transfer.form', ['accounts' => $this->accounts()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'exists:accounts,id'],
            'to_account_id' => ['required', 'different:from_account_id', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:255'],
            'transfer_date' => ['nullable', 'date'],
        ]);
        $data['staff_user_id'] = auth()->guard('staff')->id();
        $this->accountService->transfer($data);

        return redirect()->route('accounts.transfer.index')->with('success', 'Balance transferred.');
    }

    public function destroy(Request $request, FundTransfer $transfer): JsonResponse|RedirectResponse
    {
        $transfer->delete(); // paired ledger entries cascade away

        return $request->expectsJson()
            ? response()->json(['message' => 'Transfer deleted.'])
            : back()->with('success', 'Transfer deleted.');
    }

    private function accounts()
    {
        return Account::where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }
}
