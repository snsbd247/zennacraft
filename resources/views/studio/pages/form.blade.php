@extends('layouts.studio')
@section('title', ($cmsPage->exists ? 'Edit' : 'Add').' Page')
@section('subtitle', 'Website Setup')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:820px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:150px 1fr;gap:1.1rem;align-items:start;margin-bottom:1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.85rem;padding-top:0.6rem;color:var(--studio-text);}
    .zc-cf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;}
    @media(max-width:600px){.zc-cf-row{grid-template-columns:1fr;gap:0.35rem;}.zc-cf-row>label{padding-top:0;}}
    .req{color:#e0483d;font-weight:800;}
    .zc-cf-check{display:inline-flex;align-items:center;gap:0.6rem;font-weight:700;cursor:pointer;}
    .zc-cf-check input{width:1.15rem;height:1.15rem;accent-color:var(--studio-accent);}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('pages.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif
    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $cmsPage->exists ? 'Edit' : 'Add' }} Page</h1>
        <form class="zc-cf" method="POST" action="{{ $cmsPage->exists ? route('pages.update', $cmsPage) : route('pages.store') }}" style="margin-top:1rem;">
            @csrf @if($cmsPage->exists) @method('PUT') @endif
            <div class="zc-cf-row"><label>Title <span class="req">*</span></label><input name="title" class="studio-form-control" value="{{ old('title', $cmsPage->title) }}" placeholder="e.g. About us" required></div>
            <div class="zc-cf-row"><label>Link (slug) <small>leave blank to auto-generate</small></label><input name="slug" class="studio-form-control" value="{{ old('slug', $cmsPage->slug) }}" placeholder="about-us"></div>
            <div class="zc-cf-row"><label>Content <small>HTML supported</small></label><textarea name="content" class="studio-form-control" rows="12" placeholder="Page content — basic HTML works.">{{ old('content', $cmsPage->content) }}</textarea></div>
            <div class="zc-cf-row"><label>Meta Title</label><input name="meta_title" class="studio-form-control" value="{{ old('meta_title', $cmsPage->meta_title) }}"></div>
            <div class="zc-cf-row"><label>Meta Description</label><textarea name="meta_description" class="studio-form-control" rows="2">{{ old('meta_description', $cmsPage->meta_description) }}</textarea></div>
            <div class="zc-cf-row"><label>Status</label><label class="zc-cf-check"><input type="checkbox" name="active" value="1" @checked(old('active', $cmsPage->exists ? $cmsPage->active : true))> Active (visible on the site)</label></div>
            <div style="text-align:center;margin-top:1.3rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@endsection
