@extends('layouts.app')

@section('title', 'Checkout — '.$storeName)

@php
    $p = $preview ?? [];
    $pItems = collect($p['items'] ?? []);
    $subtotal = (float) ($p['subtotal'] ?? 0);
    $delivery = (float) ($p['delivery_fee'] ?? 0);
    $total = (float) ($p['total'] ?? $subtotal);
    $zones = $deliveryZones ?? [];
    $money = fn ($v) => '৳'.number_format((float) $v, 2);
@endphp

@section('content')
<section class="zc-pagehero">
    <div class="zc-wrap">
        <div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <a href="{{ route('cart.index') }}">Cart</a> <span>/</span> <span>Checkout</span></div>
        <h1>Cash on delivery checkout</h1>
        <p style="opacity:.9;margin-top:6px;">Pay at your door after you inspect the order.</p>
    </div>
</section>

<section class="zc-sec zc-wrap">
    @if ($errors->any())
        <div class="zc-note zc-note--warn" style="margin-bottom:18px;">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('checkout.store') }}">
        @csrf
        <input type="hidden" name="cart_checkout" value="{{ !empty($cartCheckout) ? 1 : 0 }}">
        @if (!empty($productId))<input type="hidden" name="product_id" value="{{ $productId }}">@endif
        @if (!empty($variantId))<input type="hidden" name="variant_id" value="{{ $variantId }}">@endif
        <input type="hidden" name="quantity" value="{{ $quantity ?? 1 }}">
        <input type="hidden" name="coupon_code" id="zc-coupon-code" value="{{ $couponCode ?? '' }}">
        <input type="hidden" name="payment_method" value="cod">

        <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:24px;align-items:start;" class="zc-checkout-layout">
            <div class="zc-card" style="padding:24px;">
                <h3 style="font-size:18px;margin-bottom:16px;">Delivery details</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="zc-field" style="grid-column:1/-1;"><label for="c-name">Full name *</label><input id="c-name" class="zc-input" name="name" value="{{ old('name') }}" required></div>
                    <div class="zc-field"><label for="c-phone">Phone *</label><input id="c-phone" class="zc-input" name="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX"></div>
                    <div class="zc-field"><label for="c-zone">Delivery zone *</label>
                        <select id="c-zone" class="zc-input" name="delivery_zone" required>
                            @foreach ($zones as $zk => $zlabel)
                                <option value="{{ $zk }}" @selected(($deliveryZone ?? '') === $zk)>{{ is_array($zlabel) ? ($zlabel['label'] ?? $zk) : $zlabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="zc-field" style="grid-column:1/-1;">
                        <label for="c-address">Full address *</label>
                        <textarea id="c-address" class="zc-input" name="address" rows="3" required placeholder="House/road/village, area, city — e.g. House 12, Road 5, Dhanmondi, Dhaka">{{ old('address') }}</textarea>
                        <p style="font-size:12px;color:var(--muted);margin-top:5px;">Please include your <b>area and city</b> (e.g. Dhanmondi, Dhaka) so we can confirm delivery correctly.</p>
                    </div>
                    <div class="zc-field" style="grid-column:1/-1;"><label for="c-notes">Order notes</label><textarea id="c-notes" class="zc-input" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                </div>
                <div class="zc-note" style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/></svg>
                    <span><b>Cash on delivery</b> — pay only when your order reaches you.</span>
                </div>
            </div>

            <div class="zc-card" style="padding:22px;position:sticky;top:96px;">
                <h3 style="font-size:18px;margin-bottom:16px;">Your order</h3>
                @php $singleQty = empty($cartCheckout) && !empty($productId) && $pItems->count() === 1; @endphp
                @foreach ($pItems as $it)
                    <div style="display:flex;gap:10px;align-items:center;padding:8px 0;">
                        <div style="width:52px;height:52px;border-radius:10px;overflow:hidden;background:var(--panel);flex:none;display:grid;place-items:center;">
                            @if (!empty($it['image_url']))<img src="{{ $it['image_url'] }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#cfc6b2"><path d="M5 21V9l7-5 7 5v12"/></svg>@endif
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:13.5px;">{{ $it['display_name'] ?? $it['product_name'] ?? 'Item' }}</div>
                            @if ($singleQty)
                                <div class="zc-qstep" style="margin-top:6px;">
                                    <button type="button" data-q-dec aria-label="Decrease">−</button>
                                    <span data-q-val>{{ (int) ($it['quantity'] ?? 1) }}</span>
                                    <button type="button" data-q-inc aria-label="Increase">+</button>
                                </div>
                            @else
                                <div class="zc-muted" style="font-size:12px;">Qty {{ $it['quantity'] ?? 1 }}</div>
                            @endif
                        </div>
                        <div style="font-weight:700;font-size:13.5px;" @if ($singleQty) id="zc-item-total" data-unit="{{ (float) ($it['price'] ?? 0) }}" @endif>{{ $money(($it['price'] ?? 0) * ($it['quantity'] ?? 1)) }}</div>
                    </div>
                @endforeach
                <div class="zc-coupon" style="margin-top:12px;border-top:1px solid var(--line);padding-top:14px;">
                    <label for="zc-coupon-input" style="font-size:13px;font-weight:700;display:block;margin-bottom:7px;">Have a coupon code?</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="zc-coupon-input" class="zc-input" placeholder="Enter coupon code" value="{{ $couponCode ?? '' }}" style="flex:1;text-transform:uppercase;">
                        <button type="button" id="zc-coupon-apply" class="zc-btn" style="white-space:nowrap;padding-inline:20px;">Apply</button>
                    </div>
                    <div id="zc-coupon-msg" style="font-size:12.5px;margin-top:7px;"></div>
                </div>
                <div style="display:grid;gap:9px;font-size:14px;border-top:1px solid var(--line);margin-top:14px;padding-top:14px;">
                    <div style="display:flex;justify-content:space-between;"><span class="zc-muted">Subtotal</span><span style="font-weight:700;" id="zc-subtotal-val">{{ $money($subtotal) }}</span></div>
                    <div id="zc-discount-row" style="display:{{ (float) ($p['discount_amount'] ?? 0) > 0 ? 'flex' : 'none' }};justify-content:space-between;color:#1c8a4e;"><span>Discount</span><span style="font-weight:700;" id="zc-discount-val">−{{ $money($p['discount_amount'] ?? 0) }}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span class="zc-muted">Delivery</span><span style="font-weight:700;" id="zc-delivery-val">{{ $delivery > 0 ? $money($delivery) : 'Calculated' }}</span></div>
                    <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:12px;font-size:18px;"><span style="font-weight:800;">Total</span><span style="font-weight:800;color:var(--leaf-deep);" id="zc-total-val">{{ $money($total) }}</span></div>
                </div>
                <button type="submit" id="zc-place-btn" class="zc-btn zc-btn--primary zc-btn--block" style="margin-top:18px;padding:15px;"><span class="zc-place-label">Place order (COD)</span><span class="zc-place-spin" aria-hidden="true"></span></button>
            </div>
        </div>
    </form>
</section>

@push('storefront-styles')<style>
    @media(max-width:820px){.zc-checkout-layout{grid-template-columns:1fr !important;}}
    /* Quantity stepper */
    .zc-qstep{display:inline-flex;align-items:center;border:1.5px solid var(--line);border-radius:999px;overflow:hidden;background:var(--surface);}
    .zc-qstep button{width:30px;height:30px;border:none;background:transparent;font-size:17px;font-weight:800;line-height:1;color:var(--leaf-deep);cursor:pointer;}
    .zc-qstep button:hover{background:var(--leaf-soft);}
    .zc-qstep button:disabled{opacity:.4;cursor:default;}
    .zc-qstep span{min-width:30px;text-align:center;font-weight:800;font-size:14px;font-variant-numeric:tabular-nums;}
    /* Place order button: continuous shimmer + click spinner */
    #zc-place-btn{position:relative;overflow:hidden;}
    #zc-place-btn::after{content:"";position:absolute;top:0;left:-60%;width:45%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.38),transparent);transform:skewX(-18deg);animation:zc-place-shimmer 2.6s ease-in-out infinite;pointer-events:none;}
    @keyframes zc-place-shimmer{0%{left:-60%}55%,100%{left:130%}}
    #zc-place-btn.is-loading{pointer-events:none;opacity:.95;}
    #zc-place-btn.is-loading::after{display:none;}
    .zc-place-spin{display:none;width:16px;height:16px;border:2.5px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;margin-left:9px;vertical-align:-3px;animation:zc-place-spin .7s linear infinite;}
    #zc-place-btn.is-loading .zc-place-spin{display:inline-block;}
    @keyframes zc-place-spin{to{transform:rotate(360deg)}}
    @media(prefers-reduced-motion:reduce){#zc-place-btn::after{display:none;}}
</style>@endpush
@push('storefront-scripts')
<script>
(function(){
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var input = document.getElementById('zc-coupon-input'),
        hidden = document.getElementById('zc-coupon-code'),
        msg = document.getElementById('zc-coupon-msg'),
        zone = document.getElementById('c-zone'),
        applyBtn = document.getElementById('zc-coupon-apply'),
        qtyHidden = document.querySelector('input[name="quantity"]'),
        itemTotal = document.getElementById('zc-item-total'),
        placeBtn = document.getElementById('zc-place-btn'),
        form = placeBtn ? placeBtn.closest('form') : null;
    var money = function (v) { return '৳' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var ctx = { cart_checkout: '{{ !empty($cartCheckout) ? 1 : 0 }}', product_id: '{{ $productId ?? '' }}', variant_id: '{{ $variantId ?? '' }}' };

    function updateSummary(d){
        document.getElementById('zc-subtotal-val').textContent = money(d.subtotal);
        document.getElementById('zc-delivery-val').textContent = d.delivery_fee > 0 ? money(d.delivery_fee) : 'Calculated';
        document.getElementById('zc-total-val').textContent = money(d.total);
        var dr = document.getElementById('zc-discount-row');
        if (d.discount_amount > 0) { dr.style.display = 'flex'; document.getElementById('zc-discount-val').textContent = '−' + money(d.discount_amount); }
        else { dr.style.display = 'none'; }
    }
    function recompute(){
        var body = new URLSearchParams(Object.assign({}, ctx, {
            quantity: qtyHidden ? qtyHidden.value : '1',
            coupon_code: hidden ? hidden.value : '',
            delivery_zone: zone ? zone.value : ''
        }));
        return fetch('{{ route('checkout.coupon') }}', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) { if (res.ok && res.d) updateSummary(res.d); return res; });
    }

    // ---- Coupon ----
    if (applyBtn && input) {
        function apply() {
            var code = input.value.trim();
            applyBtn.disabled = true; applyBtn.textContent = '…';
            if (hidden) hidden.value = code;
            recompute().then(function (res) {
                applyBtn.disabled = false; applyBtn.textContent = 'Apply';
                var d = res.d || {};
                if (!res.ok) { msg.style.color = '#c0392b'; msg.textContent = d.message || 'Could not apply coupon.'; if (hidden) hidden.value = ''; return; }
                if (code === '') { if (hidden) hidden.value = ''; msg.textContent = ''; return; }
                if (d.valid) { if (hidden) hidden.value = code; msg.style.color = '#1c8a4e'; msg.textContent = '✓ ' + (d.message || 'Coupon applied.'); }
                else { if (hidden) hidden.value = ''; msg.style.color = '#c0392b'; msg.textContent = d.message || 'Invalid coupon.'; }
            }).catch(function () { applyBtn.disabled = false; applyBtn.textContent = 'Apply'; msg.style.color = '#c0392b'; msg.textContent = 'Something went wrong.'; });
        }
        applyBtn.addEventListener('click', apply);
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
    }

    // ---- Quantity stepper (single-product checkout) ----
    var qval = document.querySelector('[data-q-val]');
    if (qval && qtyHidden) {
        var dec = document.querySelector('[data-q-dec]'), inc = document.querySelector('[data-q-inc]');
        var unit = itemTotal ? (parseFloat(itemTotal.getAttribute('data-unit')) || 0) : 0;
        function setQty(q){
            q = Math.max(1, Math.min(99, q));
            var prev = parseInt(qtyHidden.value, 10) || 1;
            if (q === prev) return;
            qval.textContent = q; qtyHidden.value = q;
            if (itemTotal) itemTotal.textContent = money(unit * q);
            [dec, inc].forEach(function (b) { if (b) b.disabled = true; });
            recompute().then(function (res) {
                [dec, inc].forEach(function (b) { if (b) b.disabled = false; });
                if (dec) dec.disabled = (parseInt(qtyHidden.value, 10) || 1) <= 1;
                if (!res.ok) { qtyHidden.value = prev; qval.textContent = prev; if (itemTotal) itemTotal.textContent = money(unit * prev); if (msg) { msg.style.color = '#c0392b'; msg.textContent = (res.d && res.d.message) || 'Not enough stock.'; } }
            });
        }
        if (dec) dec.addEventListener('click', function () { setQty((parseInt(qtyHidden.value, 10) || 1) - 1); });
        if (inc) inc.addEventListener('click', function () { setQty((parseInt(qtyHidden.value, 10) || 1) + 1); });
        if (dec) dec.disabled = (parseInt(qtyHidden.value, 10) || 1) <= 1;
    }

    // ---- Double-submit guard + Place Order loading animation ----
    if (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitting) { e.preventDefault(); return; }
            form.dataset.submitting = '1';
            if (placeBtn) { placeBtn.classList.add('is-loading'); var lbl = placeBtn.querySelector('.zc-place-label'); if (lbl) lbl.textContent = 'Placing order…'; }
        });
    }
})();
</script>

