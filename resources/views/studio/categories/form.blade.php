@extends('layouts.studio')
@section('title', ($category->exists ? 'Edit ' : 'Add ').$singular)
@section('subtitle', 'Category')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:820px;margin:0 auto;}
    .zc-cf-sec{text-align:center;font-family:inherit;font-weight:800;font-size:1.15rem;margin:1.75rem 0 1.1rem;color:var(--studio-text);}
    .zc-cf-row{display:grid;grid-template-columns:180px 1fr;gap:1.25rem;align-items:start;margin-bottom:1.1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.86rem;color:var(--studio-text);padding-top:0.6rem;}
    .zc-cf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;}
    @media(max-width:640px){.zc-cf-row{grid-template-columns:1fr;gap:0.4rem;}.zc-cf-row > label{padding-top:0;}}
    .zc-cf-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .zc-cf-img{display:flex;align-items:center;gap:1rem;}
    .zc-cf-preview{width:78px;height:78px;border-radius:12px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:none;}
    .zc-cf-preview.show{display:block;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('categories.'.$level.'.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $category->exists ? 'Edit ' : 'Add ' }}{{ $singular }}</h1>
        <form class="zc-cf" method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('categories.'.$level.'.update', $category) : route('categories.'.$level.'.store') }}" style="margin-top:1.25rem;">
            @csrf @if($category->exists) @method('PUT') @endif

            @if ($hasParent)
                <div class="zc-cf-row">
                    <label>{{ $parentLabel }} <span class="req">*</span></label>
                    <select name="parent_id" class="studio-form-control" required>
                        <option value="">Select {{ $parentLabel }}</option>
                        @foreach ($parents as $p)<option value="{{ $p->id }}" @selected((int) old('parent_id', $category->parent_id) === $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                </div>
            @endif

            <div class="zc-cf-row">
                <label>{{ $singular }} Name <span class="req">*</span></label>
                <input name="name" class="studio-form-control" value="{{ old('name', $category->name) }}" placeholder="{{ strtolower($singular) }} name" required>
            </div>

            <div class="zc-cf-row">
                <label>{{ $singular }} Image <small>(square · recommended 400 × 400 px)</small></label>
                <div class="zc-cf-img">
                    @php $img = $mediaUrl($category->image); @endphp
                    <img id="zc-cf-preview" class="zc-cf-preview {{ $img ? 'show' : '' }}" src="{{ $img ?: '' }}" alt="">
                    <input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-cf-file" style="max-width:22rem;">
                </div>
            </div>

            <div class="zc-cf-sec">SEO Info</div>
            <div class="zc-cf-row"><label>Meta Title</label><input name="meta_title" class="studio-form-control" value="{{ old('meta_title', $category->meta_title) }}" maxlength="70" placeholder="Enter Title Max: 70 characters"></div>
            <div class="zc-cf-row"><label>Meta Description</label><input name="meta_description" class="studio-form-control" value="{{ old('meta_description', $category->meta_description) }}" maxlength="170" placeholder="Enter Description Max: 170 characters"></div>
            <div class="zc-cf-row"><label>Meta Key</label><input name="meta_keywords" class="studio-form-control" value="{{ old('meta_keywords', $category->meta_keywords) }}" placeholder="Enter Key"></div>
            <div class="zc-cf-row"><label>Meta Content</label><textarea name="meta_content" class="studio-form-control" rows="2" placeholder="Enter Content">{{ old('meta_content', $category->meta_content) }}</textarea></div>

            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>

@push('studio-scripts')
<script>
    (function(){
        var file=document.getElementById('zc-cf-file'), prev=document.getElementById('zc-cf-preview');
        file.addEventListener('change', function(){ var f=file.files[0]; if(f){ prev.src=URL.createObjectURL(f); prev.classList.add('show'); } });
    })();
</script>
@endpush
@endsection
