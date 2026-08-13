@extends('layouts.studio')
@section('title', 'Attribute / Size')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <div class="zc-sm-actions">
            <a href="{{ route('products.attributes.create') }}" class="studio-command-button studio-command-button--primary">+ Attribute Add</a>
            <a href="{{ route('products.variants.index') }}" class="studio-command-button">Variant</a>
        </div>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">Attributes</h1>
        <div style="width:210px;"></div>
    </div>
    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th>Values</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($attributes as $attribute)
                    <tr>
                        <td>{{ $loop->iteration + ($attributes->firstItem() ? $attributes->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $attribute->name }}</td>
                        <td>{{ $attribute->values_count }}</td>
                        <td><span class="zc-sm-pill {{ $attribute->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ ucfirst($attribute->status) }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <form method="POST" action="{{ route('products.attributes.toggle', $attribute) }}">@csrf<button class="zc-sm-btn zc-sm-btn--tog" title="Toggle status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button></form>
                                <a href="{{ route('products.attributes.edit', $attribute) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <form method="POST" action="{{ route('products.attributes.destroy', $attribute) }}" onsubmit="return confirm('Delete this attribute?')">@csrf @method('DELETE')<button class="zc-sm-btn zc-sm-btn--del" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No attributes yet. Click <b>+ Attribute Add</b> to create your first one (Colour, Size, Weight…).</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($attributes->hasPages())<div class="zc-sm-pager">@if(!$attributes->onFirstPage())<a href="{{ $attributes->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $attributes->currentPage() }} / {{ $attributes->lastPage() }}</span>@if($attributes->hasMorePages())<a href="{{ $attributes->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
@endsection
