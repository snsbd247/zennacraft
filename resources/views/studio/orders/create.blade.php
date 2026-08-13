@extends('layouts.studio')

@section('title', 'Create Order')
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
    <style>
        .zc-co-grid { display:grid; gap:1.25rem; grid-template-columns:1fr; }
        @media (min-width:1024px){ .zc-co-grid { grid-template-columns:0.8fr 1.2fr; } }

        .zc-co-field { display:grid; gap:0.3rem; margin-bottom:0.85rem; }
        .zc-co-field > label { font-size:0.75rem; font-weight:700; color:var(--studio-muted); }

        .zc-co-search-wrap { position:relative; }
        .zc-co-results {
            position:absolute; left:0; right:0; top:100%; z-index:30; margin-top:0.35rem; max-height:20rem; overflow-y:auto;
            border:1px solid var(--studio-border); border-radius:12px; background:var(--studio-surface);
            box-shadow:0 26px 60px -28px rgba(16,24,40,0.28); display:none;
        }
        .zc-co-result { display:flex; align-items:center; gap:0.7rem; padding:0.55rem 0.75rem; cursor:pointer; border-bottom:1px solid var(--studio-border); }
        .zc-co-result:last-child { border-bottom:none; }
        .zc-co-result:hover { background:rgba(212,180,131,0.1); }
        .zc-co-result img, .zc-co-result .zc-co-ph { width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--studio-border); background:var(--studio-surface-soft); flex:none; }
        .zc-co-result__name { font-weight:700; color:var(--studio-text); font-size:0.85rem; }
        .zc-co-result__meta { font-size:0.72rem; color:var(--studio-muted); }

        .zc-co-tbl { width:100%; border-collapse:collapse; }
        .zc-co-tbl th { text-align:left; font-size:0.66rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:var(--studio-muted); padding:0.6rem 0.5rem; border-bottom:1px solid var(--studio-border); }
        .zc-co-tbl td { padding:0.6rem 0.5rem; border-bottom:1px solid var(--studio-border); vertical-align:middle; font-size:0.82rem; color:var(--studio-text); }
        .zc-co-prod { display:flex; align-items:center; gap:0.6rem; }
        .zc-co-prod img, .zc-co-prod .zc-co-ph { width:38px; height:38px; border-radius:8px; object-fit:cover; border:1px solid var(--studio-border); background:var(--studio-surface-soft); flex:none; }
        .zc-co-qty { width:4rem; }
        .zc-co-price { width:6rem; }
        .zc-co-stock { display:inline-block; margin-left:0.4rem; padding:0.05rem 0.4rem; border-radius:6px; background:rgba(52,199,123,0.16); color:#1c8a4e; font-size:0.7rem; font-weight:800; }
        .zc-co-del { display:inline-flex; align-items:center; justify-content:center; width:2rem; height:2rem; border-radius:8px; border:none; background:linear-gradient(135deg,#e0685a,#c0392b); color:#fff; cursor:pointer; }
        .zc-co-del svg { width:1rem; height:1rem; }

        .zc-co-summary { display:grid; gap:0.55rem; margin-top:1rem; max-width:24rem; margin-left:auto; }
        .zc-co-sumrow { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .zc-co-sumrow > span { color:var(--studio-muted); font-size:0.85rem; }
        .zc-co-sumrow input, .zc-co-sumrow select { max-width:11rem; }
        .zc-co-sumrow--big { font-weight:800; font-size:1.05rem; color:var(--studio-text); border-top:1px solid var(--studio-border-strong); padding-top:0.6rem; }
        .zc-co-empty td { text-align:center; color:var(--studio-muted); padding:1.5rem; }
    </style>
@endpush

@section('content')
    <form method="POST" action="{{ route('orders.store') }}" id="zc-co-form">
        @csrf
        <div style="margin-bottom:1rem;">
            <a href="{{ route('orders.index') }}" class="studio-command-button">← Back</a>
        </div>

        @if ($errors->any())
            <div class="studio-callout studio-callout--danger" style="margin-bottom:1rem;"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="zc-co-grid">
            {{-- Customer Information --}}
            <section class="zc-op-panel p-5">
                <h2 class="studio-section-title" style="text-align:center; margin-bottom:1rem;">Customer Information</h2>

                <div class="zc-co-field">
                    <label for="co-phone">Customer Phone</label>
                    <input type="text" id="co-phone" name="phone" value="{{ old('phone') }}" class="studio-form-control" placeholder="Enter customer 11 digit mobile number" required>
                </div>
                <div class="zc-co-field">
                    <label for="co-name">Name</label>
                    <input type="text" id="co-name" name="name" value="{{ old('name') }}" class="studio-form-control" placeholder="Name" required>
                </div>
                <div class="zc-co-field">
                    <label for="co-address">Address</label>
                    <input type="text" id="co-address" name="address" value="{{ old('address') }}" class="studio-form-control" placeholder="address">
                </div>
                <div class="zc-co-field">
                    <label for="co-courier">Courier</label>
                    <select id="co-courier" name="courier_provider_id" class="studio-form-control">
                        <option value="">Select Courier</option>
                        @foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="zc-co-field">
                    <label for="co-city">City</label>
                    <select id="co-city" name="district" class="studio-form-control">
                        <option value="">Select City</option>
                        @foreach ($districts as $d)<option value="{{ $d }}" {{ old('district') === $d ? 'selected' : '' }}>{{ $d }}</option>@endforeach
                    </select>
                </div>
                <div class="zc-co-field">
                    <label for="co-subcity">Sub City</label>
                    <input type="text" id="co-subcity" name="sub_city" value="{{ old('sub_city') }}" class="studio-form-control" placeholder="Area / sub city (optional)">
                </div>
                <div class="zc-co-field">
                    <label for="co-source">Order Source</label>
                    <select id="co-source" name="source" class="studio-form-control">
                        <option value="">Select Source</option>
                        @foreach ($sources as $s)<option value="{{ $s }}" {{ old('source', 'custom') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                </div>
                <div class="zc-co-field">
                    <label for="co-note">Note</label>
                    <textarea id="co-note" name="note" rows="2" class="studio-form-control" placeholder="note">{{ old('note') }}</textarea>
                </div>
            </section>

            {{-- Product Information --}}
            <section class="zc-op-panel p-5">
                <h2 class="studio-section-title" style="text-align:center; margin-bottom:1rem;">Product information</h2>

                <div class="zc-co-field zc-co-search-wrap">
                    <label for="co-search">Scan Barcode || product code</label>
                    <input type="text" id="co-search" class="studio-form-control" placeholder="type product code or name" autocomplete="off"
                           data-search="{{ route('orders.create.products.search') }}">
                    <div class="zc-co-results" id="co-results"></div>
                </div>

                <div class="studio-responsive-scroll">
                    <table class="zc-co-tbl">
                        <thead>
                            <tr><th>#</th><th>Product</th><th>Color</th><th>Quantity</th><th>Price</th><th>Total</th><th>Action</th></tr>
                        </thead>
                        <tbody id="co-items">
                            <tr class="zc-co-empty" id="co-empty"><td colspan="7">No products added yet — search above to add.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="zc-co-summary">
                    <div class="zc-co-sumrow"><span>Total Amount</span><b id="co-total-amount">0</b></div>
                    <div class="zc-co-sumrow"><span>Discount</span><input type="number" step="0.01" min="0" name="discount" value="0" class="studio-form-control" id="co-discount"></div>
                    <div class="zc-co-sumrow"><span>Paid</span><input type="number" step="0.01" min="0" name="paid" value="0" class="studio-form-control" id="co-paid"></div>
                    <div class="zc-co-sumrow"><span>Paid By</span>
                        <select name="paid_by" class="studio-form-control">
                            <option value="">Select</option>
                            @foreach ($accounts as $a)<option value="{{ $a->name }}">{{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="zc-co-sumrow"><span>Shipping charge</span><input type="number" step="0.01" min="0" name="shipping_charge" value="0" class="studio-form-control" id="co-shipping"></div>
                    <div class="zc-co-sumrow zc-co-sumrow--big"><span>Amount due</span><b id="co-amount-due">0</b></div>
                    <div class="zc-co-sumrow"><span>Status</span>
                        <select name="status" class="studio-form-control">
                            @foreach ($statuses as $st)<option value="{{ $st }}" {{ $st === 'pending' ? 'selected' : '' }}>{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div style="text-align:center; margin-top:1.25rem;">
                    <button type="submit" class="studio-command-button studio-command-button--primary" style="padding:0.6rem 2.5rem;">Submit</button>
                </div>
            </section>
        </div>
    </form>

    @push('studio-scripts')
        <script>
            (() => {
                const search = document.getElementById('co-search');
                const results = document.getElementById('co-results');
                const tbody = document.getElementById('co-items');
                const emptyRow = document.getElementById('co-empty');
                const fmt = (n) => Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
                let idx = 0;
                let timer = null;

                const ph = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3C/svg%3E';

                // ---- product search ----
                search.addEventListener('input', () => {
                    clearTimeout(timer);
                    const q = search.value.trim();
                    if (!q) { results.style.display = 'none'; return; }
                    timer = setTimeout(async () => {
                        try {
                            const url = new URL(search.dataset.search, window.location.origin);
                            url.searchParams.set('q', q);
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const data = await res.json();
                            results.innerHTML = (data.results || []).map(p =>
                                `<div class="zc-co-result" data-product='${JSON.stringify(p).replace(/'/g, "&#39;")}'>
                                    ${p.thumb ? `<img src="${p.thumb}" alt="">` : '<span class="zc-co-ph"></span>'}
                                    <div><div class="zc-co-result__name">${p.name}</div><div class="zc-co-result__meta">SKU: ${p.sku} · ৳${fmt(p.price)} · stock ${p.stock}</div></div>
                                </div>`).join('') || '<div class="zc-co-result"><span class="zc-co-result__meta">No products found.</span></div>';
                            results.style.display = 'block';
                        } catch (e) { results.style.display = 'none'; }
                    }, 200);
                });
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.zc-co-search-wrap')) results.style.display = 'none';
                    const r = e.target.closest('.zc-co-result[data-product]');
                    if (r) { addRow(JSON.parse(r.dataset.product)); results.style.display = 'none'; search.value = ''; }
                });

                // ---- add / manage rows ----
                function addRow(p) {
                    emptyRow.style.display = 'none';
                    const i = idx++;
                    const variants = p.variants || [];
                    const firstV = variants[0] || null;
                    const price = firstV ? firstV.price : p.price;
                    const stock = firstV ? firstV.stock : p.stock;
                    const colorCell = variants.length
                        ? `<select class="studio-form-control" name="items[${i}][variant_id]" data-role="variant">
                             ${variants.map(v => `<option value="${v.id}" data-price="${v.price}" data-stock="${v.stock}">${v.label}</option>`).join('')}
                           </select>`
                        : `<span class="zc-op-muted">—</span>`;
                    const tr = document.createElement('tr');
                    tr.dataset.row = i;
                    tr.innerHTML = `
                        <td>${tbody.querySelectorAll('tr[data-row]').length + 1}</td>
                        <td><div class="zc-co-prod">${p.thumb ? `<img src="${p.thumb}">` : '<span class="zc-co-ph"></span>'}<div><div class="zc-op-strong">${p.name}</div><div class="zc-op-muted">${p.sku}</div></div></div>
                            <input type="hidden" name="items[${i}][product_id]" value="${p.id}"></td>
                        <td>${colorCell}</td>
                        <td><input type="number" min="1" value="1" class="studio-form-control zc-co-qty" name="items[${i}][quantity]" data-role="qty"><span class="zc-co-stock" data-role="stock">${stock}</span></td>
                        <td><input type="number" step="0.01" min="0" value="${price}" class="studio-form-control zc-co-price" name="items[${i}][price]" data-role="price"></td>
                        <td data-role="total">${fmt(price)}</td>
                        <td><button type="button" class="zc-co-del" data-role="del"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M7 7l1 13h8l1-13"/></svg></button></td>`;
                    tbody.appendChild(tr);
                    recalcRow(tr);
                    recalcTotals();
                }

                function recalcRow(tr) {
                    const price = parseFloat(tr.querySelector('[data-role=price]').value) || 0;
                    const qty = parseInt(tr.querySelector('[data-role=qty]').value) || 0;
                    tr.querySelector('[data-role=total]').textContent = fmt(price * qty);
                }

                function recalcTotals() {
                    let total = 0;
                    tbody.querySelectorAll('tr[data-row]').forEach((tr, n) => {
                        tr.querySelector('td').textContent = n + 1;
                        const price = parseFloat(tr.querySelector('[data-role=price]').value) || 0;
                        const qty = parseInt(tr.querySelector('[data-role=qty]').value) || 0;
                        total += price * qty;
                    });
                    const discount = parseFloat(document.getElementById('co-discount').value) || 0;
                    const shipping = parseFloat(document.getElementById('co-shipping').value) || 0;
                    const paid = parseFloat(document.getElementById('co-paid').value) || 0;
                    document.getElementById('co-total-amount').textContent = fmt(total);
                    document.getElementById('co-amount-due').textContent = fmt(Math.max(0, total - discount + shipping - paid));
                    if (!tbody.querySelector('tr[data-row]')) emptyRow.style.display = '';
                }

                tbody.addEventListener('input', (e) => {
                    const tr = e.target.closest('tr[data-row]');
                    if (!tr) return;
                    if (e.target.dataset.role === 'variant') {
                        const opt = e.target.selectedOptions[0];
                        tr.querySelector('[data-role=price]').value = opt.dataset.price;
                        tr.querySelector('[data-role=stock]').textContent = opt.dataset.stock;
                    }
                    recalcRow(tr);
                    recalcTotals();
                });
                tbody.addEventListener('change', (e) => {
                    if (e.target.dataset.role === 'variant') {
                        const tr = e.target.closest('tr[data-row]');
                        const opt = e.target.selectedOptions[0];
                        tr.querySelector('[data-role=price]').value = opt.dataset.price;
                        tr.querySelector('[data-role=stock]').textContent = opt.dataset.stock;
                        recalcRow(tr); recalcTotals();
                    }
                });
                tbody.addEventListener('click', (e) => {
                    if (e.target.closest('[data-role=del]')) {
                        e.target.closest('tr[data-row]').remove();
                        recalcTotals();
                    }
                });
                ['co-discount', 'co-shipping', 'co-paid'].forEach(id =>
                    document.getElementById(id).addEventListener('input', recalcTotals));

                document.getElementById('zc-co-form').addEventListener('submit', (e) => {
                    if (!tbody.querySelector('tr[data-row]')) { e.preventDefault(); alert('Add at least one product.'); }
                });
            })();
        </script>
    @endpush
@endsection
