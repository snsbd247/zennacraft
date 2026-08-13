@extends('layouts.app')
@section('title', 'Tracking '.$order->order_number)
@section('content')
@php
    $heroStatus = ['pending' => 'Pending', 'confirmed' => 'Approved', 'processing' => 'Processing', 'shipped' => 'In Transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'][$order->status] ?? ucfirst($order->status);
@endphp
<section class="zc-pagehero">
    <div class="zc-wrap">
        <div class="zc-crumbs">
            <a href="{{ route('storefront.home') }}">Home</a> <span>/</span>
            <a href="{{ route('customer.dashboard') }}">My Account</a> <span>/</span>
            <span>Tracking</span>
        </div>
        <h1>Order #{{ $order->order_number }}</h1>
        <p style="opacity:.9;margin-top:6px;">Current status: <b>{{ $heroStatus }}</b></p>
    </div>
</section>

<section class="zc-sec zc-wrap" style="padding-top:22px;">
    @include('storefront.tracking._view', ['order' => $order])

    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="{{ route('customer.dashboard') }}" class="zc-btn zc-btn--outline zc-btn--sm">← Back to my account</a>
        <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--outline zc-btn--sm">Continue shopping</a>
    </div>
</section>
@endsection
