@extends('layouts.app')
@section('title', $category->name.' — '.$storeName)
@section('content')
<section class="zc-pagehero">
    <div class="zc-wrap">
        <div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <a href="{{ route('storefront.products') }}">Products</a> <span>/</span> <span>{{ $category->name }}</span></div>
        <h1>{{ $category->name }}</h1>
        @if ($category->description)<p style="opacity:.9;margin-top:8px;max-width:60ch;">{{ $category->description }}</p>@endif
    </div>
</section>
<section class="zc-sec zc-wrap">
    @if ($products->count())
        <div class="zc-grid zc-grid--5">
            @foreach ($products as $product)@include('storefront.partials.product-card')@endforeach
        </div>
        @if (method_exists($products, 'hasPages') && $products->hasPages())
            <div style="display:flex;justify-content:center;gap:10px;margin-top:30px;">
                @if (!$products->onFirstPage())<a href="{{ $products->previousPageUrl() }}" class="zc-btn zc-btn--outline">Previous</a>@endif
                @if ($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}" class="zc-btn zc-btn--primary">Next</a>@endif
            </div>
        @endif
    @else
        <div class="zc-card" style="padding:50px;text-align:center;"><p class="zc-muted">No products in this category yet.</p><a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary" style="margin-top:14px;">Browse all</a></div>
    @endif
</section>
@endsection
