@extends('layouts.app')

@section('title', 'All Products — '.$storeName)
@section('meta_description', 'Browse our full range of products. Cash on delivery across Bangladesh.')

@section('content')
    <section class="zc-pagehero">
        <div class="zc-wrap">
            <div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <span>All Products</span></div>
            <h1>{{ request('q') ? 'Results for “'.e(request('q')).'”' : 'All products' }}</h1>
            <p style="opacity:.9;margin-top:8px;">{{ $products->total() ?? $products->count() }} products, honestly priced.</p>
        </div>
    </section>

    <section class="zc-sec zc-wrap">
        @if ($products->count())
            <div class="zc-grid zc-grid--5">
                @foreach ($products as $product)@include('storefront.partials.product-card')@endforeach
            </div>

            @if (method_exists($products, 'hasPages') && $products->hasPages())
                <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:34px;">
                    @if ($products->onFirstPage())
                        <span class="zc-btn zc-btn--outline" style="opacity:.5;pointer-events:none;">Previous</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" class="zc-btn zc-btn--outline">Previous</a>
                    @endif
                    <span class="zc-muted" style="font-weight:700;">Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" class="zc-btn zc-btn--primary">Next</a>
                    @else
                        <span class="zc-btn zc-btn--primary" style="opacity:.5;pointer-events:none;">Next</span>
                    @endif
                </div>
            @endif
        @else
            <div class="zc-card" style="padding:60px 24px;text-align:center;">
                <h2 style="font-size:22px;">Nothing here yet</h2>
                <p class="zc-muted" style="margin:10px 0 20px;">We couldn't find products for this view. Try browsing everything.</p>
                <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary">Browse all</a>
            </div>
        @endif
    </section>
@endsection
