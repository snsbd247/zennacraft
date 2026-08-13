{{-- Embedded landing-page order form: pick product/variant + billing + promo → creates the order directly (no separate checkout). --}}
@php $accent = $accent ?? '#1f7a3d'; @endphp
<section id="zc-order" class="zc-lo">
    <div class="zc-lo__wrap">
        <h2 class="zc-lo__title">অর্ডার করতে সঠিক সাইজ সিলেক্ট করুন <span>Select &amp; confirm your order</span></h2>

        {{-- One selectable card per variant (or per product when it has none) so
             the customer taps the exact option; multiple cards can be selected. --}}
        <div class="zc-lo__products">
            @foreach ($products as $product)
                @php
                    $variants = ($product->variants ?? collect())->filter(fn ($v) => (int) $v->stock > 0)->values();
                    if ($variants->isEmpty() && ($product->variants ?? collect())->isNotEmpty()) {
                        $variants = ($product->variants ?? collect())->values();
                    }
                    $img = $mediaUrl($product->thumbnail);
                @endphp
                @if ($variants->isNotEmpty())
                    @foreach ($variants as $v)
                        @php
                            $vlabel = collect((array) ($v->option_values ?? []))->filter()->implode(' / ') ?: ($v->name ?: 'Option');
                            $vimg = $v->image ? $mediaUrl($v->image) : $img;
                            $vout = (int) $v->stock <= 0;
                        @endphp
                        <div class="zc-lo-prod" data-lo-prod data-id="{{ $product->id }}" data-variant="{{ $v->id }}" data-name="{{ $product->name }}" data-label="{{ $vlabel }}" data-price="{{ (float) $v->price }}" @if ($vout) data-lo-out @endif>
                            <span class="zc-lo-prod__pick"><input type="checkbox" data-lo-pick @disabled($vout)></span>
                            <span class="zc-lo-prod__img">@if ($vimg)<img src="{{ $vimg }}" alt="{{ $product->name }}">@endif</span>
                            <div class="zc-lo-prod__body">
                                <div class="zc-lo-prod__name">{{ $product->name }}</div>
                                <div class="zc-lo-prod__variant">{{ $vlabel }}</div>
                                <div class="zc-lo-prod__price">৳{{ number_format((float) $v->price) }}@if ($vout)<span class="zc-lo-prod__out">Stock out</span>@endif</div>
                                <div class="zc-lo-prod__ctrls" data-lo-noselect>
                                    <div class="zc-lo-qty"><button type="button" data-lo-dec>−</button><span data-lo-qty>1</span><button type="button" data-lo-inc>+</button></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="zc-lo-prod" data-lo-prod data-id="{{ $product->id }}" data-variant="" data-name="{{ $product->name }}" data-label="" data-price="{{ (float) $product->price }}">
                        <span class="zc-lo-prod__pick"><input type="checkbox" data-lo-pick></span>
                        <span class="zc-lo-prod__img">@if ($img)<img src="{{ $img }}" alt="{{ $product->name }}">@endif</span>
                        <div class="zc-lo-prod__body">
                            <div class="zc-lo-prod__name">{{ $product->name }}</div>
                            <div class="zc-lo-prod__price">৳{{ number_format((float) $product->price) }}</div>
                            <div class="zc-lo-prod__ctrls" data-lo-noselect>
                                <div class="zc-lo-qty"><button type="button" data-lo-dec>−</button><span data-lo-qty>1</span><button type="button" data-lo-inc>+</button></div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="zc-lo__cols">
            <div class="zc-lo-billing">
                <h3>বিলিং বিবরণ <span>Billing details</span></h3>
                <div class="zc-lo-field"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg><input type="tel" id="zc-lo-phone" placeholder="01XXXXXXXXX *" required></div>
                <div class="zc-lo-field"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><input type="text" id="zc-lo-name" placeholder="আপনার নাম / Full name *" required></div>
                <div class="zc-lo-field"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg><textarea id="zc-lo-address" rows="2" placeholder="এলাকা, বাসা, রোড / Full address *" required></textarea></div>
                <div class="zc-lo-field"><svg viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                    <select id="zc-lo-zone">@foreach ($deliveryZones as $z)<option value="{{ $z['key'] }}" data-charge="{{ $z['charge'] }}">{{ $z['label'] }}</option>@endforeach</select>
                </div>
            </div>

            <div class="zc-lo-summary">
                <h3>আপনার অর্ডার <span>Your order</span></h3>
                <div id="zc-lo-items" class="zc-lo-items"><p class="zc-lo-empty">Tick a product above to add it to your order.</p></div>

                <div class="zc-lo-coupon">
                    <input type="text" id="zc-lo-coupon" placeholder="প্রোমো কোড / Promo code" autocomplete="off">
                    <button type="button" id="zc-lo-coupon-apply">Apply</button>
                </div>
                <div id="zc-lo-coupon-msg" class="zc-lo-cmsg"></div>

                <div class="zc-lo-rows">
                    <div class="row"><span>Subtotal</span><b id="zc-lo-subtotal">৳0</b></div>
                    <div class="row" id="zc-lo-discount-row" style="display:none;color:#1c8a4e;"><span>Discount</span><b id="zc-lo-discount">−৳0</b></div>
                    <div class="row"><span>Shipping</span><b id="zc-lo-shipping">৳0</b></div>
                    <div class="row total"><span>Payable</span><b id="zc-lo-payable">৳0</b></div>
                </div>
                <div id="zc-lo-msg" class="zc-lo-msg"></div>
                <button type="button" id="zc-lo-confirm" class="zc-lo-confirm">অর্ডার কনফার্ম করুন — Confirm order</button>
            </div>
        </div>
    </div>
