@extends('layouts.studio')

@section('title', 'Add Exchange Order')
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
    <style>
        .zc-ex-result {
            display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
            padding: 0.7rem 0.9rem; border: 1px solid var(--studio-border);
            border-radius: 12px; margin-top: 0.5rem; text-decoration: none; color: var(--studio-text);
        }
        .zc-ex-result:hover { border-color: rgba(212,180,131,0.4); background: rgba(212,180,131,0.05); }
    </style>
@endpush

@section('content')
    @php $money = fn ($value) => number_format((float) $value, 2); @endphp

    <div class="space-y-6">
        <section class="studio-page-hero">
            <div class="studio-page-hero__meta">
                <div>
                    <h1 class="studio-section-title">Add Exchange Order</h1>
                    <p class="studio-section-subtitle">Find the original order, then choose the replacement product to ship in its place.</p>
                </div>
                <a href="{{ route('orders.index') }}" class="studio-command-button">Back to orders</a>
            </div>
        </section>

        @if ($errors->any())
            <div class="studio-callout studio-callout--danger">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $original)
            <section class="zc-op-panel p-5">
                <div class="studio-section-title">1. Find the original order</div>
                <p class="studio-section-subtitle">Search by order number, phone, or customer name.</p>
                <div class="zc-op-field mt-4" style="max-width: 30rem;">
                    <label for="ex-search">Search order</label>
                    <input type="text" id="ex-search" class="studio-form-control" placeholder="Type at least 2 characters…" autocomplete="off"
                           data-search-endpoint="{{ route('orders.exchange.search') }}">
                </div>
                <div id="ex-results" class="mt-2"></div>
            </section>
        @else
            <section class="zc-op-panel p-5">
                <div class="studio-section-title">Original order</div>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="zc-op-muted">Order</div>
                        <div class="zc-op-strong">{{ $original->order_number }}</div>
                        <div class="zc-op-muted mt-2">Customer</div>
                        <div>{{ $original->customer_name }} · {{ $original->customer_phone }}</div>
                    </div>
                    <div>
                        <div class="zc-op-muted">Total</div>
                        <div class="zc-op-strong">৳{{ $money($original->total) }}</div>
                        <div class="zc-op-muted mt-2">Original items</div>
                        <div>
                            @foreach ($original->items as $item)
                                <div style="font-size:0.85rem;">{{ $item->quantity }}× {{ $item->product_name }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <a href="{{ route('orders.exchange.create') }}" class="studio-command-button mt-4">Change order</a>
            </section>

            <section class="zc-op-panel p-5">
                <div class="studio-section-title">2. Replacement product</div>
                <form method="POST" action="{{ route('orders.exchange.store') }}" class="mt-4 space-y-4" style="max-width: 40rem;">
                    @csrf
                    <input type="hidden" name="original_order_id" value="{{ $original->id }}">

                    <div class="zc-op-field">
                        <label for="product_id">Product</label>
                        <select id="product_id" name="product_id" class="studio-form-control" required data-products>
                            <option value="">Select a product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}"
                                        data-price="{{ $product->price }}"
                                        data-variants='@json($product->variants->map(fn ($v) => ["id" => $v->id, "name" => $v->name, "price" => $v->price]))'>
                                    {{ $product->name }} (৳{{ $money($product->price) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="zc-op-field" id="variant-field" style="display:none;">
                        <label for="variant_id">Variant</label>
                        <select id="variant_id" name="variant_id" class="studio-form-control">
                            <option value="">Base product</option>
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="zc-op-field">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="99" class="studio-form-control" required>
                        </div>
                        <div class="zc-op-field">
                            <label for="price">Price override (optional)</label>
                            <input type="number" step="0.01" min="0" id="price" name="price" class="studio-form-control" placeholder="Leave blank for list price">
                        </div>
                    </div>

                    <div class="zc-op-field">
                        <label for="note">Note (optional)</label>
                        <textarea id="note" name="note" rows="2" class="studio-form-control" placeholder="Reason for exchange"></textarea>
                    </div>

                    <button type="submit" class="studio-command-button studio-command-button--primary">Create Exchange Order</button>
                </form>
            </section>
        @endif
    </div>

    @push('studio-scripts')
        <script>
            (() => {
                // Original-order search
                const search = document.getElementById('ex-search');
                const results = document.getElementById('ex-results');
                if (search) {
                    let timer = null;
                    search.addEventListener('input', () => {
                        clearTimeout(timer);
                        const q = search.value.trim();
                        if (q.length < 2) { results.innerHTML = ''; return; }
                        timer = setTimeout(async () => {
                            try {
                                const url = new URL(search.dataset.searchEndpoint, window.location.origin);
                                url.searchParams.set('q', q);
                                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                                const data = await res.json();
                                results.innerHTML = (data.results || []).map(o =>
                                    `<a class="zc-ex-result" href="${new URL(window.location.href).pathname}?order_id=${o.id}">
                                        <span><strong>${o.order_number}</strong><br><span style="color:var(--studio-muted);font-size:0.8rem;">${o.customer_name} · ${o.customer_phone}</span></span>
                                        <span>৳${o.total}</span>
                                    </a>`
                                ).join('') || '<div class="zc-op-muted" style="padding:0.5rem;">No matching orders.</div>';
                            } catch (e) { /* ignore */ }
                        }, 220);
                    });
                }

                // Product → variant population
                const productSelect = document.querySelector('[data-products]');
                const variantField = document.getElementById('variant-field');
                const variantSelect = document.getElementById('variant_id');
                const priceInput = document.getElementById('price');
                if (productSelect) {
                    productSelect.addEventListener('change', () => {
                        const opt = productSelect.selectedOptions[0];
                        const variants = opt ? JSON.parse(opt.dataset.variants || '[]') : [];
                        if (priceInput) priceInput.placeholder = opt?.dataset.price ? ('List: ' + opt.dataset.price) : 'Leave blank for list price';
                        if (variants.length) {
                            variantSelect.innerHTML = '<option value="">Base product</option>' +
                                variants.map(v => `<option value="${v.id}">${v.name} (৳${v.price})</option>`).join('');
                            variantField.style.display = '';
                        } else {
                            variantSelect.innerHTML = '<option value="">Base product</option>';
                            variantField.style.display = 'none';
                        }
                    });
                }
            })();
        </script>
    @endpush
@endsection
