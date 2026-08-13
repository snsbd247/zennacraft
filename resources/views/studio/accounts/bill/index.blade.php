@extends('layouts.studio')
@section('title', 'Bill Statement')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('accounts.bill.create') }}" class="studio-command-button studio-command-button--primary">+ Add</a></div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Bill Statement</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Bill Name</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($bills as $b)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="zc-sm-name">{{ $b->name }} @unless($b->isActive())<span class="zc-sm-pill zc-sm-pill--off" style="margin-left:8px;">Disabled</span>@endunless</td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('accounts.bill.edit', $b) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-bill-status {{ $b->isActive() ? 'zc-sm-btn--tog' : 'zc-sm-btn--del' }}" title="Enable/Disable" data-toggle="{{ route('accounts.bill.toggle', $b) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 13l4 4L19 7"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="zc-sm-empty">No bills yet. Click <b>+ Add</b>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="zc-cat-toast" id="zc-bill-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-bill-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ fetch(tog.getAttribute('data-toggle'),{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}).then(function(res){ if(res.ok){ location.reload(); } else showToast(res.d.message||'Failed',true); }); }
        });
    })();
</script>
@endpush
@endsection
