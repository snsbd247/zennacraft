@extends('layouts.app')
@section('title', 'My Orders — '.$storeName)
@section('content')
<section class="zc-pagehero"><div class="zc-wrap"><div class="zc-crumbs"><a href="{{ route('customer.dashboard') }}">Account</a> <span>/</span> <span>Orders</span></div><h1>My orders</h1></div></section>
<section class="zc-sec zc-wrap">
    @php $orders = $orders ?? collect(); @endphp
    @if (($orders instanceof \Countable ? count($orders) : $orders->count()) > 0)
        <div class="zc-card" style="padding:8px 18px;">
            @foreach ($orders as $order)
                <div style="display:flex;gap:14px;align-items:center;padding:16px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--line-soft);' : '' }}flex-wrap:wrap;">
                    <div style="flex:1;min-width:180px;"><b>#{{ $order->order_number }}</b><div class="zc-muted" style="font-size:13px;">{{ $order->created_at?->format('M j, Y') }} · {{ $order->items->count() ?? 0 }} item(s)</div></div>
                    <span class="zc-badge zc-badge--soft">{{ ucfirst($order->status) }}</span>
                    <b style="min-width:100px;text-align:right;">৳{{ number_format((float) $order->total, 2) }}</b>
                    <a href="{{ route('customer.orders.show', $order) }}" class="zc-btn zc-btn--outline zc-btn--sm">Details</a>
                </div>
            @endforeach
        </div>
        @if (method_exists($orders, 'hasPages') && $orders->hasPages())<div style="margin-top:20px;display:flex;justify-content:center;gap:10px;">@if(!$orders->onFirstPage())<a href="{{ $orders->previousPageUrl() }}" class="zc-btn zc-btn--outline">Previous</a>@endif @if($orders->hasMorePages())<a href="{{ $orders->nextPageUrl() }}" class="zc-btn zc-btn--primary">Next</a>@endif</div>@endif
    @else
        <div class="zc-card" style="padding:50px;text-align:center;"><p class="zc-muted">You have no orders yet.</p><a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary" style="margin-top:14px;">Start shopping</a></div>
    @endif
</section>
@endsection
