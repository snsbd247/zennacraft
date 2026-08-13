@extends('layouts.studio')
@section('title', 'Offer Banner')
@section('subtitle', 'Campaign / Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-ob-thumb{width:120px;height:52px;border-radius:8px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);}
    .zc-ob-place{display:inline-flex;align-items:center;padding:0.25rem 0.75rem;border-radius:999px;background:rgba(59,110,165,0.12);color:#2b567f;font-size:0.74rem;font-weight:800;font-family:ui-monospace,Menlo,monospace;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif

    <div class="zc-sm-head">
        <a href="{{ route('offer-banners.create') }}" class="studio-command-button studio-command-button--primary">+ Add Banner</a>
        <div style="flex:1;text-align:center;">
            <h1 class="studio-section-title" style="justify-content:center;">Banners</h1>
            <div style="font-size:0.8rem;color:var(--studio-muted);margin-top:2px;">Promotional banners shown in your storefront's offer slots — each row shows exactly <b>where</b> it appears.</div>
        </div>
        <div style="width:130px;"></div>
    </div>

    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Image</th><th>Banner Placement</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($banners as $banner)
                    <tr>
                        <td>{{ $loop->iteration + ($banners->firstItem() ? $banners->firstItem() - 1 : 0) }}</td>
                        <td>
                            @php $img = $mediaUrl($banner->image); @endphp
                            @if ($img)<img src="{{ $img }}" alt="" class="zc-ob-thumb">
                            @else<span class="zc-ob-thumb" style="display:grid;place-items:center;color:var(--studio-muted);"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/></svg></span>@endif
                        </td>
                        <td><span class="zc-ob-place">{{ $banner->placement_label }}</span></td>
                        <td><span class="zc-sm-pill zc-ob-status {{ $banner->active ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $banner->active ? 'active' : 'inactive' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('offer-banners.edit', $banner) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('offer-banners.toggle', $banner) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('offer-banners.destroy', $banner) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No offer banners yet. Click <b>+ Add Banner</b> to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($banners->hasPages())<div class="zc-sm-pager">@if(!$banners->onFirstPage())<a href="{{ $banners->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $banners->currentPage() }} / {{ $banners->lastPage() }}</span>@if($banners->hasMorePages())<a href="{{ $banners->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
<div class="zc-cat-toast" id="zc-ob-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-ob-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2400); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-ob-status'); var on=res.d.active; p.textContent=on?'active':'inactive'; p.className='zc-sm-pill zc-ob-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast('Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this banner?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ var tr=del.closest('tr'); tr.style.transition='opacity .2s'; tr.style.opacity='0'; setTimeout(function(){tr.remove();},200); showToast(res.d.message||'Deleted'); } else showToast('Failed',true); }); }
        });
    })();
</script>
@endpush
@endsection
