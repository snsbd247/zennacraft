@extends('layouts.studio')
@section('title', $value->exists ? 'Edit Variant' : 'Add Variant')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('products.variants.index') }}" class="studio-command-button">&larr; Back</a>
    <div class="studio-card" style="padding:1.75rem;">
        <h1 class="studio-section-title" style="text-align:center;margin-bottom:1.5rem;">{{ $value->exists ? 'Edit Variant' : 'Add Variant' }}</h1>
        <form class="zc-sm-form" method="POST" action="{{ $value->exists ? route('products.variants.update', $value) : route('products.variants.store') }}" style="margin:0 auto;">
            @csrf @if($value->exists) @method('PUT') @endif
            <div class="zc-sm-field">
                <label for="attribute_id">Select Attribute</label>
                <select id="attribute_id" name="attribute_id" class="studio-form-control" required>
                    <option value="">-- Select Attribute --</option>
                    @foreach ($attributes as $attr)<option value="{{ $attr->id }}" @selected((int) old('attribute_id', $value->attribute_id) === $attr->id)>{{ $attr->name }}</option>@endforeach
                </select>
                @error('attribute_id')<span style="color:#c0392b;font-size:0.78rem;">{{ $message }}</span>@enderror
            </div>
            <div class="zc-sm-field">
                <label for="name">Name</label>
                <input id="name" name="name" class="studio-form-control" value="{{ old('name', $value->name) }}" placeholder="Ex: MAGENTA" required>
                @error('name')<span style="color:#c0392b;font-size:0.78rem;">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="studio-command-button studio-command-button--primary">Submit</button>
        </form>
    </div>
</div>
@endsection
