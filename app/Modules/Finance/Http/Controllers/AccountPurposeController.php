<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\AccountPurpose;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountPurposeController extends Controller
{
    public function index(): View
    {
        return view('studio.accounts.purpose.index', ['purposes' => AccountPurpose::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('studio.accounts.purpose.form', ['purpose' => new AccountPurpose(['type' => 'not_expense'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        AccountPurpose::create($this->validated($request));

        return redirect()->route('accounts.purpose.index')->with('success', 'Purpose added.');
    }

    public function edit(AccountPurpose $purpose): View
    {
        return view('studio.accounts.purpose.form', ['purpose' => $purpose]);
    }

    public function update(Request $request, AccountPurpose $purpose): RedirectResponse
    {
        $purpose->update($this->validated($request));

        return redirect()->route('accounts.purpose.index')->with('success', 'Purpose updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(AccountPurpose::TYPES))],
        ]);
    }
}
