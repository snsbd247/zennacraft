@extends('layouts.studio')
@section('title', 'POS')
@section('subtitle', 'Point of Sale')

@push('studio-styles')
<style>
    .zc-pos{display:grid;grid-template-columns:1.5fr 1fr;gap:18px;align-items:start;max-width:1200px;margin:0 auto;}
    .zc-pos-card{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,.04),0 20px 50px -42px rgba(16,24,40,.35);}
    .zc-pos-card__h{padding:14px 18px;border-bottom:1px solid var(--studio-border);font-weight:800;font-size:0.95rem;display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .zc-pos-search{position:relative;padding:14px 18px;}
    .zc-pos-search input{width:100%;padding:12px 14px;border:1px solid var(--studio-border);border-radius:11px;font-size:0.95rem;background:var(--studio-surface);color:var(--studio-text);}
    .zc-pos-search input:focus{outline:none;border-color:var(--studio-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--studio-accent) 20%,transparent);}
    .zc-pos-results{max-height:60vh;overflow-y:auto;padding:0 8px 8px;}
    .zc-pos-res{display:flex;align-items:center;gap:12px;width:100%;text-align:left;padding:10px 12px;border:none;background:transparent;border-radius:10px;cursor:pointer;color:var(--studio-text);}
    .zc-pos-res:hover{background:var(--studio-surface-soft);}
    .zc-pos-res--var{padding-left:26px;font-size:0.85rem;}
    .zc-pos-res__img{width:44px;height:44px;border-radius:9px;object-fit:cover;background:var(--studio-surface-soft);border:1px solid var(--studio-border);flex:none;}
    .zc-pos-res__main{flex:1;min-width:0;}
    .zc-pos-res__name{font-weight:700;font-size:0.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .zc-pos-res__meta{font-size:0.74rem;color:var(--studio-muted);}
    .zc-pos-res__price{font-weight:800;white-space:nowrap;font-variant-numeric:tabular-nums;}
    .zc-pos-res__out{color:#c0392b;font-weight:700;font-size:0.72rem;}
    .zc-pos-empty{padding:40px 18px;text-align:center;color:var(--studio-muted);}

    .zc-pos-cart{display:flex;flex-direction:column;}
    .zc-pos-lines{max-height:38vh;overflow-y:auto;}
    .zc-pos-line{display:flex;align-items:center;gap:10px;padding:11px 18px;border-bottom:1px solid var(--studio-border);}
    .zc-pos-line__main{flex:1;min-width:0;}
    .zc-pos-line__name{font-weight:700;font-size:0.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .zc-pos-line__price{font-size:0.75rem;color:var(--studio-muted);}
    .zc-pos-qty{display:flex;align-items:center;gap:6px;}
    .zc-pos-qty button{width:26px;height:26px;border-radius:7px;border:1px solid var(--studio-border);background:var(--studio-surface);color:var(--studio-text);font-weight:800;cursor:pointer;line-height:1;}
    .zc-pos-qty span{min-width:22px;text-align:center;font-weight:800;font-variant-numeric:tabular-nums;}
    .zc-pos-line__amt{font-weight:800;white-space:nowrap;font-variant-numeric:tabular-nums;min-width:60px;text-align:right;}
    .zc-pos-line__rm{border:none;background:none;color:#c0392b;cursor:pointer;font-size:1rem;padding:2px 4px;}
    .zc-pos-cart__empty{padding:38px 18px;text-align:center;color:var(--studio-muted);}

    .zc-pos-foot{padding:14px 18px;border-top:1px solid var(--studio-border);display:grid;gap:9px;}
    .zc-pos-row{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:0.88rem;}
    .zc-pos-row span{color:var(--studio-muted);}
    .zc-pos-row.grand{font-size:1.15rem;font-weight:800;border-top:1px dashed var(--studio-border);padding-top:10px;}
    .zc-pos-row.grand b{color:var(--studio-accent);}
    .zc-pos-in{width:110px;text-align:right;border:1px solid var(--studio-border);border-radius:8px;padding:7px 9px;font-size:0.88rem;background:var(--studio-surface);color:var(--studio-text);font-variant-numeric:tabular-nums;}
    .zc-pos-cust{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:0 18px 10px;}
    .zc-pos-cust input{padding:9px 11px;border:1px solid var(--studio-border);border-radius:9px;font-size:0.85rem;background:var(--studio-surface);color:var(--studio-text);}
    .zc-pos-complete{margin:2px 18px 18px;padding:15px;border:none;border-radius:12px;background:linear-gradient(135deg,#2bb673,#0f7a45);color:#fff;font-weight:800;font-size:1rem;cursor:pointer;box-shadow:0 14px 28px -14px rgba(15,122,69,.7);}
    .zc-pos-complete:disabled{opacity:.55;cursor:not-allowed;box-shadow:none;}
    .zc-pos-change{color:#1c8a4e;font-weight:800;}

    /* Success modal */
    .zc-pos-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,20,25,.55);}
    .zc-pos-modal.is-open{display:flex;}
    .zc-pos-modal__box{background:var(--studio-surface);border-radius:18px;padding:34px 30px;max-width:380px;width:100%;text-align:center;box-shadow:0 40px 90px -30px rgba(0,0,0,.5);}
    .zc-pos-modal__ok{width:64px;height:64px;border-radius:50%;background:#e3f6ea;color:#1c8a4e;display:grid;place-items:center;margin:0 auto 14px;}
    .zc-pos-modal__ok svg{width:32px;height:32px;}
    .zc-pos-modal h3{font-size:1.3rem;font-weight:800;margin-bottom:6px;}
    .zc-pos-modal__acts{display:grid;gap:9px;margin-top:20px;}
    @media(max-width:900px){.zc-pos{grid-template-columns:1fr;}}
</style>
@endpush

@section('content')
<div class="space-y-4">
    <div class="zc-pos">
        {{-- Product search --}}
        <div class="zc-pos-card">
            <div class="zc-pos-card__h">Products <span class="studio-badge studio-badge--info">tap to add</span></div>
            <div class="zc-pos-search">
                <input type="text" data-pos-search placeholder="Search product name or SKU…" autocomplete="off" autofocus>
            </div>
            <div class="zc-pos-results" data-pos-results>
                <div class="zc-pos-empty">Start typing to find products.</div>
            </div>
        </div>

        {{-- Cart + payment --}}
        <div class="zc-pos-card zc-pos-cart">
            <div class="zc-pos-card__h">Current sale <button type="button" class="studio-command-button" style="padding:.3rem .7rem;font-size:.72rem;" data-pos-clear>Clear</button></div>
            <div class="zc-pos-lines" data-pos-lines>
                <div class="zc-pos-cart__empty" data-pos-empty>No items yet — search and tap a product.</div>
            </div>
            <div class="zc-pos-cust">
                <input type="text" data-pos-name placeholder="Customer name (optional)">
                <input type="text" data-pos-phone inputmode="numeric" placeholder="Phone (optional)">
            </div>
            <div class="zc-pos-foot">
                <div class="zc-pos-row"><span>Subtotal</span><span data-pos-subtotal>৳0</span></div>
                <div class="zc-pos-row"><span>Discount</span><input type="number" min="0" step="0.01" value="0" class="zc-pos-in" data-pos-discount></div>
                <div class="zc-pos-row grand"><span style="color:var(--studio-text);">Total</span><b data-pos-total>৳0</b></div>
                <div class="zc-pos-row"><span>Cash received</span><input type="number" min="0" step="0.01" value="0" class="zc-pos-in" data-pos-paid></div>
                <div class="zc-pos-row"><span>Change</span><span class="zc-pos-change" data-pos-changeamt>৳0</span></div>
            </div>
            <button type="button" class="zc-pos-complete" data-pos-complete disabled>Complete sale</button>
        </div>
    </div>
</div>

{{-- Success modal --}}
<div class="zc-pos-modal" data-pos-modal>
    <div class="zc-pos-modal__box">
        <div class="zc-pos-modal__ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg></div>
        <h3>Sale complete</h3>
        <div class="studio-section-subtitle" data-pos-modal-info>Order recorded.</div>
        <div class="zc-pos-modal__acts">
            <a href="#" target="_blank" rel="noopener" class="studio-command-button studio-command-button--primary" data-pos-receipt style="justify-content:center;">🧾 Print receipt</a>
            <button type="button" class="studio-command-button" data-pos-new style="justify-content:center;">＋ New sale</button>
        </div>
    </div>
</div>
@endsection

@push('studio-scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var searchUrl = @json(route('pos.products.search'));
    var storeUrl = @json(route('pos.store'));

    var cart = []; // {key, product_id, variant_id, name, price, qty, stock}
    var els = {
        search: document.querySelector('[data-pos-search]'),
        results: document.querySelector('[data-pos-results]'),
        lines: document.querySelector('[data-pos-lines]'),
        empty: document.querySelector('[data-pos-empty]'),
        subtotal: document.querySelector('[data-pos-subtotal]'),
        discount: document.querySelector('[data-pos-discount]'),
        total: document.querySelector('[data-pos-total]'),
        paid: document.querySelector('[data-pos-paid]'),
        change: document.querySelector('[data-pos-changeamt]'),
        complete: document.querySelector('[data-pos-complete]'),
        name: document.querySelector('[data-pos-name]'),
        phone: document.querySelector('[data-pos-phone]'),
        modal: document.querySelector('[data-pos-modal]'),
    };
    var money = function (v) { return '৳' + (Math.round(v * 100) / 100).toLocaleString('en-US'); };
    function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

    // ---- Search ----
    var timer = null;
    els.search.addEventListener('input', function () {
        var q = els.search.value.trim();
        clearTimeout(timer);
        if (!q) { els.results.innerHTML = '<div class="zc-pos-empty">Start typing to find products.</div>'; return; }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var items = d.results || [];
                    if (!items.length) { els.results.innerHTML = '<div class="zc-pos-empty">No products found.</div>'; return; }
                    els.results.innerHTML = items.map(function (p) {
                        var img = p.thumb ? '<img class="zc-pos-res__img" src="' + p.thumb + '" alt="">' : '<span class="zc-pos-res__img"></span>';
                        var out = p.stock <= 0;
                        var html = '<button type="button" class="zc-pos-res" data-add data-id="' + p.id + '" data-name="' + esc(p.name) + '" data-price="' + p.price + '" data-stock="' + p.stock + '"' + (out ? ' disabled style="opacity:.5"' : '') + '>' +
                            img + '<div class="zc-pos-res__main"><div class="zc-pos-res__name">' + esc(p.name) + '</div><div class="zc-pos-res__meta">' + esc(p.sku || '') + (out ? ' · <span class="zc-pos-res__out">out of stock</span>' : ' · stock ' + p.stock) + '</div></div><div class="zc-pos-res__price">' + money(p.price) + '</div></button>';
                        (p.variants || []).forEach(function (v) {
                            var vout = v.stock <= 0;
                            html += '<button type="button" class="zc-pos-res zc-pos-res--var" data-add data-id="' + p.id + '" data-variant="' + v.id + '" data-name="' + esc(p.name + ' — ' + v.label) + '" data-price="' + v.price + '" data-stock="' + v.stock + '"' + (vout ? ' disabled style="opacity:.5"' : '') + '>' +
                                '<div class="zc-pos-res__main"><div class="zc-pos-res__name">↳ ' + esc(v.label) + '</div><div class="zc-pos-res__meta">' + (vout ? '<span class="zc-pos-res__out">out</span>' : 'stock ' + v.stock) + '</div></div><div class="zc-pos-res__price">' + money(v.price) + '</div></button>';
                        });
                        return html;
                    }).join('');
                });
        }, 200);
    });

    els.results.addEventListener('click', function (e) {
        var b = e.target.closest('[data-add]');
        if (!b || b.disabled) return;
        addToCart({
            product_id: parseInt(b.getAttribute('data-id'), 10),
            variant_id: b.getAttribute('data-variant') ? parseInt(b.getAttribute('data-variant'), 10) : null,
            name: b.getAttribute('data-name'),
            price: parseFloat(b.getAttribute('data-price')) || 0,
            stock: parseInt(b.getAttribute('data-stock'), 10) || 0,
        });
    });

    function addToCart(item) {
        var key = item.product_id + ':' + (item.variant_id || 0);
        var line = cart.find(function (l) { return l.key === key; });
        if (line) { line.qty += 1; } else { cart.push({ key: key, product_id: item.product_id, variant_id: item.variant_id, name: item.name, price: item.price, qty: 1, stock: item.stock }); }
        renderCart();
    }

    // ---- Cart ----
    function renderCart() {
        if (!cart.length) {
            els.lines.innerHTML = '<div class="zc-pos-cart__empty" data-pos-empty>No items yet — search and tap a product.</div>';
        } else {
            els.lines.innerHTML = cart.map(function (l, i) {
                return '<div class="zc-pos-line"><div class="zc-pos-line__main"><div class="zc-pos-line__name">' + esc(l.name) + '</div><div class="zc-pos-line__price">' + money(l.price) + ' each</div></div>' +
                    '<div class="zc-pos-qty"><button type="button" data-dec="' + i + '">−</button><span>' + l.qty + '</span><button type="button" data-inc="' + i + '">+</button></div>' +
                    '<div class="zc-pos-line__amt">' + money(l.price * l.qty) + '</div>' +
                    '<button type="button" class="zc-pos-line__rm" data-rm="' + i + '">✕</button></div>';
            }).join('');
        }
        recompute();
    }

    els.lines.addEventListener('click', function (e) {
        var inc = e.target.closest('[data-inc]'), dec = e.target.closest('[data-dec]'), rm = e.target.closest('[data-rm]');
        if (inc) { cart[+inc.getAttribute('data-inc')].qty += 1; renderCart(); }
        else if (dec) { var l = cart[+dec.getAttribute('data-dec')]; l.qty = Math.max(1, l.qty - 1); renderCart(); }
        else if (rm) { cart.splice(+rm.getAttribute('data-rm'), 1); renderCart(); }
    });

    function recompute() {
        var subtotal = cart.reduce(function (s, l) { return s + l.price * l.qty; }, 0);
        var discount = Math.min(parseFloat(els.discount.value) || 0, subtotal);
        var total = Math.max(0, subtotal - discount);
        var paid = parseFloat(els.paid.value) || 0;
        els.subtotal.textContent = money(subtotal);
        els.total.textContent = money(total);
        els.change.textContent = money(Math.max(0, paid - total));
        els.complete.disabled = cart.length === 0;
    }
    els.discount.addEventListener('input', recompute);
    els.paid.addEventListener('input', recompute);
    document.querySelector('[data-pos-clear]').addEventListener('click', function () { cart = []; els.discount.value = 0; els.paid.value = 0; els.name.value = ''; els.phone.value = ''; renderCart(); });

    // ---- Complete ----
    els.complete.addEventListener('click', function () {
        if (!cart.length) return;
        els.complete.disabled = true; els.complete.textContent = 'Recording…';
        fetch(storeUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: cart.map(function (l) { return { product_id: l.product_id, variant_id: l.variant_id, quantity: l.qty }; }),
                customer_name: els.name.value.trim(), customer_phone: els.phone.value.trim(),
                discount: parseFloat(els.discount.value) || 0, paid: parseFloat(els.paid.value) || 0,
            }),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            els.complete.disabled = false; els.complete.textContent = 'Complete sale';
            if (res.ok && res.d.success) {
                document.querySelector('[data-pos-modal-info]').textContent = 'Order ' + res.d.order_number + ' · ' + money(res.d.total);
                document.querySelector('[data-pos-receipt]').setAttribute('href', res.d.receipt_url);
                els.modal.classList.add('is-open');
            } else {
                var first = res.d.errors ? Object.values(res.d.errors)[0][0] : (res.d.message || 'Could not complete the sale.');
                alert(first);
            }
        }).catch(function () { els.complete.disabled = false; els.complete.textContent = 'Complete sale'; alert('Network error. Try again.'); });
    });

    document.querySelector('[data-pos-new]').addEventListener('click', function () {
        cart = []; els.discount.value = 0; els.paid.value = 0; els.name.value = ''; els.phone.value = '';
        els.search.value = ''; els.results.innerHTML = '<div class="zc-pos-empty">Start typing to find products.</div>';
        renderCart(); els.modal.classList.remove('is-open'); els.search.focus();
    });

    renderCart();
})();
</script>
@endpush
