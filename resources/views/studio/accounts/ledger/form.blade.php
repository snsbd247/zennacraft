@extends('layouts.studio')
@php $isCredit = $type === 'credit'; @endphp
@section('title', ($transaction->exists ? 'Edit ' : 'Add ').($isCredit ? 'Credit' : 'Debit'))
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route($isCredit ? 'accounts.income.index' : 'accounts.expense.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="zc-ac-title">{{ $transaction->exists ? 'Edit' : 'Add' }} {{ $isCredit ? 'Credit' : 'Debit' }}</h1>

        <form class="zc-cf" method="POST" action="{{ $transaction->exists ? route($isCredit ? 'accounts.income.update' : 'accounts.expense.update', $transaction) : route($isCredit ? 'accounts.income.store' : 'accounts.expense.store') }}" style="margin-top:1rem;">
            @csrf @if($transaction->exists) @method('PUT') @endif

            <div class="zc-cf-row"><label>Date <span class="req">*</span></label><input type="date" name="transaction_date" class="studio-form-control" value="{{ old('transaction_date', optional($transaction->transaction_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></div>

            <div class="zc-cf-row">
                <label>Purpose @unless($isCredit)<span class="req">*</span>@endunless</label>
                @if ($isCredit)
                    <input name="purpose" class="studio-form-control" value="{{ old('purpose', $transaction->purpose) }}" placeholder="e.g. Office sale">
                @else
                    <select name="account_purpose_id" class="studio-form-control" required>
                        <option value="">Select Purpose</option>
                        @foreach ($purposes as $p)<option value="{{ $p->id }}" @selected(old('account_purpose_id', $transaction->account_purpose_id) == $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                @endif
            </div>

            <div class="zc-cf-row"><label>Amount <span class="req">*</span></label><input type="number" step="0.01" min="0" name="amount" class="studio-form-control" value="{{ old('amount', $transaction->amount ? (float) $transaction->amount : '') }}" required></div>
            <div class="zc-cf-row"><label>Comment</label><input name="description" class="studio-form-control" value="{{ old('description', $transaction->description) }}" placeholder="optional note"></div>

            <div class="zc-cf-row">
                <label>{{ $isCredit ? 'Credit In' : 'Debit From' }} <span class="req">*</span></label>
                <select name="account_id" class="studio-form-control" required>
                    <option value="">Select Balance</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id', $transaction->account_id) == $a->id)>{{ $a->name }}</option>@endforeach
                </select>
            </div>

            <div style="text-align:center;margin-top:1.4rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@endsection
