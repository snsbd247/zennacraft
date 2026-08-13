@extends('layouts.studio')
@section('title', 'Fund Transfer')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('accounts.transfer.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="zc-ac-title">Fund Transfer</h1>
        <form class="zc-cf" method="POST" action="{{ route('accounts.transfer.store') }}" style="margin-top:1rem;">
            @csrf
            <div class="zc-cf-row"><label>From <span class="req">*</span></label>
                <select name="from_account_id" class="studio-form-control" required>
                    <option value="">Select Balance</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('from_account_id') == $a->id)>{{ $a->name }}</option>@endforeach
                </select>
            </div>
            <div class="zc-cf-row"><label>To <span class="req">*</span></label>
                <select name="to_account_id" class="studio-form-control" required>
                    <option value="">Select Balance</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('to_account_id') == $a->id)>{{ $a->name }}</option>@endforeach
                </select>
            </div>
            <div class="zc-cf-row"><label>Amount <span class="req">*</span></label><input type="number" step="0.01" min="0" name="amount" id="zc-tr-amount" class="studio-form-control" value="{{ old('amount', 0) }}" required></div>
            <div class="zc-cf-row"><label>Cost</label><input type="number" step="0.01" min="0" name="cost" id="zc-tr-cost" class="studio-form-control" value="{{ old('cost', 0) }}"></div>
            <div class="zc-cf-row"><label>Transfer Amount <small style="display:block;font-weight:400;color:var(--studio-muted);">(amount - cost)</small></label><input type="text" id="zc-tr-net" class="studio-form-control" value="0" readonly style="background:var(--studio-surface-soft);"></div>
            <div class="zc-cf-row"><label>Comment</label><input name="comment" class="studio-form-control" value="{{ old('comment') }}" placeholder="comment"></div>
            <div style="text-align:center;margin-top:1.4rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var amt=document.getElementById('zc-tr-amount'), cost=document.getElementById('zc-tr-cost'), net=document.getElementById('zc-tr-net');
        function calc(){ var n=(parseFloat(amt.value)||0)-(parseFloat(cost.value)||0); net.value=(Math.round(n*100)/100); }
        amt.addEventListener('input',calc); cost.addEventListener('input',calc); calc();
    })();
</script>
@endpush
@endsection
