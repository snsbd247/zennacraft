<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\BillStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BillStatementController extends Controller
{
    public function index(): View
    {
        return view('studio.accounts.bill.index', ['bills' => BillStatement::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('studio.accounts.bill.form', ['bill' => new BillStatement(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        BillStatement::create($this->validated($request));

        return redirect()->route('accounts.bill.index')->with('success', 'Bill added.');
    }

    public function edit(BillStatement $bill): View
    {
        return view('studio.accounts.bill.form', ['bill' => $bill]);
    }

    public function update(Request $request, BillStatement $bill): RedirectResponse
    {
        $bill->update($this->validated($request));

        return redirect()->route('accounts.bill.index')->with('success', 'Bill updated.');
    }

    public function toggle(Request $request, BillStatement $bill): JsonResponse|RedirectResponse
    {
        $bill->update(['status' => $bill->isActive() ? 'inactive' : 'active']);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'status' => $bill->status])
            : back()->with('success', 'Status updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
