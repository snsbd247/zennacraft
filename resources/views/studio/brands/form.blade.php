@extends('layouts.studio')
@section('title', ($brand->exists ? 'Edit' : 'Add').' Brand')
@section('subtitle', 'Brand')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:760px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:170px 1fr;gap:1.25rem;align-items:start;margin-bottom:1.1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.86rem;color:var(--studio-text);padding-top:0.6rem;}
    .zc-cf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;}
    @media(max-width:640px){.zc-cf-row{grid-template-columns:1fr;gap:0.4rem;}.zc-cf-row > label{padding-top:0;}}
    .zc-cf-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .zc-cf-img{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .zc-cf-preview{width:130px;height:80px;border-radius:10px;object-fit:contain;padding:6px;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:none;flex:none;}
    .zc-cf-preview.show{display:block;}
    .zc-cf .req{color:#e0483d;font-weight:800;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('brands.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $brand->exists ? 'Edit' : 'Add' }} Brand</h1>

        <form class="zc-cf" method="POST" enctype="multipart/form-data" action="{{ $brand->exists ? route('brands.update', $brand) : route('brands.store') }}" style="margin-top:1.25rem;">
            @csrf @if($brand->exists) @method('PUT') @endif

            <div class="zc-cf-row"><label>Brand Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $brand->name) }}" placeholder="e.g. ARIYAN" required></div>

            <div class="zc-cf-row">
                <label>Logo Image <small>shown on the brands list · recommended 300 × 150 px (transparent PNG)</small></label>
                <div class="zc-cf-img">
                    @php $img = $mediaUrl($brand->image); @endphp
                    <img id="zc-br-prev" class="zc-cf-preview {{ $img ? 'show' : '' }}" src="{{ $img ?: '' }}" alt="">
                    <input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-br-img" style="max-width:22rem;">
                </div>
            </div>

            <div class="zc-cf-row">
                <label>Position &amp; Status</label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Position <small style="font-weight:400;">(higher shows first)</small></label><input type="number" name="position" min="0" class="studio-form-control" value="{{ old('position', $brand->position ?? 0) }}"></div>
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Status</label>
                        <select name="status" class="studio-form-control">
                            <option value="active" @selected(old('status', $brand->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $brand->status) === 'inactive')>De-active</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>

@push('studio-scripts')
<script>
    (function(){
        var input=document.getElementById('zc-br-img'), prev=document.getElementById('zc-br-prev');
        if(input){ input.addEventListener('change', function(){ var f=input.files[0]; if(f){ prev.src=URL.createObjectURL(f); prev.classList.add('show'); } }); }
    })();
</script>
@endpush
@endsection
