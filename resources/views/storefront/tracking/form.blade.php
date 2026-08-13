@extends('layouts.app')
@section('title', 'Track Your Order — '.$storeName)
@section('content')
<section class="zc-wrap" style="padding:34px 0 10px;">
    <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--honey-deep);font-weight:800;display:flex;align-items:center;gap:7px;"><span style="width:7px;height:7px;border-radius:50%;background:var(--honey);display:inline-block;"></span> Live order tracking</div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-top:8px;">
        <div>
            <h1 style="font-size:clamp(24px,3vw,34px);">Track Your Order</h1>
            <p class="zc-muted" style="margin-top:4px;">Real-time updates on your shipment progress</p>
            @if ($order)<div style="margin-top:12px;"><span style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Order Number</span><div style="font-size:19px;font-weight:800;color:var(--honey-deep);">#{{ $order->order_number }}</div></div>@endif
        </div>
        <form method="GET" action="{{ route('tracking.form') }}" style="display:flex;gap:10px;">
            <input type="text" name="order" value="{{ $searched ?? '' }}" placeholder="Order number, e.g. ZC-1024" class="zc-input" style="min-width:230px;" autocomplete="off">
            <button type="submit" class="zc-btn zc-btn--honey" style="white-space:nowrap;">Search</button>
        </form>
    </div>
</section>

<section class="zc-sec zc-wrap" style="padding-top:22px;">
    @if ($order)
        @include('storefront.tracking._view', ['order' => $order])
    @elseif ($notFound ?? false)
        <div class="zc-card" style="padding:38px 24px;text-align:center;max-width:520px;margin:0 auto;">
            <div style="font-size:42px;">🔍</div>
            <h3 style="margin:10px 0 6px;font-size:19px;">No order found</h3>
            <p class="zc-muted">We couldn't find an order with number <b>{{ $searched }}</b>. Please check the number and try again.</p>
        </div>
    @else
        <div class="zc-card" style="padding:38px 24px;text-align:center;max-width:520px;margin:0 auto;">
            <div style="font-size:42px;">📦</div>
            <h3 style="margin:10px 0 6px;font-size:19px;">Enter your order number</h3>
            <p class="zc-muted">Type your order number above to see live status, items, and delivery details.</p>
        </div>
    @endif
</section>
@endsection
