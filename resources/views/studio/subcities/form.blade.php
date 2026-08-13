@extends('layouts.studio')
@section('title', ($subCity->exists ? 'Edit' : 'Add').' Sub City')
@section('subtitle', 'Setting & Configuration')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:620px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:150px 1fr;gap:1.1rem;align-items:center;margin-bottom:1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.85rem;color:var(--studio-text);}
    @media(max-width:560px){.zc-cf-row{grid-template-columns:1fr;gap:0.35rem;}}
    .req{color:#e0483d;font-weight:800;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('subcities.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif
    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $subCity->exists ? 'Edit' : 'Add' }} Sub City</h1>
        <form class="zc-cf" method="POST" action="{{ $subCity->exists ? route('subcities.update', $subCity) : route('subcities.store') }}" style="margin-top:1rem;">
            @csrf @if($subCity->exists) @method('PUT') @endif
            <div class="zc-cf-row"><label>City <span class="req">*</span></label>
                <select name="city_id" class="studio-form-control" required>
                    <option value="">Select City</option>
                    @foreach ($cities as $c)<option value="{{ $c->id }}" @selected(old('city_id', $subCity->city_id) == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="zc-cf-row"><label>Sub City Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $subCity->name) }}" placeholder="e.g. Mirpur" required></div>
            <div class="zc-cf-row"><label>Position</label><input type="number" min="0" name="sort_order" class="studio-form-control" value="{{ old('sort_order', $subCity->sort_order ?? 0) }}"></div>
            <div class="zc-cf-row"><label>Status</label><select name="status" class="studio-form-control"><option value="active" @selected(old('status',$subCity->status)==='active')>Active</option><option value="inactive" @selected(old('status',$subCity->status)==='inactive')>Inactive</option></select></div>
            <div style="text-align:center;margin-top:1.3rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@endsection
