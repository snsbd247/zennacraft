@extends('layouts.studio')
@section('title', ($offer->exists ? 'Edit' : 'Add').' Offer')
@section('subtitle', 'Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:760px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:180px 1fr;gap:1.1rem;align-items:start;margin-bottom:1.05rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.85rem;padding-top:0.6rem;color:var(--studio-text);}
    .zc-cf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;}
    @media(max-width:640px){.zc-cf-row{grid-template-columns:1fr;gap:0.35rem;}.zc-cf-row>label{padding-top:0;}}
    .zc-cf .req{color:#e0483d;font-weight:800;}
    .zc-of-hint{padding:0.7rem 0.9rem;border-radius:10px;background:rgba(242,162,12,0.1);border:1px solid rgba(242,162,12,0.3);color:#8a5a00;font-size:0.8rem;font-weight:600;margin-top:0.5rem;}
    .zc-cf-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    @media(max-width:640px){.zc-cf-2{grid-template-columns:1fr;}}
    .zc-cf-check{display:inline-flex;align-items:center;gap:0.6rem;font-weight:700;cursor:pointer;}
    .zc-cf-check input{width:1.15rem;height:1.15rem;accent-color:var(--studio-accent);}
</style>@endpush
@section('content')
@php $placements = \App\Modules\Promotion\Models\Offer::PLACEMENTS; @endphp
<div class="space-y-4">
    <a href="{{ route('offers.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $offer->exists ? 'Edit' : 'Add' }} Offer</h1>

        <form class="zc-cf" method="POST" action="{{ $offer->exists ? route('offers.update', $offer) : route('offers.store') }}" style="margin-top:1rem;">
            @csrf @if($offer->exists) @method('PUT') @endif

            <div class="zc-cf-row"><label>Offer Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $offer->name) }}" placeholder="e.g. Free gift over ৳3000" required></div>

            <div class="zc-cf-row">
                <label>Where it shows <span class="req">*</span></label>
                <div>
                    <select name="placement" class="studio-form-control" id="zc-of-placement">
                        @foreach ($placements as $val => $meta)
                            <option value="{{ $val }}" @selected(old('placement', $offer->placement) === $val) data-desc="{{ $meta['desc'] }}">{{ $meta['label'] }} — {{ $meta['where'] }}</option>
                        @endforeach
                    </select>
                    <div class="zc-of-hint" id="zc-of-hint">{{ $placements[old('placement', $offer->placement ?? 'cart_free_gift')]['desc'] ?? '' }}</div>
                </div>
            </div>

            <div class="zc-cf-row">
                <label>Rules</label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Unlock at (৳ subtotal)</label><input type="number" step="0.01" min="0" name="threshold_amount" class="studio-form-control" value="{{ old('threshold_amount', (float) $offer->threshold_amount ?: '') }}" placeholder="3000"></div>
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Position</label><input type="number" min="0" name="sort_order" class="studio-form-control" value="{{ old('sort_order', $offer->sort_order ?? 0) }}"></div>
                </div>
            </div>

            <div class="zc-cf-row"><label>Reward Text <small>shown in the bar</small></label><input name="reward_text" class="studio-form-control" value="{{ old('reward_text', $offer->reward_text) }}" placeholder="e.g. Free gift item"></div>

            <div class="zc-cf-row">
                <label>Reward Product <small>optional — link the free item</small></label>
                <select name="reward_product_id" class="studio-form-control">
                    <option value="">— none —</option>
                    @foreach ($products as $p)<option value="{{ $p->id }}" @selected(old('reward_product_id', $offer->reward_product_id) == $p->id)>{{ $p->name }} @if($p->sku)(SKU: {{ $p->sku }})@endif</option>@endforeach
                </select>
            </div>

            <div class="zc-cf-row"><label>Status</label><label class="zc-cf-check"><input type="checkbox" name="active" value="1" @checked(old('active', $offer->exists ? $offer->active : true))> Active (show on storefront)</label></div>

            <div style="text-align:center;margin-top:1.4rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var sel=document.getElementById('zc-of-placement'), hint=document.getElementById('zc-of-hint');
        if(sel){ sel.addEventListener('change', function(){ hint.textContent=sel.options[sel.selectedIndex].getAttribute('data-desc')||''; }); }
    })();
</script>
@endpush
@endsection
