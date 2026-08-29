@extends('layouts.studio')
@section('title', $category->name)
@section('subtitle', $title.' details')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cs-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.1rem;}
    .zc-cs-thumb{width:64px;height:64px;border-radius:12px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:grid;place-items:center;color:var(--studio-muted);font-size:0.6rem;font-weight:800;text-align:center;flex:none;}
    .zc-cs-title{font-size:1.15rem;font-weight:800;color:var(--studio-text);}
    .zc-cs-sub{color:var(--studio-muted);font-size:0.82rem;margin-top:2px;}
    .zc-cs-search{max-width:22rem;margin-left:auto;}
    .zc-cs-prod{display:flex;align-items:center;gap:0.75rem;}
    .zc-cs-name{font-weight:700;color:var(--studio-text);}
    .zc-cs-sku{font-size:0.74rem;color:var(--studio-muted);}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-cs-head">
        <a href="{{ route('categories.'.$level.'.index') }}" class="studio-command-button" title="Back to {{ $title }}">&larr; Back</a>
        <span class="zc-cs-thumb">
            @php $img = $mediaUrl($category->image); @endphp
            @if ($img)<img src="{{ $img }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">@else NO IMAGE @endif
        </span>
        <div>
            <div class="zc-cs-title">{{ $category->name }}</div>
            <div class="zc-cs-sub">{{ $singular }} &middot; {{ $products->total() }} product{{ $products->total() === 1 ? '' : 's' }}</div>
        </div>
        <form method="GET" action="{{ route('categories.'.$level.'.show', $category) }}" class="zc-cs-search"><input name="q" value="{{ $search }}" class="studio-form-control" placeholder="Search products in this category" onchange="this.form.submit()"></form>
    </div>

    <div class="studio-card" style="padding:1rem 1.25rem;">
        <div class="studio-responsive-scroll">
        <table class="zc-sm-tbl">
            <thead><tr>
                <th>#</th><th>Product</th><th>Status</th><th>Price</th><th>Stock</th><th>Action</th>
            </tr></thead>
            <tbody>
                @forelse ($products as $product)
                    @php $thumb = $mediaUrl($product->thumbnail); $stock = $product->effective_stock ?? $product->stock; @endphp
                    <tr>
                        <td>{{ $loop->iteration + ($products->firstItem() ? $products->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-cs-prod">
                                <img class="zc-cs-thumb" src="{{ $thumb ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2252%22 height=%2252%22%3E%3C/svg%3E' }}" alt="" loading="lazy">
                                <div>
                                    <div class="zc-cs-name">{{ $product->name }}</div>
                                    <div class="zc-cs-sku">SKU : {{ $product->sku }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="zc-sm-pill {{ $product->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $product->status === 'active' ? 'Publish' : 'Unpublished' }}</span></td>
                        <td>{{ number_format((float) $product->price, 0) }}</td>
                        <td>{{ number_format((int) $stock) }}</td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('products.edit', $product) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="zc-sm-empty">No products in this {{ strtolower($singular) }} yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if ($products->hasPages())<div class="zc-sm-pager">@if(!$products->onFirstPage())<a href="{{ $products->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $products->currentPage() }} / {{ $products->lastPage() }}</span>@if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of total {{ $products->total() }} entries</div>
    </div>
</div>
@endsection
