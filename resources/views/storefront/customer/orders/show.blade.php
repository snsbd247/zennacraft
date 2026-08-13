@extends('layouts.app')
@section('title', 'Order '.$order->order_number)
@section('content')
<section class="zc-pagehero"><div class="zc-wrap"><div class="zc-crumbs"><a href="{{ route('customer.orders') }}">Orders</a> <span>/</span> <span>{{ $order->order_number }}</span></div><h1>Order #{{ $order->order_number }}</h1><p style="opacity:.9;margin-top:6px;">{{ $order->created_at?->format('M j, Y g:i A') }} · <span class="zc-badge zc-badge--new" style="vertical-align:middle;">{{ ucfirst($order->shipment?->status ?? $order->status) }}</span></p></div></section>
<section class="zc-sec zc-wrap" style="max-width:820px;">
    <div class="zc-card" style="padding:22px;">
        @foreach ($order->items as $item)
            <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--line-soft);' : '' }}">
                <div><b>{{ $item->product_name }}</b><div class="zc-muted" style="font-size:13px;">Qty {{ $item->quantity }} × ৳{{ number_format((float) $item->price, 2) }}</div></div>
                <b>৳{{ number_format((float) $item->subtotal, 2) }}</b>
            </div>
        @endforeach
        <div style="border-top:1px solid var(--line);margin-top:12px;padding-top:14px;display:grid;gap:8px;font-size:14px;">
            <div style="display:flex;justify-content:space-between;"><span class="zc-muted">Delivery</span><b>৳{{ number_format((float) $order->delivery_fee, 2) }}</b></div>
            <div style="display:flex;justify-content:space-between;font-size:18px;"><b>Total</b><b style="color:var(--leaf-deep);">৳{{ number_format((float) $order->total, 2) }}</b></div>
        </div>
    </div>
    <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;"><a href="{{ route('customer.orders.tracking', $order) }}" class="zc-btn zc-btn--primary">Track this order</a><a href="{{ route('customer.orders') }}" class="zc-btn zc-btn--outline">Back to orders</a></div>
</section>
@endsection