{{-- Incomplete-order capture: save what the customer typed even if they never click Confirm. --}}
<script>
(function(){
    var name=document.getElementById('c-name'), phone=document.getElementById('c-phone');
    if(!name||!phone) return;
    var address=document.getElementById('c-address');
    var meta=document.querySelector('meta[name="csrf-token"]'); if(!meta) return;
    var csrf=meta.content, url="{{ route('checkout.capture') }}";
    var form=name.closest('form');
    var pid=document.querySelector('input[name="product_id"]'), vid=document.querySelector('input[name="variant_id"]'), qty=document.querySelector('input[name="quantity"]');
    var submitted=false, last='';
    function payload(){
        return {
            name:(name.value||'').trim(), phone:(phone.value||'').trim(),
            address:(address?address.value:'').trim(),
            product_id: pid&&pid.value?pid.value:'', variant_id: vid&&vid.value?vid.value:'', quantity: qty&&qty.value?qty.value:1
        };
    }
    function meaningful(p){ return p.name || p.phone || p.address; }
    function send(useBeacon){
        if(submitted) return;
        var p=payload(); if(!meaningful(p)) return;
        var key=JSON.stringify(p); if(key===last && !useBeacon) return; last=key;
        if(useBeacon && navigator.sendBeacon){
            var fd=new FormData(); Object.keys(p).forEach(function(k){ fd.append(k,p[k]); }); fd.append('_token',csrf);
            try{ navigator.sendBeacon(url, fd); }catch(e){}
        } else {
            fetch(url,{method:'POST',keepalive:true,headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:JSON.stringify(p)}).catch(function(){});
        }
    }
    var t;
    [name,phone,address].forEach(function(el){ if(!el) return;
        el.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(function(){ send(false); },1200); });
        el.addEventListener('blur', function(){ send(false); });
    });
    document.addEventListener('visibilitychange', function(){ if(document.visibilityState==='hidden') send(true); });
    window.addEventListener('pagehide', function(){ send(true); });
    if(form) form.addEventListener('submit', function(){ submitted=true; });
})();
</script>
@endpush
@endsection
