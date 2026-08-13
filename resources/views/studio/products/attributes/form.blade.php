@extends('layouts.studio')
@section('title', $attribute->exists ? 'Edit Attribute' : 'Add Attribute')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('products.attributes.index') }}" class="studio-command-button">&larr; Back</a>
    <div class="studio-card" style="padding:1.75rem;">
        <h1 class="studio-section-title" style="text-align:center;margin-bottom:1.5rem;">{{ $attribute->exists ? 'Edit Attribute' : 'Add Attribute' }}</h1>
        <form class="zc-sm-form" method="POST" action="{{ $attribute->exists ? route('products.attributes.update', $attribute) : route('products.attributes.store') }}" style="margin:0 auto;">
            @csrf @if($attribute->exists) @method('PUT') @endif
            <div class="zc-sm-field">
                <label for="name">Name</label>
                <input id="name" name="name" class="studio-form-control" value="{{ old('name', $attribute->name) }}" placeholder="Ex: Size" required>
                @error('name')<span style="color:#c0392b;font-size:0.78rem;">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="studio-command-button studio-command-button--primary">Submit</button>
        </form>
    </div>
</div>
@endsection
