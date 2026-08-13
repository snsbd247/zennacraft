@extends('layouts.studio')
@section('title', $banner->exists ? 'Edit Banner' : 'Add Banner')
@section('subtitle', 'Campaign / Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-ob-form{max-width:640px;margin:0 auto;}
    .zc-ob-prev{width:100%;max-height:200px;border-radius:12px;object-fit:contain;border:1px solid var(--studio-border);background:var(--studio-surface-soft);margin-bottom:0.7rem;display:block;padding:6px;}
    .zc-ob-row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    @media (max-width:640px){.zc-ob-row2{grid-template-columns:1fr;}}
    .zc-ob-switch{display:inline-flex;align-items:center;gap:0.55rem;font-weight:700;cursor:pointer;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.5rem 1.75rem;">
        <div class="zc-sm-head">
            <a href="{{ route('offer-banners.index') }}" class="studio-command-button">← Back</a>
            <h1 class="studio-section-title" style="flex:1;text-align:center;justify-content:center;">{{ $banner->exists ? 'Edit Offer Banner' : 'Add Offer Banner' }}</h1>
            <div style="width:90px;"></div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ $banner->exists ? route('offer-banners.update', $banner) : route('offer-banners.store') }}" class="zc-ob-form">
            @csrf
            @if ($banner->exists) @method('PUT') @endif

            <div class="zc-sm-field">
                <label>Banner image {{ $banner->exists ? '' : '*' }}</label>
                @if ($mediaUrl($banner->image))<img src="{{ $mediaUrl($banner->image) }}" alt="" class="zc-ob-prev" id="zc-ob-prev">@endif
                <input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-ob-imginput" {{ $banner->exists ? '' : 'required' }}>
                <span style="display:inline-flex;align-items:center;gap:7px;margin-top:8px;font-size:0.78rem;font-weight:600;color:var(--studio-muted);background:var(--studio-surface-soft);border:1px dashed var(--studio-border);border-radius:9px;padding:6px 11px;">📐 Recommended size: <b style="color:var(--studio-text);font-weight:800;">1200 × 300 px</b> (wide strip · JPG/PNG · max 6 MB)</span>
            </div>

            <div class="zc-sm-field">
                <label>Banner placement *</label>
                <select name="placement" class="studio-form-control" required>
                    @foreach ($placements as $p)<option value="{{ $p }}" @selected(old('placement', $banner->placement) === $p)>{{ \App\Modules\Storefront\Models\StorefrontSlider::placementMeta($p)['label'] }}</option>@endforeach
                </select>
                <span style="font-size:0.75rem;color:var(--studio-muted);">Where this banner shows on the storefront.</span>
            </div>

            <div class="zc-sm-field"><label>Title *</label><input type="text" name="title" value="{{ old('title', $banner->title) }}" class="studio-form-control" required placeholder="e.g. Eid Mega Offer"></div>
            <div class="zc-sm-field"><label>Subtitle</label><input type="text" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" class="studio-form-control" placeholder="Optional line under the title"></div>

            <div class="zc-ob-row2">
                <div class="zc-sm-field"><label>Button text</label><input type="text" name="button_text" value="{{ old('button_text', $banner->button_text) }}" class="studio-form-control" placeholder="e.g. Shop Now"></div>
                <div class="zc-sm-field"><label>Button link</label><input type="text" name="button_url" value="{{ old('button_url', $banner->button_url) }}" class="studio-form-control" placeholder="/shop or https://…"></div>
            </div>

            <div class="zc-ob-row2">
                <div class="zc-sm-field"><label>Sort order</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order', $banner->sort_order ?? 0) }}" class="studio-form-control"></div>
                <div class="zc-sm-field"><label>Status</label>
                    <label class="zc-ob-switch" style="padding-top:0.4rem;"><input type="checkbox" name="active" value="1" @checked(old('active', $banner->active ?? true)) style="width:1.15rem;height:1.15rem;accent-color:var(--studio-accent);"> Active (visible on storefront)</label>
                </div>
            </div>

            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:3rem;">{{ $banner->exists ? 'Update Banner' : 'Save Banner' }}</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var input=document.getElementById('zc-ob-imginput'); if(!input) return;
        input.addEventListener('change', function(){
            if(!input.files||!input.files[0]) return;
            var u=URL.createObjectURL(input.files[0]), prev=document.getElementById('zc-ob-prev');
            if(prev){ prev.src=u; } else { var img=document.createElement('img'); img.src=u; img.id='zc-ob-prev'; img.className='zc-ob-prev'; input.parentNode.insertBefore(img,input); }
        });
    })();
</script>
@endpush
@endsection
