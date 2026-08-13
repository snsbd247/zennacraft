@extends('layouts.studio')
@section('title', 'Variant')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <div class="zc-sm-actions">
            <a href="{{ route('products.variants.create') }}" class="studio-command-button studio-command-button--primary">+ Variant Add</a>
            <a href="{{ route('products.attributes.index') }}" class="studio-command-button">Attribute</a>
        </div>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">Variants</h1>
        <div style="width:210px;"></div>
    </div>
    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th>Attribute</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($values as $value)
                    <tr>
                        <td>{{ $loop->iteration + ($values->firstItem() ? $values->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $value->name }}</td>
                        <td>{{ $value->attribute->name ?? '—' }}</td>
                        <td><span class="zc-sm-pill {{ $value->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ ucfirst($value->status) }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <form method="POST" action="{{ route('products.variants.toggle', $value) }}">@csrf<button class="zc-sm-btn zc-sm-btn--tog" title="Toggle status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button></form>
                                <a href="{{ route('products.variants.edit', $value) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <form method="POST" action="{{ route('products.variants.destroy', $value) }}" onsubmit="return confirm('Delete this variant?')">@csrf @method('DELETE')<button class="zc-sm-btn zc-sm-btn--del" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No variants yet. Add an attribute first, then create variant values (MAGENTA, FREE SIZE, 12-13 YEAR…).</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($values->hasPages())<div class="zc-sm-pager">@if(!$values->onFirstPage())<a href="{{ $values->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $values->currentPage() }} / {{ $values->lastPage() }}</span>@if($values->hasMorePages())<a href="{{ $values->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
@endsection