</section>

<style>
    html{scroll-behavior:smooth;}
    .zc-lo{background:#eef3f0;padding:52px 16px;scroll-margin-top:70px;}
    .zc-lo__wrap{max-width:960px;margin:0 auto;}
    .zc-lo__title{text-align:center;font-weight:900;font-size:clamp(20px,3vw,28px);color:#14532d;margin-bottom:24px;}
    .zc-lo__title span{display:block;font-size:14px;font-weight:600;color:#5f6b63;margin-top:4px;}
    .zc-lo__products{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px;}
    @media(max-width:720px){.zc-lo__products{grid-template-columns:1fr;}}
    .zc-lo-prod{display:flex;align-items:center;gap:12px;background:#fff;border:2px solid #e5eae7;border-radius:14px;padding:10px;transition:border-color .15s,box-shadow .15s,transform .1s;cursor:pointer;position:relative;}
    .zc-lo-prod:hover{border-color:#c9d6cd;}
    .zc-lo-prod.is-picked{border-color:{{ $accent }};box-shadow:0 10px 26px -18px {{ $accent }};animation:zcLoPop .42s cubic-bezier(.34,1.56,.5,1);}
    /* A ring flashes outward the moment a card is selected. */
    .zc-lo-prod.is-picked::after{content:"";position:absolute;inset:-2px;border-radius:16px;border:2.5px solid {{ $accent }};opacity:0;pointer-events:none;animation:zcLoRing .58s ease-out;}
    @keyframes zcLoPop{0%{transform:scale(1);}40%{transform:scale(1.035);}70%{transform:scale(.995);}100%{transform:scale(1);}}
    @keyframes zcLoRing{0%{opacity:.85;transform:scale(1);}100%{opacity:0;transform:scale(1.05);}}
    @media(prefers-reduced-motion:reduce){.zc-lo-prod.is-picked,.zc-lo-prod.is-picked::after{animation:none;}}
    .zc-lo-prod[data-lo-out]{opacity:.55;cursor:not-allowed;}
    .zc-lo-prod__pick input{width:20px;height:20px;accent-color:{{ $accent }};cursor:pointer;}
    .zc-lo-prod__img{width:58px;height:58px;border-radius:10px;overflow:hidden;background:#f4f6f5;flex:none;display:grid;place-items:center;}
    .zc-lo-prod__img img{width:100%;height:100%;object-fit:cover;}
    .zc-lo-prod__body{flex:1;min-width:0;}
    .zc-lo-prod__name{font-weight:700;font-size:14px;color:#222;line-height:1.25;}
    .zc-lo-prod__variant{display:inline-block;font-size:12px;font-weight:800;color:{{ $accent }};background:rgba(0,0,0,.045);border:1px solid #e5eae7;border-radius:999px;padding:2px 10px;margin:4px 0 5px;}
    .zc-lo-prod__price{font-weight:900;color:{{ $accent }};font-size:15px;margin:2px 0 6px;}
    .zc-lo-prod__out{margin-left:8px;font-size:11px;font-weight:800;color:#c0392b;text-transform:uppercase;letter-spacing:.03em;}
    .zc-lo-prod__ctrls{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
    .zc-lo-qty{display:inline-flex;align-items:center;border:1px solid #dfe4e1;border-radius:8px;overflow:hidden;}
    .zc-lo-qty button{width:28px;height:28px;border:none;background:#f4f6f5;font-size:16px;cursor:pointer;color:#333;line-height:1;}
    .zc-lo-qty button:active{background:#e6eae7;}
    .zc-lo-qty span{min-width:28px;text-align:center;font-weight:700;font-size:13px;}
    .zc-lo-sel{border:1px solid #dfe4e1;border-radius:8px;padding:5px 8px;font-size:12.5px;background:#fff;}
    .zc-lo__cols{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    @media(max-width:720px){.zc-lo__cols{grid-template-columns:1fr;}}
    .zc-lo-billing,.zc-lo-summary{background:#fff;border:1px solid #e5eae7;border-radius:16px;padding:18px;}
    .zc-lo-billing h3,.zc-lo-summary h3{font-weight:800;font-size:15px;color:#14532d;margin-bottom:14px;}
    .zc-lo-billing h3 span,.zc-lo-summary h3 span{font-weight:500;color:#8a938d;font-size:12px;}
    .zc-lo-field{display:flex;align-items:flex-start;gap:9px;border:1px solid #e0e5e2;border-radius:10px;padding:10px 12px;margin-bottom:11px;}
    .zc-lo-field svg{width:18px;height:18px;fill:none;stroke:#9aa39d;stroke-width:1.8;flex:none;margin-top:2px;}
    .zc-lo-field input,.zc-lo-field textarea,.zc-lo-field select{border:none;outline:none;width:100%;font-size:14px;font-family:inherit;background:none;resize:none;}
    .zc-lo-items{min-height:40px;margin-bottom:12px;}
    .zc-lo-empty{color:#9aa39d;font-size:13px;text-align:center;padding:10px 0;}
    .zc-lo-item{display:flex;justify-content:space-between;gap:8px;font-size:13px;padding:7px 0;border-bottom:1px dashed #eceeec;}
    .zc-lo-item small{color:#8a938d;}
    .zc-lo-coupon{display:flex;gap:8px;margin-bottom:6px;}
    .zc-lo-coupon input{flex:1;border:1px solid #e0e5e2;border-radius:10px;padding:10px 12px;font-size:14px;text-transform:uppercase;outline:none;}
    .zc-lo-coupon button{border:none;border-radius:10px;background:#14532d;color:#fff;font-weight:700;padding:0 18px;cursor:pointer;font-size:14px;}
    .zc-lo-cmsg{font-size:12px;margin-bottom:8px;min-height:14px;}
    .zc-lo-rows{border-top:1px solid #eceeec;padding-top:12px;}
    .zc-lo-rows .row{display:flex;justify-content:space-between;font-size:14px;margin-bottom:7px;color:#555;}
    .zc-lo-rows .row.total{font-size:18px;font-weight:900;color:#14532d;border-top:1px solid #eceeec;padding-top:10px;margin-top:4px;}
    .zc-lo-msg{font-size:12.5px;margin:6px 0;min-height:16px;}
    .zc-lo-confirm{width:100%;margin-top:8px;border:none;border-radius:12px;background:{{ $accent }};color:#fff;font-weight:900;font-size:16px;padding:15px;cursor:pointer;box-shadow:0 16px 30px -14px {{ $accent }};transition:transform .12s;}
    .zc-lo-confirm:hover{transform:translateY(-1px);} .zc-lo-confirm:disabled{opacity:.6;cursor:not-allowed;transform:none;}
</style>

<script>
(function(){
    var section = document.getElementById('zc-order'); if(!section) return;
    var cfg = {
        url: '{{ route('landing.order.store') }}',
        couponUrl: '{{ route('landing.order.coupon') }}',
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        landingId: {{ $landingPage->id }},
        freeThreshold: {{ $freeDeliveryThreshold ?? 0 }},
        freeAll: {{ ($freeDeliveryAll ?? false) ? 'true' : 'false' }}
    };
    var zoneSel = document.getElementById('zc-lo-zone');
    var money = function(v){ return '৳' + Math.round(v).toLocaleString('en-US'); };
    var state = { subtotal:0, shipping:0, items:[], code:'', discount:0, freeShip:false };
    var couponTimer = null, couponBusy = false;

    function resolve(row){
        var qty = parseInt(row.querySelector('[data-lo-qty]').textContent,10) || 1;
        var vid = row.getAttribute('data-variant');
        return { id: vid ? parseInt(vid,10) : null, price: parseFloat(row.getAttribute('data-price'))||0, qty:qty, label: row.getAttribute('data-label')||'' };
    }

    function baseCompute(){
        var subtotal = 0, items = [];
        document.querySelectorAll('[data-lo-prod]').forEach(function(row){
            var picked = row.querySelector('[data-lo-pick]').checked;
            row.classList.toggle('is-picked', picked);
            if(!picked) return;
            var r = resolve(row);
            subtotal += r.price * r.qty;
            items.push({ product_id: parseInt(row.getAttribute('data-id'),10), variant_id: r.id, quantity: r.qty, name: row.getAttribute('data-name'), label: r.label, price: r.price });
        });
        var zoneCharge = parseFloat((zoneSel.options[zoneSel.selectedIndex] || {}).getAttribute ? zoneSel.options[zoneSel.selectedIndex].getAttribute('data-charge') : 0) || 0;
        state.subtotal = subtotal;
        state.shipping = cfg.freeAll ? 0 : (subtotal > 0 && subtotal >= cfg.freeThreshold ? 0 : (subtotal > 0 ? zoneCharge : 0));
        state.items = items;
        var box = document.getElementById('zc-lo-items');
        box.innerHTML = items.length ? items.map(function(i){ return '<div class="zc-lo-item"><span>'+i.name+(i.label?' <small>('+i.label+')</small>':'')+' × '+i.quantity+'</span><b>'+money(i.price*i.quantity)+'</b></div>'; }).join('') : '<p class="zc-lo-empty">Tick a product above to add it to your order.</p>';
        document.getElementById('zc-lo-msg').textContent = '';
    }

    function renderTotals(){
        var shipping = (state.code && state.freeShip) ? 0 : state.shipping;
        var discount = state.code ? state.discount : 0;
        var payable = Math.max(0, state.subtotal + shipping - discount);
        document.getElementById('zc-lo-subtotal').textContent = money(state.subtotal);
        document.getElementById('zc-lo-shipping').textContent = state.subtotal > 0 ? (shipping > 0 ? money(shipping) : 'Free') : '৳0';
        var dr = document.getElementById('zc-lo-discount-row');
        if(state.code && discount > 0){ dr.style.display = 'flex'; document.getElementById('zc-lo-discount').textContent = '−' + money(discount); }
        else dr.style.display = 'none';
        document.getElementById('zc-lo-payable').textContent = money(payable);
    }

    function refresh(){ baseCompute(); renderTotals(); if(state.code){ clearTimeout(couponTimer); couponTimer = setTimeout(fetchCoupon, 300); } }

    function fetchCoupon(){
        if(!state.code || !state.items.length){ state.discount = 0; state.freeShip = false; renderTotals(); return; }
        couponBusy = true;
        fetch(cfg.couponUrl, { method:'POST', headers:{ 'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':cfg.csrf,'Content-Type':'application/json' },
            body: JSON.stringify({ coupon_code: state.code, delivery_zone: zoneSel.value, items: state.items.map(function(i){ return { product_id:i.product_id, variant_id:i.variant_id, quantity:i.quantity }; }) }) })
            .then(function(r){ return r.json().then(function(d){ return {ok:r.ok,d:d}; }); })
            .then(function(res){
                couponBusy = false;
                var msg = document.getElementById('zc-lo-coupon-msg'), d = res.d || {};
                if(res.ok && d.valid){ state.discount = d.discount_amount || 0; state.freeShip = !!d.free_shipping; msg.style.color = '#1c8a4e'; msg.textContent = '✓ ' + (d.message || 'Promo applied.'); }
                else { state.discount = 0; state.freeShip = false; state.code = ''; msg.style.color = '#c0392b'; msg.textContent = (d && d.message) || 'Invalid promo code.'; }
                renderTotals();
            })
            .catch(function(){ couponBusy = false; });
    }

    section.addEventListener('click', function(e){
        var inc = e.target.closest('[data-lo-inc]'), dec = e.target.closest('[data-lo-dec]');
        if(inc || dec){ var q = (inc || dec).closest('.zc-lo-prod').querySelector('[data-lo-qty]'); var n = (parseInt(q.textContent,10) || 1) + (inc ? 1 : -1); q.textContent = Math.max(1, n); refresh(); return; }
        if(e.target.closest('[data-lo-noselect]')) return;   // qty controls don't toggle the card
        if(e.target.closest('[data-lo-pick]')) return;       // the checkbox toggles itself (change)
        var card = e.target.closest('[data-lo-prod]');
        if(card && !card.hasAttribute('data-lo-out')){ var cb = card.querySelector('[data-lo-pick]'); cb.checked = !cb.checked; refresh(); }
    });
    section.addEventListener('change', function(e){
        if(e.target.matches('[data-lo-pick]')){
            refresh();
        }
        if(e.target === zoneSel) refresh();
    });

    document.getElementById('zc-lo-coupon-apply').addEventListener('click', function(){
        var code = document.getElementById('zc-lo-coupon').value.trim().toUpperCase();
        var msg = document.getElementById('zc-lo-coupon-msg');
        if(!state.items.length){ msg.style.color = '#c0392b'; msg.textContent = 'Select a product first.'; return; }
        if(!code){ state.code = ''; state.discount = 0; msg.textContent = ''; renderTotals(); return; }
        state.code = code; fetchCoupon();
    });

    document.getElementById('zc-lo-confirm').addEventListener('click', function(){
        var btn = this, msg = document.getElementById('zc-lo-msg');
        baseCompute();
        var phone = document.getElementById('zc-lo-phone').value.trim();
        var name = document.getElementById('zc-lo-name').value.trim();
        var address = document.getElementById('zc-lo-address').value.trim();
        if(!state.items.length){ msg.style.color='#c0392b'; msg.textContent='Please select at least one product.'; return; }
        if(!name || phone.length < 6 || !address){ msg.style.color='#c0392b'; msg.textContent='Please fill your name, phone and address.'; return; }
        btn.disabled = true; var orig = btn.textContent; btn.textContent = 'অর্ডার হচ্ছে…';
        fetch(cfg.url, { method:'POST', headers:{ 'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':cfg.csrf,'Content-Type':'application/json' },
            body: JSON.stringify({ name:name, phone:phone, address:address, delivery_zone: zoneSel.value, landing_page_id: cfg.landingId, coupon_code: state.code || null,
                items: state.items.map(function(i){ return { product_id:i.product_id, variant_id:i.variant_id, quantity:i.quantity }; }) }) })
            .then(function(r){ return r.json().then(function(d){ return {ok:r.ok,d:d}; }); })
            .then(function(res){
                if(res.ok && res.d.redirect){ window.location = res.d.redirect; return; }
                btn.disabled=false; btn.textContent=orig; msg.style.color='#c0392b'; msg.textContent = (res.d && res.d.message) || 'Could not place the order. Please try again.';
            })
            .catch(function(){ btn.disabled=false; btn.textContent=orig; msg.style.color='#c0392b'; msg.textContent='Network error. Please try again.'; });
    });

    // One-page feel: every CTA smoothly scrolls to this form and focuses it.
    document.querySelectorAll('a[href="#zc-order"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(function () { var f = document.getElementById('zc-lo-name'); if (f) f.focus({ preventScroll: true }); }, 550);
        });
    });

    refresh();
})();
</script>
