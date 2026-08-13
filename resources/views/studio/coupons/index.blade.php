@extends('layouts.studio')
@section('title', 'Coupons')
@section('subtitle', 'Coupon')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cp-code{font-weight:800;letter-spacing:.05em;font-family:ui-monospace,Menlo,monospace;background:var(--studio-surface-soft);border:1px dashed var(--studio-border);border-radius:6px;padding:2px 8px;display:inline-block;}
    .zc-cp-search{position:relative;margin-left:auto;min-width:min(320px,100%);}
    .zc-cp-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--studio-muted);}
    .zc-cp-search input{width:100%;padding-left:2.6rem;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('coupons.create') }}" class="studio-command-button studio-command-button--primary">+ Add Coupon</a></div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <div class="zc-sm-head" style="margin-bottom:1rem;">
            <h1 class="studio-section-title">Coupons</h1>
            <div class="zc-cp-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="zc-cp-q" class="studio-form-control" placeholder="Search code or name" autocomplete="off" value="{{ request('q') }}">
            </div>
        </div>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Code</th><th>Type</th><th>Value</th><th>Min order</th><th>Used</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody id="zc-cp-body">@include('studio.coupons._rows')</tbody>
        </table>
        @if ($coupons->hasPages())<div class="zc-sm-pager">@if(!$coupons->onFirstPage())<a href="{{ $coupons->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $coupons->currentPage() }} / {{ $coupons->lastPage() }}</span>@if($coupons->hasMorePages())<a href="{{ $coupons->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $coupons->firstItem() ?? 0 }} to {{ $coupons->lastItem() ?? 0 }} of total {{ $coupons->total() }} coupons</div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-cp-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-cp-toast'), body=document.getElementById('zc-cp-body'), q=document.getElementById('zc-cp-q');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2800); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        var t=null;
        q.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(function(){ body.style.opacity=.45;
            fetch('{{ route('coupons.index') }}?partial=1&q='+encodeURIComponent(q.value),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.text();}).then(function(h){ body.innerHTML=h; body.style.opacity=1; }); }, 250); });
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-cp-status'); var on=res.d.status==='active'; p.textContent=on?'Active':'Inactive'; p.className='zc-sm-pill zc-cp-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this coupon?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
        });
    })();
</script>
@endpush
@endsection
