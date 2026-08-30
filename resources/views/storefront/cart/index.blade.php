@extends('layouts.app')

@section('title', 'Your Cart — '.$storeName)

@php
    $items = collect($cartItems ?? []);
    $s = $cartSummary ?? [];
    $subtotal = (float) ($s['subtotal'] ?? 0);
    $delivery = (float) ($s['delivery_fee'] ?? 0);
    $discount = (float) ($s['discount_amount'] ?? 0);
    $total = (float) ($s['total'] ?? $subtotal);
    $money = fn ($v) => '৳'.number_format((float) $v, 2);
@endphp

@section('content')
<section class="zc-pagehero">
    <div class="zc-wrap">
        <div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <span>Cart</span></div>
        <h1>Your cart</h1>
        <p style="opacity:.9;margin-top:6px;">{{ $items->sum('quantity') ?: 0 }} item(s) · cash on delivery</p>
    </div>
</section>

<section class="zc-sec zc-wrap">
    @if ($items->isEmpty())
        <div class="zc-card" style="padding:60px 24px;text-align:center;max-width:520px;margin:0 auto;">
            <div style="width:70px;height:70px;border-radius:50%;background:var(--leaf-soft);color:var(--leaf-deep);display:grid;place-items:center;margin:0 auto 16px;"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/><circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/></svg></div>
            <h2 style="font-size:22px;">Your cart is empty</h2>
            <p class="zc-muted" style="margin:10px 0 20px;">Add some products before you check out.</p>
            <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary">Start shopping</a>
        </div>
    @else
        <div style="display:grid;grid-template-columns:1.7fr 1fr;gap:24px;align-items:start;" class="zc-cart-layout">
            <div class="zc-card" style="padding:8px 18px;">
                @foreach ($items as $item)
                    @php $key = $item['key'] ?? $item['id'] ?? ''; $q = (int) ($item['quantity'] ?? 1); $lp = (float) ($item['price'] ?? 0); @endphp
                    <div style="display:flex;gap:14px;align-items:center;padding:16px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--line-soft);' : '' }}">
                        <div style="width:74px;height:74px;border-radius:12px;overflow:hidden;background:var(--panel);flex:none;display:grid;place-items:center;">
                            @if (!empty($item['image_url']))<img src="{{ $item['image_url'] }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#cfc6b2"><path d="M5 21V9l7-5 7 5v12"/></svg>@endif
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:700;font-size:14.5px;">{{ $item['display_name'] ?? $item['product_name'] ?? 'Item' }}</div>
                            @if (!empty($item['package_name']))<div class="zc-muted" style="font-size:12.5px;">{{ $item['package_name'] }}</div>@endif
                            <div style="color:var(--leaf-deep);font-weight:800;margin-top:4px;">{{ $money($lp) }}</div>
                        </div>
                        <form method="POST" action="{{ route('cart.update', $key) }}" style="display:flex;align-items:center;gap:6px;">
                            @csrf @method('PATCH')
                            <input type="number" name="quantity" value="{{ $q }}" min="1" max="99" class="zc-input" style="width:64px;padding:8px;text-align:center;">
                            <button type="submit" class="zc-btn zc-btn--outline zc-btn--sm">Update</button>
                        </form>
                        <form method="POST" action="{{ route('cart.remove', $key) }}">
                            @csrf @method('DELETE')
                            <button type="submit" aria-label="Remove" class="zc-btn zc-btn--sm" style="background:var(--sale-soft);color:var(--sale);border:none;">✕</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="zc-card" style="padding:22px;position:sticky;top:96px;">
                <h3 style="font-size:18px;margin-bottom:16px;">Order summary</h3>
                <form method="GET" action="{{ route('cart.index') }}" style="display:flex;gap:8px;margin-bottom:16px;">
                    <input type="text" name="coupon_code" value="{{ $couponCode ?? '' }}" placeholder="Coupon code" class="zc-input" style="flex:1;">
                    <button type="submit" class="zc-btn zc-btn--outline">Apply</button>
                </form>
                <div style="display:grid;gap:10px;font-size:14px;">
                    <div style="display:flex;justify-content:space-between;"><span class="zc-muted">Subtotal</span><span style="font-weight:700;">{{ $money($subtotal) }}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span class="zc-muted">Delivery</span><span style="font-weight:700;">{{ $delivery > 0 ? $money($delivery) : 'At checkout' }}</span></div>
                    @if ($discount > 0)<div style="display:flex;justify-content:space-between;color:var(--leaf-deep);"><span>Discount</span><span style="font-weight:700;">−{{ $money($discount) }}</span></div>@endif
                    <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:12px;margin-top:4px;font-size:18px;"><span style="font-weight:800;">Total</span><span style="font-weight:800;color:var(--leaf-deep);">{{ $money($total) }}</span></div>
                </div>
                <a href="{{ route('checkout', array_filter(['cart_checkout' => 1, 'coupon_code' => $couponCode ?? null])) }}" class="zc-btn zc-btn--primary zc-btn--block" style="margin-top:18px;">Proceed to checkout</a>
                <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--outline zc-btn--block" style="margin-top:10px;">Continue shopping</a>
                <div class="zc-note" style="margin-top:16px;font-size:12.5px;">Cash on delivery available. Inspect your order before paying.</div>
            </div>
        </div>
    @endif
</section>

@endsection
