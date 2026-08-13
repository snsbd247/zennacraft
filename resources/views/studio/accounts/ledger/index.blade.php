@extends('layouts.studio')
@php $isCredit = $type === 'credit'; @endphp
@section('title', $isCredit ? 'Income' : 'Expense')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-ac-switch">
        <a href="{{ route('accounts.income.create') }}" class="is-credit">+ Add Credit</a>
        <a href="{{ route('accounts.expense.create') }}" class="is-debit">+ Add Debit</a>
        <a href="{{ route($isCredit ? 'accounts.expense.index' : 'accounts.income.index') }}" class="is-ghost">{{ $isCredit ? 'Debit' : 'Credit' }}</a>
    </div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h1 class="zc-ac-title">{{ $isCredit ? 'Credit/Income Records' : 'Debit/Expense' }}</h1>

        @php $money = fn ($v) => '৳'.number_format((float) $v, 0); $avg = ($typeCount ?? 0) > 0 ? ($typeTotal ?? 0) / $typeCount : 0; @endphp
        @include('studio.partials.stat-strip')
        <div class="zc-stat-strip">
            <div class="zc-stat {{ $isCredit ? 'zc-stat--green' : 'zc-stat--red' }}">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M17 8a4 4 0 0 0-4-3H10a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-3a4 4 0 0 1-4-3"/></svg></span>
                <div><div class="zc-stat__v">{{ $money($typeTotal ?? 0) }}</div><div class="zc-stat__l">Total {{ $isCredit ? 'income' : 'expense' }}</div></div>
            </div>
            <div class="zc-stat zc-stat--blue">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/></svg></span>
                <div><div class="zc-stat__v">{{ $money($monthTotal ?? 0) }}</div><div class="zc-stat__l">This month</div></div>
            </div>
            <div class="zc-stat zc-stat--amber">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-8"/></svg></span>
                <div><div class="zc-stat__v" data-countup>{{ number_format($typeCount ?? 0) }}</div><div class="zc-stat__l">Records</div></div>
            </div>
            <div class="zc-stat zc-stat--gold">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg></span>
                <div><div class="zc-stat__v">{{ $money($avg) }}</div><div class="zc-stat__l">Avg / record</div></div>
            </div>
        </div>

        <form class="zc-ac-filters" id="zc-ac-filter" onsubmit="return false;">
            <input type="text" name="q" class="studio-form-control" placeholder="purpose,comments...." value="{{ request('q') }}">
            <input type="date" name="from" class="studio-form-control" value="{{ request('from') }}">
            <input type="date" name="to" class="studio-form-control" value="{{ request('to') }}">
            @unless ($isCredit)
                <select name="account_purpose_id" class="studio-form-control">
                    <option value="">Account Purpose</option>
                    @foreach ($purposes as $p)<option value="{{ $p->id }}" @selected(request('account_purpose_id') == $p->id)>{{ $p->name }}</option>@endforeach
                </select>
            @endunless
            <select name="account_id" class="studio-form-control">
                <option value="">{{ $isCredit ? 'Select One' : 'Select Balance' }}</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <select name="per_page" class="studio-form-control">
                @foreach ([25, 50, 100, 200] as $n)<option value="{{ $n }}" @selected(request('per_page', 50) == $n)>{{ $n }}</option>@endforeach
            </select>
            <div class="zc-ac-tools">
                <a href="#" class="t-pdf" onclick="window.print();return false;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4v-6h16v6h-2M8 14h8v8H8z"/></svg> PDF</a>
                <button type="button" class="t-csv" id="zc-ac-csv"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg> CSV</button>
                <button type="button" class="t-reset" id="zc-ac-reset" title="Reset"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6M3 4v4h4"/></svg></button>
            </div>
        </form>

        <div style="overflow-x:auto;">
            <table class="zc-sm-tbl">
                <thead><tr>
                    <th>#</th><th>Date</th>@if ($isCredit)<th>Invoice</th>@endif<th>Purpose</th><th>{{ $isCredit ? 'Credit In' : 'Debit From' }}</th><th>Amount</th><th>Comment</th><th>Inserted</th><th>Action</th>
                </tr></thead>
                <tbody id="zc-ac-body">@include('studio.accounts.ledger._rows')</tbody>
            </table>
        </div>

        @if ($rows->hasPages())<div class="zc-sm-pager">@if(!$rows->onFirstPage())<a href="{{ $rows->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of total {{ $rows->total() }} entries</div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-ac-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-ac-toast'), body=document.getElementById('zc-ac-body'), form=document.getElementById('zc-ac-filter');
        var base='{{ route($isCredit ? 'accounts.income.index' : 'accounts.expense.index') }}';
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function params(){ return new URLSearchParams(new FormData(form)).toString(); }
        var t=null;
        function refresh(){ body.style.opacity=.45; fetch(base+'?partial=1&'+params(),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.text();}).then(function(h){ body.innerHTML=h; body.style.opacity=1; }); }
        form.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(refresh,300); });
        document.getElementById('zc-ac-reset').addEventListener('click', function(){ form.reset(); refresh(); });
        document.getElementById('zc-ac-csv').addEventListener('click', function(){ window.location=base+'?'+params()+'&export=csv'; });
        document.addEventListener('click', function(e){
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this record? Channel balance will adjust.')) return;
                fetch(del.getAttribute('data-delete'),{method:'DELETE',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}).then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); }
        });
    })();
</script>
@endpush
@endsection
