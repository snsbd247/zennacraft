@extends('layouts.studio')
@section('title', 'Landing Pages')
@section('subtitle', 'Landing Page')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-lp-link{display:inline-flex;align-items:center;gap:6px;color:var(--studio-accent);font-weight:600;font-size:0.85rem;text-decoration:none;word-break:break-all;}
    .zc-lp-link:hover{text-decoration:underline;}
    .zc-lp-tpl{display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);font-size:0.72rem;font-weight:700;color:var(--studio-muted);margin-left:8px;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <a href="{{ route('landing.create') }}" class="studio-command-button studio-command-button--primary">+ Add New Landing Page</a>
        <form method="POST" action="{{ route('landing.auto-create') }}" style="display:inline-flex;align-items:center;gap:0.6rem;">
            @csrf
            <span style="font-size:0.82rem;font-weight:700;color:var(--studio-muted);">Auto-create a landing page for every new product</span>
            <input type="hidden" name="auto_create" value="0">
            <label class="zc-lp-switch" title="Toggle auto-create">
                <input type="checkbox" name="auto_create" value="1" onchange="this.form.submit()" @checked($autoCreate ?? true) style="position:absolute;opacity:0;width:0;height:0;">
                <span class="zc-lp-switch__track" style="width:44px;height:24px;border-radius:999px;background:{{ ($autoCreate ?? true) ? '#1c8a4e' : '#c9ccce' }};position:relative;display:inline-block;transition:background .18s;">
                    <span style="position:absolute;top:3px;left:{{ ($autoCreate ?? true) ? '23px' : '3px' }};width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .18s;"></span>
                </span>
                <b style="font-size:0.78rem;color:{{ ($autoCreate ?? true) ? '#1c8a4e' : 'var(--studio-muted)' }};min-width:24px;">{{ ($autoCreate ?? true) ? 'On' : 'Off' }}</b>
            </label>
        </form>
    </div>

    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Landing page Table</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Landing Page Name</th><th>Landing Page Link</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr>
                        <td>{{ $loop->iteration + ($pages->firstItem() ? $pages->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $page->title }}<span class="zc-lp-tpl">{{ $page->templateMeta()['label'] }}</span></td>
                        <td><a href="{{ url('/'.$page->slug) }}" target="_blank" class="zc-lp-link"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>{{ $page->slug }}</a></td>
                        <td><span class="zc-sm-pill zc-lp-status {{ $page->isActive() ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $page->isActive() ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('landing.edit', $page) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('landing.toggle', $page) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('landing.destroy', $page) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No landing pages yet. Click <b>+ Add New Landing Page</b> and pick a template.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($pages->hasPages())<div class="zc-sm-pager">@if(!$pages->onFirstPage())<a href="{{ $pages->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $pages->currentPage() }} / {{ $pages->lastPage() }}</span>@if($pages->hasMorePages())<a href="{{ $pages->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
<div class="zc-cat-toast" id="zc-lp-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-lp-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-lp-status'); var on=res.d.status==='active'; p.textContent=on?'Active':'Inactive'; p.className='zc-sm-pill zc-lp-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this landing page?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
        });
    })();
</script>
@endpush
@endsection
