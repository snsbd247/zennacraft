@extends('layouts.studio')
@section('title', 'Fund Transfer')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('accounts.transfer.create') }}" class="studio-command-button studio-command-button--primary">+ Add Balance Transfer</a></div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Balance Transfer</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Date</th><th>From</th><th>To</th><th>Amount</th><th>Cost(%)</th><th>Comment</th><th>Create By</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($transfers as $t)
                    <tr>
                        <td>{{ $loop->iteration + ($transfers->firstItem() ? $transfers->firstItem() - 1 : 0) }}</td>
                        <td style="white-space:nowrap;">{{ optional($t->transfer_date)->format('Y-m-d') }}</td>
                        <td>{{ $t->fromAccount?->name }}</td>
                        <td>{{ $t->toAccount?->name }}</td>
                        <td class="zc-money-c">{{ number_format((float) $t->amount) }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $t->cost, 2), '0'), '.') }}</td>
                        <td style="color:var(--studio-muted);">{{ $t->comment }}</td>
                        <td>{{ $t->staffUser?->name ?: 'System' }}</td>
                        <td><div class="zc-sm-act"><button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('accounts.transfer.destroy', $t) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="zc-sm-empty">No transfers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($transfers->hasPages())<div class="zc-sm-pager">@if(!$transfers->onFirstPage())<a href="{{ $transfers->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $transfers->currentPage() }} / {{ $transfers->lastPage() }}</span>@if($transfers->hasMorePages())<a href="{{ $transfers->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $transfers->firstItem() ?? 0 }} to {{ $transfers->lastItem() ?? 0 }} of total {{ $transfers->total() }} entries</div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-tr-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-tr-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        document.addEventListener('click', function(e){
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this transfer? Both channel balances will revert.')) return;
                fetch(del.getAttribute('data-delete'),{method:'DELETE',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}).then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); }
        });
    })();
</script>
@endpush
@endsection
