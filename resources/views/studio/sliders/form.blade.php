@extends('layouts.studio')
@php
    $placements = \App\Modules\Storefront\Models\StorefrontSlider::PLACEMENTS;
    $curr = old('placement', $slider->placement ?? 'home_hero');
    $seg = str_replace('home_', '', $curr);
    $meta = $placements[$curr] ?? $placements['home_hero'];
@endphp
@section('title', ($slider->exists ? 'Edit' : 'Add').' '.$meta['label'])
@section('subtitle', 'Slider')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:840px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:190px 1fr;gap:1.25rem;align-items:start;margin-bottom:1.1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.86rem;color:var(--studio-text);padding-top:0.6rem;}
    .zc-cf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;}
    @media(max-width:640px){.zc-cf-row{grid-template-columns:1fr;gap:0.4rem;}.zc-cf-row > label{padding-top:0;}}
    .zc-cf-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .zc-cf-img{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .zc-cf-preview{width:190px;height:96px;border-radius:10px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:none;flex:none;}
    .zc-cf-preview.show{display:block;}
    .zc-recsize{display:inline-flex;align-items:center;gap:7px;margin-top:8px;font-size:0.78rem;font-weight:600;color:var(--studio-muted);background:var(--studio-surface-soft);border:1px dashed var(--studio-border);border-radius:9px;padding:6px 11px;}
    .zc-recsize svg{width:15px;height:15px;color:var(--studio-accent);flex:none;}
    .zc-recsize b{color:var(--studio-text);font-weight:800;}
    .zc-cf-check{display:inline-flex;align-items:center;gap:0.6rem;font-weight:700;cursor:pointer;}
    .zc-cf-check input{width:1.15rem;height:1.15rem;accent-color:var(--studio-accent);}
    .zc-cf .req{color:#e0483d;font-weight:800;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route("sliders.$seg.index") }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $slider->exists ? 'Edit' : 'Add' }} {{ $meta['label'] }}</h1>
        <div style="text-align:center;font-size:0.8rem;color:var(--studio-muted);margin-bottom:1.25rem;">{{ $meta['desc'] }}</div>

        <form class="zc-cf" method="POST" enctype="multipart/form-data" action="{{ $slider->exists ? route('sliders.update', $slider) : route('sliders.store') }}">
            @csrf @if($slider->exists) @method('PUT') @endif

            <div class="zc-cf-row">
                <label>Placement <span class="req">*</span> <small>where this banner shows</small></label>
                <select name="placement" class="studio-form-control" id="zc-sl-placement">
                    @foreach ($placements as $val => $p)
                        <option value="{{ $val }}" data-size="{{ $p['size'] ?? '' }}" @selected($curr === $val)>{{ $p['label'] }} — {{ $p['desc'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="zc-cf-row"><label>Title <small>optional — leave empty for a clean image-only banner</small></label><input name="title" class="studio-form-control" value="{{ old('title', $slider->title) }}" placeholder="e.g. Big Sale — up to 40% off (optional)"></div>
            <div class="zc-cf-row"><label>Subtitle</label><input name="subtitle" class="studio-form-control" value="{{ old('subtitle', $slider->subtitle) }}" placeholder="Short supporting line (optional)"></div>
            <div class="zc-cf-row"><label>Badge Text</label><input name="badge_text" class="studio-form-control" value="{{ old('badge_text', $slider->badge_text) }}" placeholder="e.g. This week (optional)"></div>

            <div class="zc-cf-row">
                <label>Button</label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Button Text</label><input name="button_text" class="studio-form-control" value="{{ old('button_text', $slider->button_text) }}" placeholder="Shop the collection"></div>
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Button Link</label><input name="button_url" class="studio-form-control" value="{{ old('button_url', $slider->button_url) }}" placeholder="/products or full URL"></div>
                </div>
            </div>

            <div class="zc-cf-row">
                <label>Image @if(!$slider->exists)<span class="req">*</span>@endif <small>one image — auto-resizes to fit every device (desktop &amp; mobile)</small></label>
                <div class="zc-cf-img">
                    @php $d = $mediaUrl($slider->image); @endphp
                    <img id="zc-sl-prev" class="zc-cf-preview {{ $d ? 'show' : '' }}" src="{{ $d ?: '' }}" alt="">
                    <input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-sl-img" style="max-width:22rem;" @if(!$slider->exists) required @endif>
                    <div class="zc-recsize" data-rec-size>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 3v18"/></svg>
                        Recommended size: <b>{{ $meta['size'] ?? '720 × 300 px' }}</b>
                    </div>
                </div>
            </div>

            <div class="zc-cf-row">
                <label>Position &amp; Visibility</label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Position</label><input type="number" name="sort_order" min="0" class="studio-form-control" value="{{ old('sort_order', $slider->sort_order ?? 0) }}"></div>
                    <div class="zc-sm-field" style="margin:0;justify-content:center;"><label style="font-size:0.72rem;">Status</label><label class="zc-cf-check"><input type="checkbox" name="active" value="1" @checked(old('active', $slider->exists ? $slider->active : true))> Active (show on storefront)</label></div>
                </div>
            </div>

            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>

@push('studio-scripts')
<script>
    (function(){
        var input=document.getElementById('zc-sl-img'), prev=document.getElementById('zc-sl-prev');
        if(input){ input.addEventListener('change', function(){ var f=input.files[0]; if(f){ prev.src=URL.createObjectURL(f); prev.classList.add('show'); } }); }
        // Update the recommended image size when the placement changes.
        var sel=document.getElementById('zc-sl-placement'), rec=document.querySelector('[data-rec-size] b');
        if(sel && rec){ sel.addEventListener('change', function(){ var s=sel.options[sel.selectedIndex].getAttribute('data-size'); if(s) rec.textContent=s; }); }
    })();
</script>
@endpush
@endsection
