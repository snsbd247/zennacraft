@extends('layouts.app')
@section('title', 'Order Placed — '.$storeName)
@section('content')
@if (session('payment_error'))
<section class="zc-wrap" style="padding-top:18px;">
    <div style="background:#fff4e5;border:1px solid #f2a20c;color:#7a4a00;border-radius:12px;padding:14px 18px;font-size:14px;">{{ session('payment_error') }}</div>
</section>
@endif
<section class="zc-wrap" style="padding:34px 0 10px;">
    <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--honey-deep);font-weight:800;display:flex;align-items:center;gap:7px;"><span style="width:7px;height:7px;border-radius:50%;background:var(--honey);display:inline-block;"></span> Live order tracking</div>
    <div style="margin-top:8px;">
        <h1 style="font-size:clamp(24px,3vw,34px);">Track Your Order</h1>
        <p class="zc-muted" style="margin-top:4px;">Real-time updates on your shipment progress</p>
        <div style="margin-top:12px;"><span style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Order Number</span><div style="font-size:19px;font-weight:800;color:var(--honey-deep);">#{{ $order->order_number }}</div></div>
    </div>
</section>

<section class="zc-sec zc-wrap" style="padding-top:22px;">
    @include('storefront.tracking._view', ['order' => $order])
    <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary">Continue shopping</a>
        @isset($invoiceUrl)<a href="{{ $invoiceUrl }}" class="zc-btn zc-btn--outline">View invoice</a>@endisset
        <a href="{{ route('tracking.form', ['order' => $order->order_number]) }}" class="zc-btn zc-btn--outline">Live tracking page</a>
    </div>
</section>

{{-- Order Placed! confirmation popup --}}
<div class="zc-op" data-op>
    <div class="zc-op__scrim" data-op-close></div>
    <div class="zc-op__box" role="dialog" aria-label="Order placed">
        <button class="zc-op__x" data-op-close aria-label="Close">✕</button>
        <div class="zc-op__tag">✓ Confirmed</div>
        <div class="zc-op__check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
        <h2>Order Placed!</h2>
        <p>Your order has been received and is being processed.</p>
        <div class="zc-op__rows">
            <div><span>Order No</span><b>#{{ $order->order_number }}</b></div>
            <div><span>Total</span><b>{{ number_format((float) $order->total, 2) }} BDT</b></div>
            <div><span>Placed on</span><b>{{ optional($order->created_at)->format('d M Y') }}</b></div>
        </div>
    </div>
</div>

@push('storefront-styles')
<style>
    .zc-op{position:fixed;inset:0;z-index:1500;display:none;align-items:center;justify-content:center;padding:20px;}
    .zc-op.is-open{display:flex;}
    .zc-op__scrim{position:absolute;inset:0;background:rgba(30,25,15,.55);}
    .zc-op__box{position:relative;z-index:2;background:#fff;border-radius:22px;width:min(430px,100%);padding:34px 28px 30px;text-align:center;box-shadow:0 40px 90px -30px rgba(0,0,0,.55);animation:zc-op-in .35s cubic-bezier(.34,1.4,.4,1);}
    @keyframes zc-op-in{from{transform:scale(.9) translateY(12px);opacity:0;}to{transform:none;opacity:1;}}
    .zc-op__x{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:50%;border:none;background:#f0ebdf;color:#555;font-size:14px;cursor:pointer;}
    .zc-op__tag{font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#1c8a4e;}
    .zc-op__check{width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg,#f2a20c,#d97706);display:grid;place-items:center;margin:16px auto 20px;color:#fff;box-shadow:0 14px 30px -10px rgba(217,119,6,.6);animation:zc-op-pop .5s .12s both cubic-bezier(.34,1.6,.4,1);}
    @keyframes zc-op-pop{from{transform:scale(0);}to{transform:scale(1);}}
    .zc-op__check svg{width:40px;height:40px;}
    .zc-op__box h2{font-size:26px;margin:0 0 8px;}
    .zc-op__box > p{color:var(--muted);margin:0 auto 20px;max-width:34ch;}
    .zc-op__rows{background:var(--panel);border-radius:14px;padding:16px 18px;display:grid;gap:11px;text-align:left;}
    .zc-op__rows > div{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed var(--line);padding-bottom:10px;}
    .zc-op__rows > div:last-child{border-bottom:none;padding-bottom:0;}
    .zc-op__rows span{color:var(--muted);font-size:14px;} .zc-op__rows b{color:var(--ink);font-size:15px;}
</style>
@endpush
@push('storefront-scripts')
<script>
    (function(){
        var op=document.querySelector('[data-op]'); if(!op) return;
        setTimeout(function(){ op.classList.add('is-open'); document.body.classList.add('zc-no-scroll'); }, 250);
        function close(){ op.classList.remove('is-open'); document.body.classList.remove('zc-no-scroll'); }
        op.querySelectorAll('[data-op-close]').forEach(function(b){ b.addEventListener('click', close); });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
    })();
</script>
@endpush
@endsection
