@extends('layouts.studio')
@section('title', 'Due')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-ac-switch">
        <a href="{{ route('accounts.income.create') }}" class="is-credit">+ Add Credit</a>
        <a href="{{ route('accounts.expense.create') }}" class="is-debit">+ Add Debit</a>
    </div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Due Table</h2>

        @php $money = fn ($v) => '৳'.number_format((float) $v, 0); $dueCount = $orders->total(); $avgDue = $dueCount > 0 ? ($totalDue ?? 0) / $dueCount : 0; @endphp
        @include('studio.partials.stat-strip')
        <div class="zc-stat-strip zc-stat-strip--3">
            <div class="zc-stat zc-stat--red">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
                <div><div class="zc-stat__v">{{ $money($totalDue ?? 0) }}</div><div class="zc-stat__l">Total due</div></div>
            </div>
            <div class="zc-stat zc-stat--amber">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg></span>
                <div><div class="zc-stat__v" data-countup>{{ number_format($dueCount) }}</div><div class="zc-stat__l">Orders with due</div></div>
            </div>
            <div class="zc-stat zc-stat--gold">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-8"/></svg></span>
                <div><div class="zc-stat__v">{{ $money($avgDue) }}</div><div class="zc-stat__l">Avg due / order</div></div>
            </div>
        </div>

        <form method="GET" class="zc-ac-filters" style="grid-template-columns:2fr 1fr;">
            <input type="text" name="q" class="studio-form-control" placeholder="Search by customer phone number" value="{{ request('q') }}">
            <select name="collect_account" class="studio-form-control" id="zc-due-acc" title="Collect into">
                @foreach ($accounts as $a)<option value="{{ $a->id }}">Collect in: {{ $a->name }}</option>@endforeach
            </select>
        </form>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Date</th><th>Customer Name</th><th>Mobile No</th><th>Sale Invoice</th><th>Amount</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($orders as $o)
                    @php $due = (float) $o->total - (float) $o->paid_amount; @endphp
                    <tr>
                        <td>{{ $loop->iteration + ($orders->firstItem() ? $orders->firstItem() - 1 : 0) }}</td>
                        <td style="white-space:nowrap;">{{ optional($o->created_at)->format('d-m-Y, H:i') }}</td>
                        <td class="zc-sm-name">{{ $o->customer_name }}</td>
                        <td>{{ $o->customer_phone }}</td>
                        <td><span style="font-weight:700;">{{ $o->order_number }}</span></td>
                        <td class="zc-money-d">{{ number_format($due) }}</td>
                        <td style="text-align:right;">
                            <form method="POST" action="{{ route('accounts.due.paid', $o) }}" style="display:inline;" class="zc-due-form">
                                @csrf
                                <input type="hidden" name="account_id" class="zc-due-acc-input">
                                <button type="submit" class="studio-command-button studio-command-button--primary" style="padding:0.4rem 1rem;" onclick="return confirm('Collect ৳{{ number_format($due) }} from this customer?')">Get Paid</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="zc-sm-empty">No customer dues. 🎉</td></tr>
                @endforelse
            </tbody>
            @if ($orders->isNotEmpty())
                <tfoot><tr><td colspan="5" style="text-align:right;font-weight:800;">Total Due</td><td class="zc-money-d" style="font-weight:800;">={{ number_format($totalDue) }}</td><td></td></tr></tfoot>
            @endif
        </table>
        @if ($orders->hasPages())<div class="zc-sm-pager">@if(!$orders->onFirstPage())<a href="{{ $orders->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>@if($orders->hasMorePages())<a href="{{ $orders->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of total {{ $orders->total() }} entries</div>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var acc=document.getElementById('zc-due-acc');
        function sync(){ document.querySelectorAll('.zc-due-acc-input').forEach(function(i){ i.value=acc ? acc.value : ''; }); }
        if(acc) acc.addEventListener('change', sync); sync();
    })();
</script>
@endpush
@endsection
