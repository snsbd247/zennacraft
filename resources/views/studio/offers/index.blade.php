@extends('layouts.studio')
@section('title', 'Offers')
@section('subtitle', 'Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-of-where{display:inline-flex;align-items:center;gap:6px;padding:0.25rem 0.7rem;border-radius:999px;background:rgba(242,162,12,0.12);color:#8a5a00;font-size:0.72rem;font-weight:800;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route('offers.create') }}" class="studio-command-button studio-command-button--primary">+ Add Offer</a>
        <div style="flex:1;text-align:center;">
            <h1 class="studio-section-title" style="justify-content:center;">Offers</h1>
            <div style="font-size:0.8rem;color:var(--studio-muted);margin-top:2px;">Each offer shows <b>where</b> it appears on your storefront, so you know exactly which spot you're setting.</div>
        </div>
        <div style="width:120px;"></div>
    </div>

    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th>Shows where</th><th>Unlock at</th><th>Reward</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($offers as $offer)
                    <tr>
                        <td>{{ $loop->iteration + ($offers->firstItem() ? $offers->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $offer->name }}</td>
                        <td><span class="zc-of-where">{{ \App\Modules\Promotion\Models\Offer::placementMeta($offer->placement)['where'] }}</span></td>
                        <td style="font-weight:700;">৳{{ number_format((float) $offer->threshold_amount) }}</td>
                        <td style="color:var(--studio-muted);">{{ $offer->reward_text ?: ($offer->rewardProduct?->name ?: '—') }}</td>
                        <td><span class="zc-sm-pill zc-of-status {{ $offer->active ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $offer->active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('offers.edit', $offer) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('offers.toggle', $offer) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('offers.destroy', $offer) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="zc-sm-empty">No offers yet. Click <b>+ Add Offer</b> to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($offers->hasPages())<div class="zc-sm-pager">@if(!$offers->onFirstPage())<a href="{{ $offers->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $offers->currentPage() }} / {{ $offers->lastPage() }}</span>@if($offers->hasMorePages())<a href="{{ $offers->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
<div class="zc-cat-toast" id="zc-of-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-of-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-of-status'); var on=res.d.active; p.textContent=on?'Active':'Inactive'; p.className='zc-sm-pill zc-of-status '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this offer?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
        });
    })();
</script>
@endpush
@endsection
