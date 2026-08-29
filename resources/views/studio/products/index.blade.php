@extends('layouts.studio')

@section('title', 'Product Manage')
@section('subtitle', 'Products')

@push('studio-styles')
    @include('studio.orders.partials._styles')
    <style>
        .zc-pm-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .zc-pm-actions { display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center; }
        .zc-pm-toolbar { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap; }

        /* ---------- Stat strip (premium, animated) ---------- */
        .zc-pm-stats { display:grid; grid-template-columns:repeat(2,1fr); gap:0.7rem; }
        @media (min-width:760px){ .zc-pm-stats { grid-template-columns:repeat(4,1fr); } }
        .zc-pm-stat { display:flex; align-items:center; gap:0.75rem; padding:0.8rem 0.95rem; border-radius:15px;
            border:1px solid var(--studio-border); background:var(--studio-surface-soft); text-decoration:none;
            box-shadow:0 22px 55px -44px rgba(16,24,40,0.5); transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            animation:zcPmStat .5s cubic-bezier(.2,.8,.25,1) backwards; }
        a.zc-pm-stat:hover { transform:translateY(-2px); border-color:rgba(212,180,131,0.35); box-shadow:0 26px 60px -40px rgba(16,24,40,0.45); }
        .zc-pm-stats > *:nth-child(1){animation-delay:.04s;} .zc-pm-stats > *:nth-child(2){animation-delay:.10s;}
        .zc-pm-stats > *:nth-child(3){animation-delay:.16s;} .zc-pm-stats > *:nth-child(4){animation-delay:.22s;}
        .zc-pm-stat__ic { width:2.5rem; height:2.5rem; border-radius:12px; display:grid; place-items:center; flex:none; }
        .zc-pm-stat__ic svg { width:1.2rem; height:1.2rem; }
        .zc-pm-stat__v { font-size:1.45rem; font-weight:800; line-height:1; color:var(--studio-text); letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
        .zc-pm-stat__l { font-size:0.73rem; font-weight:700; color:var(--studio-muted); margin-top:4px; }
        .zc-pm-stat .i-leaf { background:rgba(28,138,78,0.14); color:#1c8a4e; }
        .zc-pm-stat .i-blue { background:rgba(59,110,165,0.14); color:#3b6ea5; }
        .zc-pm-stat .i-amber { background:rgba(201,147,15,0.16); color:#a9793f; }
        .zc-pm-stat .i-red { background:rgba(192,57,43,0.14); color:#c0392b; }
        @keyframes zcPmStat { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }
        @media (prefers-reduced-motion:reduce){ .zc-pm-stat { animation:none; } }
        .zc-pm-show { display:flex; align-items:center; gap:0.5rem; color:var(--studio-muted); font-size:0.82rem; }
        .zc-pm-search { display:flex; gap:0.5rem; }

        /* Strip the design-system frame around the scroll container. */
        .zc-pm-tablewrap .studio-responsive-scroll { border:none !important; background:transparent !important; box-shadow:none !important; border-radius:0 !important; }

        /* Card rows — every row one opaque dark-blue colour (no hover change),
           with a colourful animated border cycling gold → blue → green → purple. */
        @keyframes zc-pm-border {
            0%   { border-color: rgba(201,162,74,0.62); }
            20%  { border-color: rgba(107,143,214,0.62); }
            40%  { border-color: rgba(95,165,120,0.62); }
            60%  { border-color: rgba(166,121,201,0.62); }
            80%  { border-color: rgba(217,138,154,0.62); }
            100% { border-color: rgba(201,162,74,0.62); }
        }
        .zc-pm-tbl { width:100%; border-collapse:separate; border-spacing:0 0.5rem; }
        .zc-pm-tbl th { text-align:left; font-size:0.66rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; color:#667085; padding:0.5rem 0.9rem; background:transparent; border:none; white-space:nowrap; }
        .zc-pm-tbl tbody tr, main .zc-pm-tbl tbody tr:hover { background:transparent !important; }
        .zc-pm-tbl tbody td, main .zc-pm-tbl tbody tr:hover td {
            padding:0.75rem 0.9rem; vertical-align:middle; font-size:0.82rem; color:var(--studio-text);
            background:#ffffff !important;
            border-top:1px solid rgba(212,180,131,0.4); border-bottom:1px solid rgba(212,180,131,0.4);
            animation: zc-pm-border 8s linear infinite;
        }
        .zc-pm-tbl tbody td:first-child { border-left:1px solid rgba(212,180,131,0.4); border-top-left-radius:12px; border-bottom-left-radius:12px; }
        .zc-pm-tbl tbody td:last-child { border-right:1px solid rgba(212,180,131,0.4); border-top-right-radius:12px; border-bottom-right-radius:12px; }
        @media (prefers-reduced-motion: reduce) { .zc-pm-tbl tbody td { animation:none; } }

        .zc-pm-prod { display:flex; align-items:center; gap:0.75rem; }
        .zc-pm-thumb { width:52px; height:52px; border-radius:10px; object-fit:cover; border:1px solid var(--studio-border); background:var(--studio-surface-soft); flex:none; }
        .zc-pm-name { font-weight:700; color:var(--studio-text); }
        .zc-pm-sku { font-size:0.74rem; color:var(--studio-muted); }
        /* Real (scannable) Code 39 barcode on a crisp white plate. */
        .zc-pm-barcode { margin-top:0.35rem; width:9.5rem; padding:3px 5px; border-radius:4px; background:#fff; }
        .zc-pm-barcode svg { width:100%; height:26px; }

        /* Premium 3-dot action menu */
        .zc-pm-dots { list-style:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
            width:2rem; height:2rem; border-radius:8px; border:1px solid var(--studio-border); background:var(--studio-surface-soft); color:#5fd39a; transition:all .15s ease; }
        .zc-pm-dots::-webkit-details-marker { display:none; }
        .zc-pm-dots:hover { border-color:rgba(95,211,154,0.5); background:rgba(95,211,154,0.12); }
        .zc-pm-dots svg { width:1.1rem; height:1.1rem; }
        .zc-pm-pop { position:relative; }
        /* Portaled to <body> on open (see studio-scripts below) so the menu
           escapes .studio-responsive-scroll's overflow-x:auto clipping —
           without this, the action menu on the last (rightmost) column gets
           cut off or is entirely inaccessible once the table scrolls
           horizontally, which is exactly what happens on mobile. */
        .zc-pm-menu {
            --studio-surface:#ffffff; --studio-surface-soft:#f7f9fc;
            --studio-border:#e7ebf1; --studio-text:#0f172a; --studio-muted:#64748b;
            position:fixed; z-index:80; width:12rem; padding:0.5rem;
            max-height:min(74vh, 32rem); overflow-y:auto; overscroll-behavior:contain;
            display:grid; gap:0.4rem; border-radius:14px; border:1px solid #e7ebf1;
            background:#ffffff; box-shadow:0 2px 4px rgba(16,24,40,0.04), 0 26px 60px -22px rgba(16,24,40,0.32);
            transform-origin:top right; }
        .zc-pm-menu.is-left { transform-origin:top left; }
        .zc-pm-menu.is-up { transform-origin:bottom right; }
        .zc-pm-menu.is-up.is-left { transform-origin:bottom left; }
        .zc-pm-menu.is-opening { animation:zc-pm-pop-in .18s cubic-bezier(.33,1.3,.5,1); }
        .zc-pm-menu.is-opening.is-up { animation-name:zc-pm-pop-in-up; }
        @keyframes zc-pm-pop-in { from { opacity:0; transform:translateY(-7px) scale(0.96); } to { opacity:1; transform:none; } }
        @keyframes zc-pm-pop-in-up { from { opacity:0; transform:translateY(7px) scale(0.96); } to { opacity:1; transform:none; } }
        .no-js .zc-pm-menu { position:absolute; right:0; top:100%; }
        .zc-pm-menu form { margin:0; }
        .zc-pm-mbtn { display:block; width:100%; text-align:center; padding:0.5rem 0.6rem; border-radius:9px; border:none;
            font-size:0.82rem; font-weight:800; cursor:pointer; text-decoration:none; }
        .zc-pm-mbtn--warn   { background:#efd07f; color:#4a3a12; }
        .zc-pm-mbtn--blue   { background:#bfe0e8; color:#1c4650; }
        .zc-pm-mbtn--purple { background:#cfc7ee; color:#3a2f6b; }
        .zc-pm-mbtn--red    { background:#e6a9a9; color:#6b1f1f; }
        .zc-pm-mbtn:hover   { filter:brightness(1.05); }

        .zc-pm-pos { display:inline-flex; align-items:center; justify-content:center; min-width:2.6rem; padding:0.2rem 0.5rem; border-radius:999px; background:rgba(224,90,74,0.14); color:#c0392b; font-weight:800; font-size:0.78rem; }
        .zc-pm-price { display:grid; gap:0.12rem; font-size:0.78rem; }
        .zc-pm-price span { color:var(--studio-muted); }
        .zc-pm-price b { color:var(--studio-text); }
        .zc-pm-stock { font-weight:800; color:#3b6ea5; font-variant-numeric:tabular-nums; }

        /* Filter hover dropdown (same pattern as orders) */
        .zc-pm-filter { position:relative; }
        .zc-pm-filter-menu { position:absolute; right:0; top:100%; z-index:40; padding-top:0.5rem; width:18rem; opacity:0; visibility:hidden; transform:translateY(-6px); transition:opacity .16s, transform .16s, visibility .16s; }
        .zc-pm-filter-menu::before { content:''; position:absolute; top:0; left:0; right:0; height:0.6rem; }
        .zc-pm-filter:hover .zc-pm-filter-menu, .zc-pm-filter:focus-within .zc-pm-filter-menu { opacity:1; visibility:visible; transform:translateY(0); }
        .zc-pm-filter-form { display:grid; gap:0.6rem; padding:0.9rem; border-radius:14px; border:1px solid var(--studio-border); background:var(--studio-surface); box-shadow:0 26px 60px -28px rgba(16,24,40,0.28); }
        .zc-pm-fl { display:grid; gap:0.25rem; }
        .zc-pm-fl > span { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; color:var(--studio-muted); }
    </style>
@endpush

@section('content')
    <div class="space-y-5">
        <div class="zc-pm-head">
            <div class="zc-pm-actions">
                <a href="{{ route('products.create') }}" class="studio-command-button studio-command-button--primary">+ Add</a>
            </div>
            <h1 class="studio-section-title" style="text-align:center; flex:1;">Product Manage</h1>
            <div class="zc-pm-actions">
                <a href="{{ route('products.index', ['stock' => 'low']) }}" class="studio-command-button" style="background:linear-gradient(135deg,#e0685a,#c0392b); color:#fff; border:none;">Low Stock Report</a>
                <div class="zc-pm-filter">
                    <button type="button" class="studio-command-button studio-command-button--primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:1rem;height:1rem;"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg> Filter
                    </button>
                    <div class="zc-pm-filter-menu">
                        <form method="GET" action="{{ route('products.index') }}" class="zc-pm-filter-form">
                            <label class="zc-pm-fl"><span>Category</span>
                                <select name="category_id" class="studio-form-control"><option value="">All categories</option>
                                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string)($filters['category_id'] ?? '') === (string)$c->id)>{{ $c->name }}</option>@endforeach
                                </select>
                            </label>
                            <label class="zc-pm-fl"><span>Status</span>
                                <select name="published" class="studio-form-control"><option value="">All</option>
                                    <option value="active" @selected(($filters['published'] ?? '') === 'active')>Publish</option>
                                    <option value="inactive" @selected(($filters['published'] ?? '') === 'inactive')>Unpublished</option>
                                </select>
                            </label>
                            <label class="zc-pm-fl"><span>Stock</span>
                                <select name="stock" class="studio-form-control"><option value="">All</option>
                                    <option value="in" @selected(($filters['stock'] ?? '') === 'in')>In stock</option>
                                    <option value="low" @selected(($filters['stock'] ?? '') === 'low')>Low stock</option>
                                    <option value="out" @selected(($filters['stock'] ?? '') === 'out')>Out of stock</option>
                                </select>
                            </label>
                            <div style="display:flex; gap:0.5rem;">
                                <button type="submit" class="studio-command-button studio-command-button--primary" style="flex:1; justify-content:center;">Apply</button>
                                <a href="{{ route('products.index') }}" class="studio-command-button">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="zc-pm-stats">
            <div class="zc-pm-stat">
                <span class="zc-pm-stat__ic i-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 8v9a1 1 0 0 1-.6.9l-6.6 3a2 2 0 0 1-1.6 0l-6.6-3A1 1 0 0 1 4 17V8"/><path d="M2.5 7 12 3l9.5 4-9.5 4z"/></svg></span>
                <div><div class="zc-pm-stat__v" data-countup>{{ number_format($totalProducts ?? 0) }}</div><div class="zc-pm-stat__l">Total products</div></div>
            </div>
            <a href="{{ route('products.index', ['published' => 'active']) }}" class="zc-pm-stat">
                <span class="zc-pm-stat__ic i-leaf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                <div><div class="zc-pm-stat__v" data-countup>{{ number_format($publishedCount ?? 0) }}</div><div class="zc-pm-stat__l">Published</div></div>
            </a>
            <a href="{{ route('products.index', ['stock' => 'low']) }}" class="zc-pm-stat">
                <span class="zc-pm-stat__ic i-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
                <div><div class="zc-pm-stat__v" data-countup>{{ number_format($lowStockCount ?? 0) }}</div><div class="zc-pm-stat__l">Low stock</div></div>
            </a>
            <a href="{{ route('products.index', ['stock' => 'out']) }}" class="zc-pm-stat">
                <span class="zc-pm-stat__ic i-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg></span>
                <div><div class="zc-pm-stat__v" data-countup>{{ number_format($outStockCount ?? 0) }}</div><div class="zc-pm-stat__l">Out of stock</div></div>
            </a>
        </div>

        <div class="zc-pm-toolbar">
            <form method="GET" action="{{ route('products.index') }}" class="zc-pm-show">
                @foreach ($filters as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <span>Show</span>
                <select name="per_page" class="studio-form-control" style="width:auto;" onchange="this.form.submit()">
                    @foreach ($perPageOptions as $opt)<option value="{{ $opt }}" @selected($perPage === $opt)>{{ $opt }}</option>@endforeach
                </select>
            </form>
            <form method="GET" action="{{ route('products.index') }}" class="zc-pm-search">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="studio-form-control" placeholder="search by product name || code" style="min-width:18rem;">
                <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
            </form>
        </div>

        <section class="zc-pm-tablewrap">
            <div class="studio-responsive-scroll">
                <table class="zc-pm-tbl">
                    <thead>
                        <tr><th>#</th><th>Product Name</th><th>Position</th><th>Status</th><th>Price</th><th>Stock</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $money = fn ($v) => number_format((float) $v, 0);
                                $list = (float) ($product->compare_price ?: $product->price);
                                $discount = $product->compare_price && $product->compare_price > $product->price ? (float) $product->compare_price - (float) $product->price : 0;
                                $thumb = $mediaUrl($product->thumbnail);
                                $stock = $product->effective_stock ?? $product->stock;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration + ($products->currentPage() - 1) * $products->perPage() }}</td>
                                <td>
                                    <div class="zc-pm-prod">
                                        <img class="zc-pm-thumb" src="{{ $thumb ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2252%22 height=%2252%22%3E%3C/svg%3E' }}" alt="" loading="lazy">
                                        <div>
                                            <div class="zc-pm-name">{{ $product->name }}</div>
                                            <div class="zc-pm-sku">SKU : {{ $product->sku }}</div>
                                            <div class="zc-pm-barcode" title="{{ $product->sku }}">{!! \App\Modules\Shared\Support\Barcode::code39Svg($product->sku, 30) !!}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="zc-pm-pos">{{ $product->id }}</span></td>
                                <td><span class="studio-badge {{ $product->status === 'active' ? 'studio-badge--success' : 'studio-badge--neutral' }}">{{ $product->status === 'active' ? 'Publish' : 'Unpublished' }}</span></td>
                                <td>
                                    <div class="zc-pm-price">
                                        <div><span>Price :</span> <b>{{ $money($list) }}</b></div>
                                        <div><span>Discount :</span> <b>{{ $money($discount) }}</b></div>
                                        <div><span>Sale Price :</span> <b>{{ $money($product->price) }}</b></div>
                                        <div><span>Cost :</span> <b>{{ $money($product->cost_price) }}</b></div>
                                    </div>
                                </td>
                                <td><span class="zc-pm-stock">{{ number_format((int) $stock) }}</span></td>
                                <td>
                                    <details class="zc-pm-pop">
                                        <summary class="zc-pm-dots" title="Actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg></summary>
                                        <div class="zc-pm-menu">
                                            <form method="POST" action="{{ route('products.toggle-status', $product) }}">@csrf
                                                <button type="submit" class="zc-pm-mbtn zc-pm-mbtn--warn">{{ $product->status === 'active' ? 'Unpublish' : 'Publish' }}</button>
                                            </form>
                                            <a href="{{ route('products.edit', $product) }}" class="zc-pm-mbtn zc-pm-mbtn--blue">Edit</a>
                                            <form method="POST" action="{{ route('products.duplicate', $product) }}">@csrf
                                                <button type="submit" class="zc-pm-mbtn zc-pm-mbtn--purple">Copy Product</button>
                                            </form>
                                            <a href="{{ route('products.export-customers', $product) }}" class="zc-pm-mbtn zc-pm-mbtn--blue">Export Customers</a>
                                            <a href="{{ route('products.print', $product) }}" target="_blank" rel="noopener" class="zc-pm-mbtn zc-pm-mbtn--blue">Print</a>
                                            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Delete this product?');">@csrf @method('DELETE')
                                                <button type="submit" class="zc-pm-mbtn zc-pm-mbtn--red">Delete</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="zc-op-empty">No products match these filters.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="p-4">{{ $products->appends($filters + ['per_page' => $perPage])->links() }}</div>
            @endif
        </section>
    </div>

    @if (session('success'))
        <div class="studio-callout studio-callout--success" style="margin-top:1rem;">{{ session('success') }}</div>
    @endif

    @push('studio-scripts')
        <script>
            // Action menu: portaled to <body> while open so it escapes the
            // table's horizontal-scroll clipping (see .zc-pm-menu CSS above)
            // — otherwise the last column's dropdown is unreachable on mobile.
            (function () {
                const positionPop = (det) => {
                    const summary = det.querySelector('summary');
                    const menu = det.__menu;
                    if (!summary || !menu) return;
                    const gap = 6, pad = 8;
                    const vw = document.documentElement.clientWidth, vh = window.innerHeight;
                    const r = summary.getBoundingClientRect();
                    const mw = menu.offsetWidth, mh = menu.offsetHeight;
                    let left = menu.classList.contains('is-left') ? r.left : (r.right - mw);
                    left = Math.max(pad, Math.min(left, vw - mw - pad));
                    const roomBelow = vh - r.bottom - gap - pad;
                    const roomAbove = r.top - gap - pad;
                    let top, up = false;
                    if (mh <= roomBelow || roomBelow >= roomAbove) { top = r.bottom + gap; }
                    else { up = true; top = r.top - gap - mh; }
                    top = Math.max(pad, Math.min(top, vh - mh - pad));
                    menu.classList.toggle('is-up', up);
                    menu.style.left = left + 'px';
                    menu.style.top = top + 'px';
                };
                const openPop = (det) => {
                    const menu = det.__menu || det.querySelector('.zc-pm-menu');
                    if (!menu) return;
                    det.__menu = menu; menu.__owner = det;
                    if (menu.parentElement !== document.body) document.body.appendChild(menu);
                    positionPop(det);
                    menu.classList.add('is-opening');
                    menu.addEventListener('animationend', () => menu.classList.remove('is-opening'), { once: true });
                };
                const closePop = (det) => {
                    if (!det) return;
                    const menu = det.__menu;
                    if (menu && menu.parentElement === document.body) {
                        if (det.isConnected) det.appendChild(menu); else menu.remove();
                    }
                    if (det.hasAttribute('open')) det.removeAttribute('open');
                };
                const closeAllPops = (except) => {
                    document.querySelectorAll('details.zc-pm-pop[open]').forEach((d) => { if (d !== except) closePop(d); });
                    document.querySelectorAll('body > .zc-pm-menu').forEach((m) => {
                        if (m.__owner && m.__owner !== except) closePop(m.__owner);
                        else if (!m.__owner) m.remove();
                    });
                };

                document.addEventListener('toggle', (e) => {
                    const det = e.target;
                    if (!det.matches || !det.matches('details.zc-pm-pop')) return;
                    if (det.open) { closeAllPops(det); openPop(det); } else { closePop(det); }
                }, true);
                document.addEventListener('click', (e) => {
                    const sum = e.target.closest && e.target.closest('details.zc-pm-pop > summary');
                    if (!sum) return;
                    const det = sum.parentElement;
                    requestAnimationFrame(() => { if (det.open) { closeAllPops(det); openPop(det); } else { closePop(det); } });
                }, true);

                document.addEventListener('click', (e) => {
                    if (e.target.closest('.zc-pm-pop') || e.target.closest('.zc-pm-menu')) return;
                    closeAllPops(null);
                });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAllPops(null); });

                const repositionOpenPops = () => document.querySelectorAll('details.zc-pm-pop[open]').forEach(positionPop);
                window.addEventListener('scroll', (e) => {
                    const t = e.target;
                    if (t && t.nodeType === 1 && t.closest && t.closest('.zc-pm-menu')) return;
                    repositionOpenPops();
                }, true);
                window.addEventListener('resize', repositionOpenPops);
            })();

            // Count the stat numbers up from zero on load.
            (function () {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                document.querySelectorAll('.zc-pm-stat__v[data-countup]').forEach(function (el) {
                    var txt = el.textContent.trim();
                    if (!/^[0-9,]+$/.test(txt)) return;
                    var target = parseInt(txt.replace(/,/g, ''), 10);
                    if (!target) return;
                    var dur = 800, t0 = null;
                    el.textContent = '0';
                    function step(ts) {
                        if (!t0) t0 = ts;
                        var p = Math.min(1, (ts - t0) / dur);
                        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('en-US');
                        if (p < 1) requestAnimationFrame(step); else el.textContent = target.toLocaleString('en-US');
                    }
                    requestAnimationFrame(step);
                });
            })();
        </script>
    @endpush
@endsection
