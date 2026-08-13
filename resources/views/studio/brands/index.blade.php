@extends('layouts.studio')
@section('title', 'Brands')
@section('subtitle', 'Brand')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-br-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;}
    .zc-br-head h1{margin:0;}
    .zc-br-search{position:relative;margin-left:auto;min-width:min(340px,100%);}
    .zc-br-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--studio-muted);pointer-events:none;}
    .zc-br-search input{width:100%;padding:0.7rem 1rem 0.7rem 2.6rem;}
    .zc-br-logo{width:74px;height:52px;border-radius:10px;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:grid;place-items:center;overflow:hidden;font-weight:800;font-size:0.8rem;color:var(--studio-muted);}
    .zc-br-logo img{width:100%;height:100%;object-fit:contain;padding:4px;}
    .zc-br-status{min-width:74px;text-align:center;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
    #zc-br-body.is-loading{opacity:.45;transition:opacity .15s;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div>
        <a href="{{ route('brands.create') }}" class="studio-command-button studio-command-button--primary">+ Brand Add</a>
    </div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <div class="zc-br-head">
            <h1 class="studio-section-title">Brands</h1>
            <div class="zc-br-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="zc-br-q" class="studio-form-control" placeholder="Enter Brand name" autocomplete="off" value="{{ request('q') }}">
            </div>
        </div>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th>Position</th><th>Image</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody id="zc-br-body">@include('studio.brands._rows')</tbody>
        </table>

        @if ($brands->hasPages())<div class="zc-sm-pager">@if(!$brands->onFirstPage())<a href="{{ $brands->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $brands->currentPage() }} / {{ $brands->lastPage() }}</span>@if($brands->hasMorePages())<a href="{{ $brands->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $brands->firstItem() ?? 0 }} to {{ $brands->lastItem() ?? 0 }} of total {{ $brands->total() }} entries</div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-br-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        var toast = document.getElementById('zc-br-toast');
        var body = document.getElementById('zc-br-body');
        var q = document.getElementById('zc-br-q');
        function showToast(msg, err){ toast.textContent = msg; toast.classList.toggle('err', !!err); toast.classList.add('show'); setTimeout(function(){ toast.classList.remove('show'); }, 2600); }
        function ajax(url, method){ return fetch(url, { method: method, headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } }).then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); }); }

        // AJAX live search (no full reload) per the project directive.
        var t = null;
        q.addEventListener('input', function(){
            clearTimeout(t);
            t = setTimeout(function(){
                body.classList.add('is-loading');
                fetch('{{ route('brands.index') }}?partial=1&q=' + encodeURIComponent(q.value), { headers: { 'X-Requested-With':'XMLHttpRequest' } })
                    .then(function(r){ return r.text(); })
                    .then(function(html){ body.innerHTML = html; body.classList.remove('is-loading'); });
            }, 250);
        });

        document.addEventListener('click', function(e){
            var tog = e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var pill=tog.closest('tr').querySelector('.zc-br-status'); var on=res.d.status==='active'; pill.textContent=on?'Active':'De-active'; pill.className='zc-sm-pill zc-br-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed', true); }); return; }
            var del = e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this brand?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed', true); }); return; }
        });
    })();
</script>
@endpush
@endsection
