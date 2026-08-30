@extends('layouts.studio')

@php $isEdit = $product->exists; @endphp
@section('title', $isEdit ? 'Edit Product' : 'Add Product')
@section('subtitle', 'Products')

@push('studio-styles')
    @include('studio.orders.partials._styles')
    <style>
        .zc-pf-wrap { max-width: 62rem; margin: 0 auto; }
        .zc-pf-card { margin-bottom: 1.25rem; }
        .zc-pf-title { text-align: center; }
        .zc-pf-sub { text-align: center; color: var(--studio-muted); font-size: 0.82rem; margin-bottom: 1.25rem; }
        .zc-pf-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 640px){ .zc-pf-2 { grid-template-columns:1fr 1fr; } .zc-pf-3 { grid-template-columns:1fr 1fr 1fr; } }
        .zc-pf-field { display:grid; gap:0.3rem; }
        .zc-pf-field > label { font-size:0.78rem; font-weight:700; color:var(--studio-muted); }
        .req { color:#dc2626; }
        .zc-pf-cat { display:flex; gap:0.5rem; align-items:flex-end; }
        .zc-pf-catadd { flex:none; width:2.75rem; height:2.75rem; border-radius:13px; border:none; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(145deg,#4bd090,#2aa564 55%,#1c8a4e); color:#fff; font-size:1.4rem; font-weight:700; line-height:1; text-decoration:none; cursor:pointer; }

        .zc-pf-attrs { display:grid; grid-template-columns:repeat(auto-fill, minmax(8rem,1fr)); gap:0.5rem 0.75rem; margin-top:0.75rem; }
        .zc-pf-attr { display:flex; align-items:center; gap:0.45rem; font-size:0.82rem; color:var(--studio-text); }
        .zc-pf-attr input { width:1rem; height:1rem; accent-color:#c79a3b; }

        .zc-pf-vtbl { width:100%; border-collapse:collapse; margin-top:1rem; }
        .zc-pf-vtbl th { text-align:left; font-size:0.66rem; text-transform:uppercase; letter-spacing:0.06em; color:var(--studio-muted); padding:0.5rem 0.6rem; border-bottom:1px solid var(--studio-border); }
        .zc-pf-vtbl td { padding:0.5rem 0.6rem; border-bottom:1px solid var(--studio-border); font-size:0.82rem; vertical-align:middle; }
        .zc-pf-vprice { width:7rem; }
        .zc-pf-vnum { color:var(--studio-muted); width:2rem; }
        .zc-pf-vname { font-weight:700; color:var(--studio-text); }
        .zc-pf-vimg { display:flex; align-items:center; gap:0.5rem; }
        .zc-pf-vpreview { position:relative; display:inline-block; flex:none; }
        .zc-pf-vthumb { display:block; width:56px; height:56px; border-radius:10px; object-fit:cover; border:1px solid var(--studio-border); box-shadow:0 8px 20px -14px rgba(0,0,0,0.5); }
        .zc-pf-vrm { position:absolute; top:-8px; right:-8px; width:1.35rem; height:1.35rem; border-radius:999px; border:2px solid var(--studio-surface); background:#dc2626; color:#fff; font-size:0.85rem; line-height:1; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 4px 10px -3px rgba(220,38,38,0.7); }
        .zc-pf-vrm:hover { background:#b91c1c; }
        .zc-pf-vfile { max-width:14rem; font-size:0.75rem; }
        .zc-pf-vdel { display:inline-flex; align-items:center; justify-content:center; width:1.9rem; height:1.9rem; border-radius:7px; border:none; background:linear-gradient(135deg,#e0685a,#c0392b); color:#fff; cursor:pointer; }

        .zc-pf-imgs { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px,1fr)); gap:0.85rem; margin-top:0.75rem; }
        .zc-pf-slot { text-align:center; }
        .zc-pf-drop { position:relative; aspect-ratio:1; border:1px dashed var(--studio-border-strong); border-radius:12px; background:var(--studio-surface-soft); overflow:hidden; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .zc-pf-drop img { width:100%; height:100%; object-fit:cover; }
        .zc-pf-drop .zc-pf-plus { font-size:1.6rem; color:var(--studio-muted); }
        .zc-pf-drop input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }
        .zc-pf-slotlabel { font-size:0.72rem; color:var(--studio-muted); margin-top:0.3rem; }
        .zc-pf-existing { position:relative; }
        .zc-pf-rm { position:absolute; top:-6px; right:-6px; width:1.4rem; height:1.4rem; border-radius:999px; border:none; background:#c0392b; color:#fff; font-size:0.8rem; cursor:pointer; z-index:2; }

        /* Premium rich text editor */
        .zc-rt-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:0.28rem; padding:0.5rem; border:1px solid var(--studio-border); border-bottom:none; border-radius:12px 12px 0 0; background:linear-gradient(180deg,#fbfcfe,#f3f6fb); }
        .zc-rt-sep { width:1px; align-self:stretch; margin:0.2rem 0.25rem; background:var(--studio-border); }
        .zc-rt-select { height:2.05rem; padding:0 0.55rem; border-radius:8px; border:1px solid var(--studio-border); background:#fff; color:var(--studio-text); font-size:0.8rem; font-weight:700; cursor:pointer; }
        .zc-rt-btn { display:inline-flex; align-items:center; justify-content:center; min-width:2.05rem; height:2.05rem; padding:0 0.5rem; border-radius:8px; border:1px solid var(--studio-border); background:#fff; color:#334155; font-weight:800; font-size:0.9rem; cursor:pointer; transition:transform .14s ease, background .14s ease, border-color .14s ease, color .14s ease; position:relative; }
        .zc-rt-btn svg { width:1.05rem; height:1.05rem; }
        .zc-rt-btn:hover { border-color:rgba(201,162,74,0.55); background:rgba(201,162,74,0.09); color:#111827; transform:translateY(-1px); }
        .zc-rt-btn.is-active { border-color:rgba(201,162,74,0.7); background:rgba(201,162,74,0.18); color:#8a5f2c; }
        .zc-rt-color { flex-direction:column; gap:0; overflow:hidden; }
        .zc-rt-color-bar { position:absolute; left:5px; right:5px; bottom:4px; height:3px; border-radius:2px; background:#111827; }
        .zc-rt-color input[type=color] { position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
        .zc-rt-editor { min-height:14rem; padding:1rem 1.15rem; border:1px solid var(--studio-border); border-radius:0 0 12px 12px; background:var(--studio-surface); color:var(--studio-text); font-size:0.92rem; line-height:1.7; overflow:auto; }
        .zc-rt-editor:focus { outline:none; border-color:rgba(201,162,74,0.55); box-shadow:0 0 0 3px rgba(201,162,74,0.12); }
        .zc-rt-editor:empty::before { content:attr(data-placeholder); color:var(--studio-muted); pointer-events:none; }
        .zc-rt-editor h1 { font-size:1.6rem; font-weight:800; margin:0.6rem 0; }
        .zc-rt-editor h2 { font-size:1.3rem; font-weight:800; margin:0.55rem 0; }
        .zc-rt-editor h3 { font-size:1.1rem; font-weight:700; margin:0.5rem 0; }
        .zc-rt-editor p { margin:0.35rem 0; }
        .zc-rt-editor ul { list-style:disc; padding-left:1.5rem; margin:0.4rem 0; }
        .zc-rt-editor ol { list-style:decimal; padding-left:1.5rem; margin:0.4rem 0; }
        .zc-rt-editor blockquote { margin:0.6rem 0; padding:0.4rem 0.95rem; border-left:3px solid rgba(201,162,74,0.65); background:rgba(201,162,74,0.07); color:#475467; border-radius:0 8px 8px 0; }
        .zc-rt-editor pre { margin:0.6rem 0; padding:0.8rem 0.95rem; border-radius:9px; background:#0f172a; color:#e2e8f0; font-family:ui-monospace,Menlo,monospace; font-size:0.85rem; overflow:auto; white-space:pre-wrap; }
        .zc-rt-editor a { color:#2563eb; text-decoration:underline; }
        .zc-rt-editor img { max-width:100%; border-radius:9px; }
        .zc-rt-embed { position:relative; padding-bottom:56.25%; height:0; margin:0.6rem 0; }
        .zc-rt-embed iframe { position:absolute; inset:0; width:100%; height:100%; border:0; border-radius:9px; }
        .zc-rt-editor video { max-width:100%; border-radius:9px; }

        /* ---------- Premium polish ---------- */
        .zc-pf-card {
            position:relative; overflow:hidden; border-radius:20px !important;
            background:
                radial-gradient(circle at 100% 0%, rgba(212,180,131,0.06), transparent 42%),
                linear-gradient(180deg, rgba(255,253,247,0.02), rgba(255,253,247,0)) !important;
            background-color: var(--studio-surface) !important;
            box-shadow: 0 1px 2px rgba(16,24,40,0.04), 0 20px 50px -40px rgba(16,24,40,0.35) !important;
        }
        .zc-pf-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px;
            background:linear-gradient(90deg, transparent, rgba(212,180,131,0.55), transparent); }
        .zc-pf-card h2.studio-section-title { letter-spacing:0.01em; }
        .zc-pf-field > label { text-transform:uppercase; letter-spacing:0.04em; font-size:0.7rem; }

        .zc-pf-drop { transition: border-color .18s ease, background .18s ease, box-shadow .18s ease; }
        .zc-pf-drop:hover { border-color: rgba(212,180,131,0.6); background: rgba(212,180,131,0.06); box-shadow: 0 14px 30px -20px rgba(212,180,131,0.5); }
        .zc-pf-drop .zc-pf-plus { transition: transform .18s ease, color .18s ease; }
        .zc-pf-drop:hover .zc-pf-plus { transform: scale(1.18); color:#e0bd7d; }

        .zc-pf-attr { padding:0.35rem 0.5rem; border-radius:8px; transition: background .15s ease; position:relative; }
        .zc-pf-attr:hover { background: rgba(212,180,131,0.08); }
        .zc-pf-attr-lbl { display:flex; align-items:center; gap:0.45rem; cursor:pointer; flex:1; min-width:0; }
        .zc-pf-attr-x { flex:none; width:1.05rem; height:1.05rem; border:none; border-radius:999px; background:var(--studio-border); color:var(--studio-muted); font-size:0.85rem; line-height:1; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; opacity:0.35; transition:opacity .15s ease, background .15s ease, color .15s ease; }
        .zc-pf-attr:hover .zc-pf-attr-x { opacity:1; }
        .zc-pf-attr-x:hover { background:#dc2626; color:#fff; }
        .zc-pf-add { display:flex; gap:0.5rem; margin-top:0.6rem; max-width:22rem; }
        .zc-pf-add .studio-form-control { flex:1; }
        .zc-pf-vsku { width:8rem; text-transform:uppercase; font-size:0.78rem; }

        /* Add-ons picker */
        .zc-pf-ad-pick { position:relative; max-width:26rem; }
        .zc-pf-ad-results { position:absolute; z-index:20; left:0; right:0; top:calc(100% + 4px); background:var(--studio-surface); border:1px solid var(--studio-border); border-radius:12px; box-shadow:0 20px 40px -20px rgba(0,0,0,.35); max-height:280px; overflow:auto; display:none; }
        .zc-pf-ad-results.show { display:block; }
        .zc-pf-ad-res { display:flex; align-items:center; gap:0.7rem; padding:0.55rem 0.8rem; cursor:pointer; border-bottom:1px solid var(--studio-border); }
        .zc-pf-ad-res:hover { background:var(--studio-surface-soft); }
        .zc-pf-ad-res img, .zc-pf-ad-res .ph, .zc-pf-ad-item img, .zc-pf-ad-item .ph { width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--studio-border); flex:none; background:var(--studio-surface-soft); }
        .zc-pf-ad-res .m, .zc-pf-ad-item .m { min-width:0; flex:1; }
        .zc-pf-ad-res .m b, .zc-pf-ad-item .m b { display:block; font-size:0.82rem; color:var(--studio-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .zc-pf-ad-res .m span, .zc-pf-ad-item .m span { font-size:0.72rem; color:var(--studio-muted); }
        .zc-pf-ad-list { display:grid; gap:0.6rem; margin-top:0.9rem; max-width:34rem; }
        .zc-pf-ad-item { display:flex; align-items:center; gap:0.7rem; padding:0.55rem 0.7rem; border:1px solid var(--studio-border); border-radius:11px; background:var(--studio-surface-soft); }
        .zc-pf-ad-price { width:8rem; }
        .zc-pf-ad-rm { border:none; background:none; color:#e0483a; cursor:pointer; font-size:1.1rem; line-height:1; padding:0.2rem; }
        .zc-pf-ad-empty { color:var(--studio-muted); font-size:0.82rem; font-style:italic; padding:0.5rem 0; }

        .zc-pf-catadd { box-shadow:inset 0 1px 0 rgba(255,255,255,0.4), inset 0 -2px 4px rgba(0,0,0,0.12), 0 12px 24px -10px rgba(28,138,78,0.65); transition:transform .16s ease, box-shadow .16s ease, filter .16s ease; }
        .zc-pf-catadd:hover { transform:translateY(-2px) scale(1.04); filter:brightness(1.05); box-shadow:inset 0 1px 0 rgba(255,255,255,0.45), inset 0 -2px 4px rgba(0,0,0,0.12), 0 18px 32px -10px rgba(28,138,78,0.8); }
        .zc-pf-catadd:active { transform:translateY(0) scale(0.98); }

        #pf-sale { font-weight:800; color:#a9793f; }

        #zc-pf-form button[type=submit] {
            background:linear-gradient(135deg,#f8ecc9 0%,#e0bd7d 45%,#a9793f 100%) !important; color:#1a1408 !important;
            border:none !important; box-shadow:0 18px 40px -18px rgba(212,180,131,0.6); font-weight:900 !important; letter-spacing:0.03em;
        }
    </style>
@endpush

@section('content')
    @php
        $listPrice = old('list_price', $isEdit ? ($product->compare_price ?: $product->price) : '');
        $discount = old('discount', $isEdit && $product->compare_price > $product->price ? (float) $product->compare_price - (float) $product->price : 0);
        $existingVariants = $isEdit ? $product->variants : collect();
        $existingLabels = $existingVariants->pluck('name')->all();
    @endphp

    <div class="zc-pf-wrap">
        <div style="margin-bottom:1rem;"><a href="{{ route('products.index') }}" class="studio-command-button">← Back</a></div>

        @if ($errors->any())
            <div class="studio-callout studio-callout--danger" style="margin-bottom:1rem;"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" id="zc-pf-form">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <input type="hidden" name="category_id" id="pf-category-id" value="{{ old('category_id', $product->category_id) }}">
            <input type="hidden" name="description" id="pf-description-input" value="{{ old('description', $product->description) }}">

            {{-- Basic Information --}}
            <section class="zc-op-panel p-5 zc-pf-card">
                <h2 class="studio-section-title zc-pf-title">Basic Information</h2>
                <p class="zc-pf-sub">Having accurate product information raises discoverability.</p>

                <div class="zc-pf-grid">
                    <div class="zc-pf-field"><label>Product Name <span class="req">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" class="studio-form-control" placeholder="Ex: formal shirt" required></div>

                    <div class="zc-pf-grid zc-pf-2">
                        <div class="zc-pf-field"><label>Product Code (SKU)</label>
                            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" class="studio-form-control" placeholder="Auto-generated if blank"></div>
                        <div class="zc-pf-field"><label>Video URL</label>
                            <input type="text" name="video_url" value="{{ old('video_url', $product->video_url) }}" class="studio-form-control" placeholder="Youtube video link"></div>
                    </div>

                    <div class="zc-pf-grid zc-pf-3">
                        <div class="zc-pf-field"><label>Category <span class="req">*</span></label>
                            <div class="zc-pf-cat">
                                <select id="pf-cat-1" class="studio-form-control" data-catlevel="1"><option value="">Select Category</option></select>
                                <a href="#" class="zc-pf-catadd" title="Category management" onclick="return false;">+</a>
                            </div>
                        </div>
                        <div class="zc-pf-field"><label>Sub Category</label>
                            <select id="pf-cat-2" class="studio-form-control" data-catlevel="2"><option value="">Select Sub Category</option></select></div>
                        <div class="zc-pf-field"><label>Sub Sub Category</label>
                            <select id="pf-cat-3" class="studio-form-control" data-catlevel="3"><option value="">Select Sub Sub Category</option></select></div>
                    </div>

                    <div class="zc-pf-grid zc-pf-3">
                        <div class="zc-pf-field"><label>Price <span class="req">*</span></label>
                            <input type="number" step="0.01" min="0" name="list_price" id="pf-price" value="{{ $listPrice }}" class="studio-form-control" required></div>
                        <div class="zc-pf-field"><label>Discount Price</label>
                            <input type="number" step="0.01" min="0" name="discount" id="pf-discount" value="{{ $discount }}" class="studio-form-control"></div>
                        <div class="zc-pf-field"><label>Sale Price <span class="req">*</span></label>
                            <input type="number" id="pf-sale" value="{{ $isEdit ? $product->price : '' }}" class="studio-form-control" style="background:var(--studio-surface-soft);" readonly></div>
                    </div>

                    <div class="zc-pf-grid zc-pf-2">
                        <div class="zc-pf-field"><label>Cost Price</label>
                            <input type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" class="studio-form-control"></div>
                        <div class="zc-pf-field"><label>Stock <span class="req">*</span></label>
                            <input type="number" min="0" name="stock" value="{{ old('stock', $isEdit ? $product->stock : 0) }}" class="studio-form-control" required></div>
                    </div>

                    <div class="zc-pf-grid zc-pf-3">
                        <div class="zc-pf-field"><label>Status <span class="req">*</span></label>
                            <select name="status" class="studio-form-control" required>
                                <option value="active" @selected(old('status', $product->status ?: 'active') === 'active')>Publish</option>
                                <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Unpublished</option>
                            </select></div>
                        <div class="zc-pf-field"><label>Brand</label>
                            <select name="brand_id" class="studio-form-control">
                                <option value="">Select Brand</option>
                                @foreach ($brands as $b)
                                    <option value="{{ $b->id }}" @selected((int) old('brand_id', $product->brand_id) === (int) $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select></div>
                        <div class="zc-pf-field"><label>Artisan / Origin</label>
                            <input type="text" name="artisan_origin" value="{{ old('artisan_origin', $product->artisan_origin) }}" class="studio-form-control" placeholder="Optional"></div>
                    </div>

                    <div class="zc-pf-field"><label>Short Description</label>
                        <textarea name="short_description" rows="2" class="studio-form-control" placeholder="One or two lines shown in listings">{{ old('short_description', $product->short_description) }}</textarea></div>

                    {{-- Size chart --}}
                    <div class="zc-pf-field">
                        <label style="display:flex; align-items:center; gap:0.5rem;"><input type="checkbox" id="pf-sizechart-toggle" style="width:1rem;height:1rem;accent-color:#c79a3b;" @checked($isEdit && $product->size_chart_id)> Size Chart</label>
                        <div id="pf-sizechart-box" style="display:{{ $isEdit && $product->size_chart_id ? 'block' : 'none' }}; max-width:16rem;">
                            <div class="zc-pf-drop">
                                @if ($isEdit && $product->sizeChart)<img src="{{ $mediaUrl($product->sizeChart) }}" alt="">@else<span class="zc-pf-plus">+</span>@endif
                                <input type="file" name="size_chart" accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Attribute (size/color) + variants --}}
            <section class="zc-op-panel p-5 zc-pf-card">
                <h2 class="studio-section-title" style="text-align:left;">Attribute (size / colour)</h2>
                <p class="zc-pf-sub" style="text-align:left;margin-bottom:0.9rem;">Tick colours and/or sizes, then <b>Generate</b>. Pick <b>both</b> to create every colour × size combination — each becomes its own variant with its own price, stock &amp; image.</p>

                <div class="zc-pf-field"><label style="font-weight:800;color:var(--studio-text);">Colours</label>
                    <div class="zc-pf-attrs" data-attr-list="colour" style="margin-top:0.4rem;">
                        @foreach ($colorOptions as $opt)
                            <span class="zc-pf-attr" data-opt data-id="{{ $opt['id'] }}">
                                <label class="zc-pf-attr-lbl"><input type="checkbox" data-attr-color value="{{ $opt['name'] }}"> {{ $opt['name'] }}</label>
                                <button type="button" class="zc-pf-attr-x" data-opt-remove aria-label="Remove {{ $opt['name'] }}">&times;</button>
                            </span>
                        @endforeach
                    </div>
                    <div class="zc-pf-add">
                        <input type="text" class="studio-form-control" data-add-input="colour" placeholder="Add a colour…" maxlength="60" autocomplete="off">
                        <button type="button" class="studio-command-button" data-add-btn="colour">＋ Add</button>
                    </div>
                </div>
                <div class="zc-pf-field" style="margin-top:0.8rem;"><label style="font-weight:800;color:var(--studio-text);">Sizes</label>
                    <div class="zc-pf-attrs" data-attr-list="size" style="margin-top:0.4rem;">
                        @foreach ($sizeOptions as $opt)
                            <span class="zc-pf-attr" data-opt data-id="{{ $opt['id'] }}">
                                <label class="zc-pf-attr-lbl"><input type="checkbox" data-attr-size value="{{ $opt['name'] }}"> {{ $opt['name'] }}</label>
                                <button type="button" class="zc-pf-attr-x" data-opt-remove aria-label="Remove {{ $opt['name'] }}">&times;</button>
                            </span>
                        @endforeach
                    </div>
                    <div class="zc-pf-add">
                        <input type="text" class="studio-form-control" data-add-input="size" placeholder="Add a size…" maxlength="60" autocomplete="off">
                        <button type="button" class="studio-command-button" data-add-btn="size">＋ Add</button>
                    </div>
                </div>
                <div style="margin-top:0.85rem;"><button type="button" class="studio-command-button studio-command-button--primary" id="pf-gen">＋ Generate variants</button></div>

                <div class="studio-responsive-scroll">
                    <table class="zc-pf-vtbl">
                        <thead><tr><th>#</th><th>Variant</th><th>SKU</th><th>Price</th><th>Stock</th><th>Image</th><th>Action</th></tr></thead>
                        <tbody id="pf-variants">
                            @foreach ($existingVariants as $v)
                                @php $vColor = $v->option_values['Color'] ?? ''; $vSize = $v->option_values['Size'] ?? ''; @endphp
                                <tr data-label="{{ $v->name }}">
                                    <td class="zc-pf-vnum"></td>
                                    <td class="zc-pf-vname">{{ $v->name }}<input type="hidden" name="variants[{{ $loop->index }}][label]" value="{{ $v->name }}"><input type="hidden" name="variants[{{ $loop->index }}][color]" value="{{ $vColor }}"><input type="hidden" name="variants[{{ $loop->index }}][size]" value="{{ $vSize }}"></td>
                                    <td><input type="text" name="variants[{{ $loop->index }}][sku]" value="{{ $v->sku }}" class="studio-form-control zc-pf-vsku" placeholder="Auto"></td>
                                    <td><input type="number" step="0.01" min="0" name="variants[{{ $loop->index }}][price]" value="{{ $v->price }}" class="studio-form-control zc-pf-vprice"></td>
                                    <td><input type="number" min="0" name="variants[{{ $loop->index }}][stock]" value="{{ (int) $v->stock }}" class="studio-form-control" style="width:5rem;"></td>
                                    <td class="zc-pf-vimg">
                                        @if ($v->image)
                                            <span class="zc-pf-vpreview"><img src="{{ $mediaUrl($v->image) }}" alt="" class="zc-pf-vthumb"><button type="button" class="zc-pf-vrm" title="Replace image">&times;</button></span>
                                        @endif
                                        <input type="file" accept="image/*" name="variants[{{ $loop->index }}][image]" class="studio-form-control zc-pf-vfile"@if ($v->image) style="display:none;"@endif>
                                    </td>
                                    <td><button type="button" class="zc-pf-vdel" data-vdel><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Add-ons: other products the customer can select alongside this one --}}
            <section class="zc-op-panel p-5 zc-pf-card">
                <h2 class="studio-section-title" style="text-align:left;">Add-ons</h2>
                <p class="zc-pf-sub" style="text-align:left;margin-bottom:0.9rem;">Attach other products as optional add-ons — e.g. a Bedsheet with a Kulbalish Cover add-on. On the product page the customer can select this product <b>and</b> any add-ons together. Leave price blank to use the add-on's own price.</p>

                <div class="zc-pf-ad-pick">
                    <input type="text" class="studio-form-control" id="pf-addon-search" placeholder="Type product name or SKU…" autocomplete="off">
                    <div class="zc-pf-ad-results" id="pf-addon-results"></div>
                </div>
                <div class="zc-pf-ad-list" id="pf-addon-list"></div>
                <div class="zc-pf-ad-empty" id="pf-addon-empty">No add-ons yet.</div>
            </section>

            {{-- Product Images --}}
            <section class="zc-op-panel p-5 zc-pf-card">
                <h2 class="studio-section-title" style="text-align:left;">Product Images</h2>
                <p class="zc-pf-sub" style="text-align:left; margin-bottom:0;">📐 Recommended: <b>800 × 800 px</b> square (product photos show in a square). Keep each image ≤ 1080 × 1080 px.</p>
                <div class="zc-pf-imgs">
                    <div class="zc-pf-slot">
                        <div class="zc-pf-drop">
                            @if ($isEdit && $product->thumbnail)<img src="{{ $mediaUrl($product->thumbnail) }}" alt="">@else<span class="zc-pf-plus">+</span>@endif
                            <input type="file" name="cover_photo" accept="image/*" data-preview>
                        </div>
                        <div class="zc-pf-slotlabel">Cover Photo</div>
                    </div>
                    @for ($i = 2; $i <= 6; $i++)
                        <div class="zc-pf-slot">
                            <div class="zc-pf-drop"><span class="zc-pf-plus">+</span><input type="file" name="gallery[]" accept="image/*" data-preview></div>
                            <div class="zc-pf-slotlabel">Image {{ $i }}</div>
                        </div>
                    @endfor
                </div>
                @if ($isEdit && $product->galleryMedia->isNotEmpty())
                    <div class="zc-pf-imgs">
                        @foreach ($product->galleryMedia as $g)
                            <div class="zc-pf-slot zc-pf-existing">
                                <button type="button" class="zc-pf-rm" data-remove="{{ $g->id }}">×</button>
                                <div class="zc-pf-drop"><img src="{{ $mediaUrl($g) }}" alt=""></div>
                                <div class="zc-pf-slotlabel">Saved</div>
                            </div>
                        @endforeach
                    </div>
                    <div id="pf-remove-holder"></div>
                @endif
            </section>

            {{-- Product Details --}}
            <section class="zc-op-panel p-5 zc-pf-card">
                <h2 class="studio-section-title" style="text-align:left;">Product Details (English) <span class="req">*</span></h2>
                <div class="zc-rt-toolbar" style="margin-top:0.75rem;">
                    <select class="zc-rt-select" data-rt-block title="Text style">
                        <option value="p">Normal</option>
                        <option value="h1">Heading 1</option>
                        <option value="h2">Heading 2</option>
                        <option value="h3">Heading 3</option>
                        <option value="blockquote">Quote</option>
                        <option value="pre">Code block</option>
                    </select>
                    <span class="zc-rt-sep"></span>
                    <button type="button" class="zc-rt-btn" data-cmd="bold" data-state="bold" title="Bold" style="font-weight:900;">B</button>
                    <button type="button" class="zc-rt-btn" data-cmd="italic" data-state="italic" title="Italic" style="font-style:italic;font-family:Georgia,serif;">I</button>
                    <button type="button" class="zc-rt-btn" data-cmd="underline" data-state="underline" title="Underline" style="text-decoration:underline;">U</button>
                    <button type="button" class="zc-rt-btn" data-cmd="strikeThrough" data-state="strikeThrough" title="Strikethrough" style="text-decoration:line-through;">S</button>
                    <span class="zc-rt-sep"></span>
                    <button type="button" class="zc-rt-btn" data-cmd="justifyLeft" data-state="justifyLeft" title="Align left"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h13"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="justifyCenter" data-state="justifyCenter" title="Align centre"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M7 12h10M6 18h12"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="justifyRight" data-state="justifyRight" title="Align right"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M10 12h10M7 18h13"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="justifyFull" data-state="justifyFull" title="Justify"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    <span class="zc-rt-sep"></span>
                    <button type="button" class="zc-rt-btn" data-cmd="insertUnorderedList" data-state="insertUnorderedList" title="Bullet list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1.1" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.1" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.1" fill="currentColor" stroke="none"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="insertOrderedList" data-state="insertOrderedList" title="Numbered list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M3.4 5l1-.5v3.6"/><path d="M2.9 16.4c0-.55.45-.95 1-.95s1 .4 1 .95c0 .85-2 1.3-2 2.35h2"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="outdent" title="Decrease indent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 18h16M11 10h9M11 14h9"/><path d="M7 10l-3 2 3 2z" fill="currentColor" stroke="none"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="indent" title="Increase indent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 18h16M11 10h9M11 14h9"/><path d="M4 10l3 2-3 2z" fill="currentColor" stroke="none"/></svg></button>
                    <span class="zc-rt-sep"></span>
                    <label class="zc-rt-btn zc-rt-color" title="Text colour">A<span class="zc-rt-color-bar"></span><input type="color" data-rt-color value="#111827"></label>
                    <button type="button" class="zc-rt-btn" data-cmd="createLink" title="Insert link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007 0l2-2a5 5 0 00-7-7l-1 1"/><path d="M14 11a5 5 0 00-7 0l-2 2a5 5 0 007 7l1-1"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="insertImage" title="Insert image"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l5-5 4 4 3-3 4 4"/></svg></button>
                    <button type="button" class="zc-rt-btn" data-cmd="insertVideo" title="Insert video"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z" fill="currentColor" stroke="none"/></svg></button>
                    <span class="zc-rt-sep"></span>
                    <button type="button" class="zc-rt-btn" data-cmd="removeFormat" title="Clear formatting"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12M9 7l6 10M15 7l-3 5"/><path d="M5 20h7"/></svg></button>
                </div>
                <div class="zc-rt-editor" id="pf-editor" contenteditable="true" data-placeholder="Write the product description…">{!! old('description', $product->description) !!}</div>
            </section>

            {{-- SEO --}}
            <section class="zc-op-panel p-5 zc-pf-card">
                <details @if($isEdit) open @endif>
                    <summary style="cursor:pointer; list-style:none;"><h2 class="studio-section-title" style="display:inline;">SEO Info</h2></summary>
                    <div class="zc-pf-grid" style="margin-top:1rem;">
                        <div class="zc-pf-field"><label>Meta Title</label><input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="studio-form-control" placeholder="Enter Title (max 70 chars)"></div>
                        <div class="zc-pf-field"><label>Meta Description</label><textarea name="meta_description" rows="2" class="studio-form-control" placeholder="Enter Description (max 170 chars)">{{ old('meta_description', $product->meta_description) }}</textarea></div>
                    </div>
                </details>
            </section>

            <div style="text-align:center; margin:1.5rem 0 2rem;">
                <button type="submit" class="studio-command-button studio-command-button--primary" style="padding:0.6rem 3rem;">{{ $isEdit ? 'Save' : 'Submit' }}</button>
            </div>
        </form>
    </div>

    @push('studio-scripts')
        <script>
            (() => {
                const cats = @json($categories);
                const chain = @json($catChain ?? []);
                const l1 = document.getElementById('pf-cat-1'), l2 = document.getElementById('pf-cat-2'), l3 = document.getElementById('pf-cat-3');
                const catId = document.getElementById('pf-category-id');
                const opts = (sel, list, placeholder, selected) => {
                    sel.innerHTML = `<option value="">${placeholder}</option>` + list.map(c => `<option value="${c.id}" ${c.id==selected?'selected':''}>${c.name}</option>`).join('');
                };
                const childrenOf = (pid) => cats.filter(c => String(c.parent_id||'') === String(pid||''));
                const syncCatId = () => { catId.value = l3.value || l2.value || l1.value || ''; };
                opts(l1, childrenOf(null), 'Select Category', chain[0]);
                const fill2 = () => { opts(l2, childrenOf(l1.value), 'Select Sub Category', chain[1]); };
                const fill3 = () => { opts(l3, childrenOf(l2.value), 'Select Sub Sub Category', chain[2]); };
                fill2(); fill3(); syncCatId();
                l1.addEventListener('change', () => { fill2(); l3.innerHTML='<option value="">Select Sub Sub Category</option>'; syncCatId(); });
                l2.addEventListener('change', () => { fill3(); syncCatId(); });
                l3.addEventListener('change', syncCatId);

                // Sale price = Price - Discount
                const price = document.getElementById('pf-price'), disc = document.getElementById('pf-discount'), sale = document.getElementById('pf-sale');
                const calcSale = () => { sale.value = Math.max(0, (parseFloat(price.value)||0) - (parseFloat(disc.value)||0)); };
                price.addEventListener('input', calcSale); disc.addEventListener('input', calcSale); calcSale();

                // Size chart toggle
                const scT = document.getElementById('pf-sizechart-toggle'), scB = document.getElementById('pf-sizechart-box');
                scT?.addEventListener('change', () => scB.style.display = scT.checked ? 'block' : 'none');

                // Variants (single-attribute OR colour × size combinations)
                const vbody = document.getElementById('pf-variants');
                let vIdx = {{ $existingVariants->count() }};
                const renumber = () => vbody.querySelectorAll('tr').forEach((tr, n) => { const c = tr.querySelector('.zc-pf-vnum'); if (c) c.textContent = n + 1; });
                renumber();
                const comboLabel = (color, size) => (color && size) ? (color + ' / ' + size) : (color || size);
                const addRow = (color, size) => {
                    const label = comboLabel(color, size);
                    if (!label || vbody.querySelector(`tr[data-label="${CSS.escape(label)}"]`)) return;
                    const i = vIdx++;
                    const tr = document.createElement('tr');
                    tr.dataset.label = label;
                    tr.innerHTML = `<td class="zc-pf-vnum"></td>
                        <td class="zc-pf-vname">${label}<input type="hidden" name="variants[${i}][label]" value="${label}"><input type="hidden" name="variants[${i}][color]" value="${color || ''}"><input type="hidden" name="variants[${i}][size]" value="${size || ''}"></td>
                        <td><input type="text" name="variants[${i}][sku]" value="" class="studio-form-control zc-pf-vsku" placeholder="Auto"></td>
                        <td><input type="number" step="0.01" min="0" name="variants[${i}][price]" value="0" class="studio-form-control zc-pf-vprice"></td>
                        <td><input type="number" min="0" name="variants[${i}][stock]" value="0" class="studio-form-control" style="width:5rem;"></td>
                        <td class="zc-pf-vimg"><input type="file" accept="image/*" name="variants[${i}][image]" class="studio-form-control zc-pf-vfile"></td>
                        <td><button type="button" class="zc-pf-vdel" data-vdel>✕</button></td>`;
                    vbody.appendChild(tr); renumber();
                };
                const removeVariant = (label) => {
                    const tr = vbody.querySelector(`tr[data-label="${CSS.escape(label)}"]`);
                    if (tr) { tr.remove(); renumber(); }
                };
                const checkedVals = (sel) => [].slice.call(document.querySelectorAll(sel + ':checked')).map(c => c.value);
                document.getElementById('pf-gen').addEventListener('click', () => {
                    const colors = checkedVals('[data-attr-color]');
                    const sizes = checkedVals('[data-attr-size]');
                    if (colors.length && sizes.length) { colors.forEach(c => sizes.forEach(s => addRow(c, s))); }
                    else if (colors.length) { colors.forEach(c => addRow(c, '')); }
                    else if (sizes.length) { sizes.forEach(s => addRow('', s)); }
                    else { alert('Tick at least one colour or size first.'); }
                });
                vbody.addEventListener('click', (e) => {
                    if (e.target.closest('[data-vdel]')) { const tr = e.target.closest('tr'); removeVariant(tr.dataset.label); }
                });

                // Add / remove colour & size options — persists to the shared
                // attribute catalog so it's remembered for every product.
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const attrStoreUrl = @json(route('products.attr-options.store'));
                const attrDestroyTpl = @json(route('products.attr-options.destroy', ['attributeValue' => '__ID__']));
                const esc = (s) => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
                const makeChip = (group, id, name) => {
                    const attr = group === 'colour' ? 'data-attr-color' : 'data-attr-size';
                    const span = document.createElement('span');
                    span.className = 'zc-pf-attr'; span.dataset.opt = ''; span.dataset.id = id;
                    span.innerHTML = `<label class="zc-pf-attr-lbl"><input type="checkbox" ${attr} value="${esc(name)}" checked> ${esc(name)}</label><button type="button" class="zc-pf-attr-x" data-opt-remove aria-label="Remove">×</button>`;
                    return span;
                };
                const addOption = (group) => {
                    const input = document.querySelector(`[data-add-input="${group}"]`);
                    const name = (input.value || '').trim();
                    if (!name) { input.focus(); return; }
                    const list = document.querySelector(`[data-attr-list="${group}"]`);
                    const dupe = [].some.call(list.querySelectorAll('input'), i => i.value.toLowerCase() === name.toLowerCase());
                    if (dupe) { input.value = ''; input.focus(); return; }
                    fetch(attrStoreUrl, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ group, name }) })
                        .then(r => r.json())
                        .then(d => { if (d && d.ok) { list.appendChild(makeChip(group, d.id, d.name)); input.value = ''; } })
                        .catch(() => {}).finally(() => input.focus());
                };
                document.querySelectorAll('[data-add-btn]').forEach(btn => btn.addEventListener('click', () => addOption(btn.dataset.addBtn)));
                document.querySelectorAll('[data-add-input]').forEach(inp => inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addOption(inp.dataset.addInput); } }));
                document.querySelectorAll('[data-attr-list]').forEach(list => list.addEventListener('click', (e) => {
                    const rm = e.target.closest('[data-opt-remove]'); if (!rm) return;
                    const chip = rm.closest('[data-opt]'); const id = chip.dataset.id;
                    if (!confirm('Remove this option from the list?')) return;
                    fetch(attrDestroyTpl.replace('__ID__', id), { method:'DELETE', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} })
                        .then(r => r.json()).then(d => { if (d && d.ok) chip.remove(); }).catch(() => {});
                }));

                // Per-variant image preview: choosing a file swaps "Choose File"
                // for a thumbnail + remove ×, matching the reference table.
                vbody.addEventListener('change', (e) => {
                    const inp = e.target.closest('.zc-pf-vfile'); if (!inp) return;
                    const f = inp.files[0]; if (!f) return;
                    const cell = inp.closest('.zc-pf-vimg');
                    let prev = cell.querySelector('.zc-pf-vpreview');
                    if (!prev) {
                        prev = document.createElement('span');
                        prev.className = 'zc-pf-vpreview';
                        prev.innerHTML = '<img class="zc-pf-vthumb" alt=""><button type="button" class="zc-pf-vrm" title="Remove image">&times;</button>';
                        cell.prepend(prev);
                    }
                    prev.querySelector('img').src = URL.createObjectURL(f);
                    inp.style.display = 'none';
                });
                vbody.addEventListener('click', (e) => {
                    const rm = e.target.closest('.zc-pf-vrm'); if (!rm) return;
                    const cell = rm.closest('.zc-pf-vimg');
                    const inp = cell.querySelector('.zc-pf-vfile');
                    if (inp) { inp.value = ''; inp.style.display = ''; }
                    cell.querySelector('.zc-pf-vpreview')?.remove();
                });

                // Image preview
                document.querySelectorAll('input[type=file][data-preview]').forEach(inp => {
                    inp.addEventListener('change', () => {
                        const drop = inp.closest('.zc-pf-drop');
                        const f = inp.files[0]; if (!f) return;
                        const url = URL.createObjectURL(f);
                        drop.querySelector('.zc-pf-plus')?.remove();
                        let img = drop.querySelector('img'); if (!img) { img = document.createElement('img'); drop.prepend(img); }
                        img.src = url;
                    });
                });
                // Remove saved gallery image
                const rmHolder = document.getElementById('pf-remove-holder');
                document.querySelectorAll('[data-remove]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = btn.dataset.remove;
                        const h = document.createElement('input'); h.type='hidden'; h.name='remove_media[]'; h.value=id; rmHolder?.appendChild(h);
                        btn.closest('.zc-pf-slot').remove();
                    });
                });

                // Premium rich text editor
                const editor = document.getElementById('pf-editor'), descInput = document.getElementById('pf-description-input');
                try { document.execCommand('styleWithCSS', false, true); } catch (_) {}
                const exec = (cmd, val = null) => { editor.focus(); document.execCommand(cmd, false, val); syncStates(); };

                // Preserve the selection across prompts / the native colour picker.
                let savedRange = null;
                const saveSel = () => { const s = window.getSelection(); if (s.rangeCount && editor.contains(s.anchorNode)) savedRange = s.getRangeAt(0); };
                const restoreSel = () => { if (savedRange) { const s = window.getSelection(); s.removeAllRanges(); s.addRange(savedRange); } editor.focus(); };
                editor.addEventListener('keyup', () => { saveSel(); syncStates(); });
                editor.addEventListener('mouseup', () => { saveSel(); syncStates(); });

                document.querySelectorAll('.zc-rt-btn[data-cmd]').forEach(b => b.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    saveSel();
                    const cmd = b.dataset.cmd;
                    if (cmd === 'createLink') { const u = prompt('Link URL:', 'https://'); restoreSel(); if (u) document.execCommand('createLink', false, u); }
                    else if (cmd === 'insertImage') { const u = prompt('Image URL:', 'https://'); restoreSel(); if (u) document.execCommand('insertImage', false, u); }
                    else if (cmd === 'insertVideo') {
                        let u = prompt('Video URL (YouTube, Vimeo or .mp4):'); restoreSel();
                        if (!u) return; u = u.trim();
                        const yt = u.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{11})/);
                        const vi = u.match(/vimeo\.com\/(\d+)/);
                        let html;
                        if (yt) html = `<div class="zc-rt-embed"><iframe src="https://www.youtube.com/embed/${yt[1]}" allowfullscreen></iframe></div><p><br></p>`;
                        else if (vi) html = `<div class="zc-rt-embed"><iframe src="https://player.vimeo.com/video/${vi[1]}" allowfullscreen></iframe></div><p><br></p>`;
                        else if (/\.(mp4|webm|ogg)(\?.*)?$/i.test(u)) html = `<video controls src="${u}"></video><p><br></p>`;
                        else html = `<div class="zc-rt-embed"><iframe src="${u}" allowfullscreen></iframe></div><p><br></p>`;
                        editor.focus(); document.execCommand('insertHTML', false, html);
                    }
                    else exec(cmd);
                    syncStates();
                }));

                const blockSel = document.querySelector('[data-rt-block]');
                blockSel?.addEventListener('mousedown', saveSel);
                blockSel?.addEventListener('change', () => { restoreSel(); document.execCommand('formatBlock', false, blockSel.value); blockSel.selectedIndex = 0; });

                const colorInp = document.querySelector('[data-rt-color]');
                const colorBar = document.querySelector('.zc-rt-color-bar');
                document.querySelector('.zc-rt-color')?.addEventListener('mousedown', saveSel);
                colorInp?.addEventListener('input', () => { restoreSel(); document.execCommand('foreColor', false, colorInp.value); if (colorBar) colorBar.style.background = colorInp.value; });

                // Highlight the active toolbar states (bold/italic/lists/align…).
                function syncStates() {
                    document.querySelectorAll('.zc-rt-btn[data-state]').forEach(b => {
                        let on = false; try { on = document.queryCommandState(b.dataset.state); } catch (_) {}
                        b.classList.toggle('is-active', on);
                    });
                }

                document.getElementById('zc-pf-form').addEventListener('submit', () => { descInput.value = editor.innerHTML; });
            })();

            // Add-ons picker
            (() => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const searchUrl = "{{ route('products.addons.search') }}";
                const excludeId = {{ $isEdit ? $product->id : 0 }};
                const search = document.getElementById('pf-addon-search'), results = document.getElementById('pf-addon-results');
                const list = document.getElementById('pf-addon-list'), empty = document.getElementById('pf-addon-empty');
                const esc = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
                let items = @json($existingAddons ?? []);

                function render() {
                    list.innerHTML = '';
                    empty.style.display = items.length ? 'none' : 'block';
                    items.forEach((it, idx) => {
                        const row = document.createElement('div'); row.className = 'zc-pf-ad-item';
                        const thumb = it.thumb ? `<img src="${esc(it.thumb)}" alt="">` : '<div class="ph"></div>';
                        row.innerHTML = thumb +
                            `<div class="m"><b>${esc(it.name)}</b><span>Own price: ৳${(+it.price).toLocaleString()}</span></div>` +
                            `<input type="number" step="0.01" min="0" class="studio-form-control zc-pf-ad-price" placeholder="Custom ৳ (optional)" value="${it.custom_price != null ? it.custom_price : ''}" data-cp="${idx}">` +
                            `<button type="button" class="zc-pf-ad-rm" data-rm="${idx}" title="Remove">✕</button>` +
                            `<input type="hidden" name="addons[${idx}][product_id]" value="${it.id}">` +
                            `<input type="hidden" name="addons[${idx}][custom_price]" value="${it.custom_price != null ? it.custom_price : ''}" data-cph="${idx}">`;
                        list.appendChild(row);
                    });
                }
                function add(p) {
                    if (items.some((x) => x.id === p.id)) return;
                    items.push({ id: p.id, name: p.name, sku: p.sku, price: p.price, custom_price: null, thumb: p.thumb });
                    render();
                }

                let t;
                search?.addEventListener('input', () => {
                    clearTimeout(t); const q = search.value.trim();
                    if (q.length < 1) { results.classList.remove('show'); results.innerHTML = ''; return; }
                    t = setTimeout(() => {
                        fetch(`${searchUrl}?q=${encodeURIComponent(q)}&exclude=${excludeId}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                            .then((r) => r.json()).then((d) => {
                                results.innerHTML = '';
                                (d.results || []).forEach((p) => {
                                    const el = document.createElement('div'); el.className = 'zc-pf-ad-res';
                                    el.innerHTML = (p.thumb ? `<img src="${esc(p.thumb)}" alt="">` : '<div class="ph"></div>') +
                                        `<div class="m"><b>${esc(p.name)}</b><span>SKU: ${esc(p.sku || '—')} · ৳${(+p.price).toLocaleString()}</span></div>`;
                                    el.addEventListener('click', () => { add(p); results.classList.remove('show'); search.value = ''; search.focus(); });
                                    results.appendChild(el);
                                });
                                results.classList.toggle('show', (d.results || []).length > 0);
                            });
                    }, 220);
                });
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.zc-pf-ad-pick')) results?.classList.remove('show');
                    const rm = e.target.closest('[data-rm]');
                    if (rm) { items.splice(+rm.getAttribute('data-rm'), 1); render(); }
                });
                list?.addEventListener('input', (e) => {
                    const cp = e.target.getAttribute('data-cp');
                    if (cp === null) return;
                    const v = e.target.value.trim();
                    items[+cp].custom_price = v === '' ? null : v;
                    const hidden = list.querySelector(`[data-cph="${cp}"]`);
                    if (hidden) hidden.value = v;
                });
                render();
            })();
        </script>
    @endpush
@endsection
