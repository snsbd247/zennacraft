@extends('layouts.studio')
@section('title', $title)
@section('subtitle', 'Category')
@php
    $levels = [
        'main' => ['label' => 'Category', 'route' => 'categories.main.index'],
        'sub' => ['label' => 'Sub Category', 'route' => 'categories.sub.index'],
        'subsub' => ['label' => 'Sub Sub Category', 'route' => 'categories.subsub.index'],
    ];
@endphp
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cat-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.1rem;}
    .zc-cat-search{max-width:22rem;}
    .zc-cat-thumb{width:52px;height:52px;border-radius:10px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:grid;place-items:center;color:var(--studio-muted);font-size:0.6rem;font-weight:800;text-align:center;}
    .zc-cat-btn-disc{padding:0.35rem 0.7rem;border-radius:8px;border:1px solid #cfe6d7;background:rgba(52,199,123,0.12);color:#1c8a4e;font-size:0.72rem;font-weight:800;cursor:pointer;}
    .zc-cat-btn-disc:hover{background:rgba(52,199,123,0.2);}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
    .zc-modal{position:fixed;inset:0;z-index:110;display:none;place-items:center;padding:1rem;} .zc-modal.open{display:grid;}
    .zc-modal__scrim{position:absolute;inset:0;background:rgba(16,24,40,.5);}
    .zc-modal__box{position:relative;z-index:2;width:min(380px,94vw);background:var(--studio-surface);border-radius:16px;box-shadow:0 30px 70px -30px rgba(16,24,40,.5);padding:1.5rem;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <div class="zc-sm-actions">
            <a href="{{ route('categories.'.$level.'.create') }}" class="studio-command-button studio-command-button--primary">+ Add {{ $singular }}</a>
            @foreach ($levels as $lv => $meta)@if ($lv !== $level)<a href="{{ route($meta['route']) }}" class="studio-command-button">{{ $meta['label'] }}</a>@endif @endforeach
        </div>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">{{ $title }}</h1>
        <form method="GET" action="{{ route('categories.'.$level.'.index') }}" class="zc-cat-search"><input name="q" value="{{ $search }}" class="studio-form-control" placeholder="Enter {{ strtolower($singular) }} name" onchange="this.form.submit()"></form>
    </div>

    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr>
                <th>#</th><th>Name</th>
                @if ($hasParent)<th>{{ $parentLabel }}</th>@endif
                <th>Image</th>
                @if (! $hasParent)<th>Position</th>@endif
                <th>Status</th>
                @if ($isSub)<th>Discount</th><th>Merchant Commission</th>@endif
                <th>Action</th>
            </tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $loop->iteration + ($categories->firstItem() ? $categories->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $category->name }}</td>
                        @if ($hasParent)<td>{{ $category->parent->name ?? '—' }}</td>@endif
                        <td>
                            @php $img = $mediaUrl($category->image); @endphp
                            <span class="zc-cat-thumb">@if ($img)<img src="{{ $img }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">@else NO IMAGE @endif</span>
                        </td>
                        @if (! $hasParent)<td style="font-weight:700;">{{ $category->sort_order }}</td>@endif
                        <td><span class="zc-sm-pill zc-cat-status {{ $category->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $category->status }}</span></td>
                        @if ($isSub)
                            <td><span class="zc-cat-disc">{{ rtrim(rtrim(number_format((float) $category->discount_percent, 2), '0'), '.') }} %</span></td>
                            <td>{{ rtrim(rtrim(number_format((float) $category->merchant_commission, 2), '0'), '.') }}</td>
                        @endif
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('categories.'.$level.'.show', $category) }}" class="zc-sm-btn zc-sm-btn--view" title="View products"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <a href="{{ route('categories.'.$level.'.edit', $category) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('categories.toggle', $category) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                @if ($isSub)<button type="button" class="zc-cat-btn-disc" data-discount="{{ route('categories.discount', $category) }}" data-current="{{ (float) $category->discount_percent }}">apply discount</button>@endif
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('categories.destroy', $category) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="zc-sm-empty">No {{ strtolower($title) }} yet. Click <b>+ Add {{ $singular }}</b> to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($categories->hasPages())<div class="zc-sm-pager">@if(!$categories->onFirstPage())<a href="{{ $categories->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $categories->currentPage() }} / {{ $categories->lastPage() }}</span>@if($categories->hasMorePages())<a href="{{ $categories->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $categories->firstItem() ?? 0 }} to {{ $categories->lastItem() ?? 0 }} of total {{ $categories->total() }} entries</div>
    </div>
</div>

<div class="zc-modal" id="zc-cat-disc-modal">
    <div class="zc-modal__scrim" data-disc-close></div>
    <div class="zc-modal__box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;"><h2 class="studio-section-title" style="font-size:1.05rem;">Apply Discount</h2><button type="button" data-disc-close style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--studio-muted);">&times;</button></div>
        <div class="zc-sm-field"><label>Discount %</label><input type="number" id="zc-disc-input" min="0" max="100" step="0.01" class="studio-form-control"></div>
        <button type="button" id="zc-disc-save" class="studio-command-button studio-command-button--primary">Apply</button>
    </div>
</div>
<div class="zc-cat-toast" id="zc-cat-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        var toast = document.getElementById('zc-cat-toast');
        function showToast(msg, err){ toast.textContent = msg; toast.classList.toggle('err', !!err); toast.classList.add('show'); setTimeout(function(){ toast.classList.remove('show'); }, 2600); }
        function ajax(url, method){ return fetch(url, { method: method, headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } }).then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); }); }

        document.addEventListener('click', function(e){
            var tog = e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var pill=tog.closest('tr').querySelector('.zc-cat-status'); pill.textContent=res.d.status; pill.className='zc-sm-pill zc-cat-status '+(res.d.status==='active'?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed', true); }); return; }
            var del = e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this category?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed', true); }); return; }
        });

        // Apply-discount modal (AJAX, no reload)
        var modal=document.getElementById('zc-cat-disc-modal'), input=document.getElementById('zc-disc-input');
        var activeBtn=null;
        document.addEventListener('click', function(e){
            var d=e.target.closest('[data-discount]'); if(d){ activeBtn=d; input.value=d.getAttribute('data-current')||0; modal.classList.add('open'); input.focus(); } });
        document.querySelectorAll('[data-disc-close]').forEach(function(b){ b.addEventListener('click', function(){ modal.classList.remove('open'); }); });
        document.getElementById('zc-disc-save').addEventListener('click', function(){
            if(!activeBtn) return;
            fetch(activeBtn.getAttribute('data-discount'), { method:'POST', headers:{ 'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded' }, body:'discount_percent='+encodeURIComponent(input.value) })
                .then(function(r){ return r.json().then(function(d){ return {ok:r.ok,d:d}; }); })
                .then(function(res){ if(res.ok){ var cell=activeBtn.closest('tr').querySelector('.zc-cat-disc'); if(cell) cell.textContent=(res.d.discount_percent%1?res.d.discount_percent:parseInt(res.d.discount_percent))+' %'; activeBtn.setAttribute('data-current',res.d.discount_percent); modal.classList.remove('open'); showToast(res.d.message);} else showToast((res.d.errors&&res.d.errors.discount_percent&&res.d.errors.discount_percent[0])||res.d.message||'Failed', true); });
        });
    })();
</script>
@endpush
@endsection
