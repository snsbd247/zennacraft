@extends('layouts.app')

@section('title', ($product->meta_title ?: $product->name).' — '.$storeName)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($product->meta_description ?: $product->short_description ?: $product->description ?: $product->name), 155))

@php
    $themeSettings = $themeSettings ?? collect();
    $themeValue = fn (string $key, $fallback = null) => filled($themeSettings->get($key)) ? $themeSettings->get($key) : $fallback;
    $mediaUrl = $mediaUrl ?? fn ($m): ?string => null;

    $price = (float) $product->price;
    $compare = $product->compare_price ? (float) $product->compare_price : null;
    $hasOff = $compare && $compare > $price;
    $off = $hasOff ? (int) round((($compare - $price) / $compare) * 100) : null;

    $variants = collect($product->variants ?? [])
        ->filter(fn ($v) => ($v->status ?? 'active') === 'active' && ($v->show_on_storefront ?? true))
        ->values();

    // A "combination" product = variants carrying BOTH a colour and a size, e.g.
    // White / M. Each such variant is its own SKU (own price/stock/image).
    $isCombo = $variants->contains(fn ($v) => filled($v->option_values['Color'] ?? null) && filled($v->option_values['Size'] ?? null));

    // Group variants by their attribute (Size / Color / …). For combination
    // products every variant is one SKU listed together under one group.
    $groups = $isCombo
        ? collect(['Colour & size' => $variants])
        : $variants->groupBy(fn ($v) => array_key_first($v->option_values ?? []) ?: 'Options');

    $colorMap = ['BLACK'=>'#1c1c1c','WHITE'=>'#f3f3ee','RED'=>'#d3382f','BLUE'=>'#2a4c9b','NAVY'=>'#1e2a4a','GREEN'=>'#1f7a3d','MAROON'=>'#6d1f2e','YELLOW'=>'#e8b923','GREY'=>'#8a8f98','GRAY'=>'#8a8f98','PINK'=>'#e58aa8','PURPLE'=>'#6b3fa0','ORANGE'=>'#e07b2a','BROWN'=>'#6b4423','OLIVE'=>'#6b7233','SKY'=>'#7fb2e5','BEIGE'=>'#d8c9a8'];

    // Gallery: cover + gallery images + variant images (unique by URL)
    $gallery = collect();
    if ($cover = $mediaUrl($product->thumbnail ?? null)) $gallery->push($cover);
    if ($product->relationLoaded('galleryMedia') || method_exists($product, 'galleryMedia')) {
        foreach (($product->galleryMedia ?? collect()) as $g) { if ($u = $mediaUrl($g)) $gallery->push($u); }
    }
    foreach ($variants as $v) { if ($vi = $mediaUrl($v->image ?? null)) $gallery->push($vi); }
    $gallery = $gallery->unique()->values();

    $avg = round((float) data_get($reviewSummary ?? [], 'average_rating', 0), 1);
    $rcount = (int) data_get($reviewSummary ?? [], 'review_count', 0);
    $reviews = collect(data_get($reviewSummary ?? [], 'reviews', []));

    $contactPhone = $themeValue('contact_phone');
    $contactWa = $themeValue('contact_whatsapp', $themeValue('social_whatsapp'));

    // Studio options the PDP surfaces: size chart, video, artisan/origin, stock.
    $sizeChartUrl = $mediaUrl($product->sizeChart ?? null);
    $artisan = trim((string) ($product->artisan_origin ?? ''));
    $videoUrl = trim((string) ($product->video_url ?? ''));
    $videoEmbed = null;
    if ($videoUrl !== '') {
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([\w-]{11})~', $videoUrl, $m)) $videoEmbed = 'https://www.youtube.com/embed/'.$m[1];
        elseif (preg_match('~vimeo\.com/(\d+)~', $videoUrl, $m)) $videoEmbed = 'https://player.vimeo.com/video/'.$m[1];
    }
    $totalStock = $variants->isNotEmpty() ? (int) $variants->sum(fn ($v) => (int) ($v->stock ?? 0)) : (int) ($product->stock ?? 0);

    $related = collect($productPersonalization['similar_products'] ?? []);
    if ($related->isEmpty() && $product->category_id) {
        $related = \App\Modules\Product\Models\Product::with(['category', 'thumbnail'])
            ->where('category_id', $product->category_id)->where('id', '!=', $product->id)
            ->where('status', 'active')->latest()->take(8)->get();
    }

    // JSON for the selector JS
    $variantJs = $variants->map(fn ($v) => [
        'id' => $v->id,
        // For combinations $v->name is already "Colour / Size"; for single
        // attributes it is the value (e.g. "M"). Either way it's the right label.
        'label' => (string) ($isCombo ? $v->name : (array_values($v->option_values ?? [])[0] ?? $v->name)),
        'type' => (string) (array_key_first($v->option_values ?? []) ?: 'Option'),
        'price' => (float) $v->price,
        'sku' => (string) $v->sku,
        'stock' => (int) ($v->stock ?? 0),
        'img' => $mediaUrl($v->image ?? null),
    ])->values();
@endphp

@push('storefront-styles')
<style>
    .pdp2{padding-block:22px 8px;}
    .pdp2__crumbs{font-size:12.5px;color:var(--muted);margin-bottom:16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    .pdp2__crumbs a:hover{color:var(--leaf-deep);}
    .pdp2__grid{display:grid;grid-template-columns:1fr 1fr;gap:36px;align-items:start;}
    .pdp2__gallery{position:sticky;top:92px;}
    .pdp2__main{aspect-ratio:1;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:var(--panel);display:grid;place-items:center;}
    .pdp2__main img{width:100%;height:100%;object-fit:cover;}
    .pdp2__ph{width:52%;height:52%;color:#cfc6b2;}
    .pdp2__thumbs{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;}
    .pdp2__thumb{width:70px;height:70px;border:1.5px solid var(--line);border-radius:10px;overflow:hidden;cursor:pointer;background:var(--panel);flex:none;transition:border-color .15s ease;}
    .pdp2__thumb.is-on{border-color:var(--leaf);border-width:2px;}
    .pdp2__thumb img{width:100%;height:100%;object-fit:cover;}

    .pdp2__name{font-size:clamp(22px,2.6vw,30px);line-height:1.2;margin-bottom:8px;}
    .pdp2__short{color:var(--muted);font-size:14.5px;margin-bottom:14px;line-height:1.6;}
    .pdp2__price{display:flex;align-items:baseline;gap:12px;padding-bottom:16px;border-bottom:1px solid var(--line);margin-bottom:16px;}
    .pdp2__price .now{font-size:28px;font-weight:800;color:var(--leaf-deep);}
    .pdp2__price .old{font-size:16px;color:var(--sale);text-decoration:line-through;}
    .pdp2__price .off{background:var(--honey);color:#3a2600;font-size:12px;font-weight:800;padding:3px 9px;border-radius:999px;}

    .pdp2__group{margin-bottom:16px;}
    .pdp2__glabel{font-size:13px;font-weight:800;margin-bottom:9px;display:flex;align-items:center;gap:10px;}
    .pdp2__glabel .hint{font-size:11.5px;font-weight:600;color:var(--muted);}
    .pdp2__chips{display:flex;flex-wrap:wrap;gap:9px;}
    .pdp2-chip{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border:1.5px solid var(--line);border-radius:10px;background:var(--surface);font-weight:700;font-size:13.5px;color:var(--ink);cursor:pointer;transition:all .15s ease;position:relative;}
    .pdp2-chip:hover{border-color:var(--leaf);}
    .pdp2-chip.is-on{border-color:var(--leaf);background:var(--leaf-soft);color:var(--leaf-deep);animation:pdpChipPop .4s cubic-bezier(.34,1.56,.5,1);}
    .pdp2-chip.is-on::after{content:"✓";position:absolute;top:-7px;right:-7px;width:18px;height:18px;border-radius:50%;background:var(--leaf);color:#fff;font-size:11px;display:grid;place-items:center;animation:pdpTick .45s cubic-bezier(.34,1.7,.5,1);}
    @keyframes pdpChipPop{0%{transform:scale(1);}42%{transform:scale(1.08);}72%{transform:scale(.98);}100%{transform:scale(1);}}
    @keyframes pdpTick{0%{transform:scale(0) rotate(-35deg);opacity:0;}55%{transform:scale(1.3) rotate(0);opacity:1;}100%{transform:scale(1);opacity:1;}}
    @media(prefers-reduced-motion:reduce){.pdp2-chip.is-on,.pdp2-chip.is-on::after{animation:none;}}
    .pdp2-chip.is-out{opacity:.45;cursor:not-allowed;text-decoration:line-through;}
    .pdp2-chip__sw{width:18px;height:18px;border-radius:50%;border:1px solid rgba(0,0,0,.15);flex:none;background-size:cover;background-position:center;}

    .pdp2__sel{border:1px dashed var(--line);border-radius:var(--radius);padding:12px;margin:16px 0;background:var(--panel);}
    .pdp2__sel-empty{color:var(--muted);font-size:13px;text-align:center;padding:8px;}
    .pdp2__sel-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--line-soft);}
    .pdp2__sel-row:last-child{border-bottom:none;}
    .pdp2__sel-row .nm{flex:1;min-width:0;font-weight:700;font-size:13.5px;}
    .pdp2__sel-row .sku{font-size:11px;color:var(--muted);font-weight:600;}
    .pdp2__qty{display:inline-flex;align-items:center;border:1.5px solid var(--line);border-radius:999px;overflow:hidden;background:var(--surface);flex:none;}
    .pdp2__qty button{width:34px;height:36px;border:none;background:transparent;font-size:17px;cursor:pointer;color:var(--leaf-deep);}
    .pdp2__qty input{width:38px;height:36px;border:none;text-align:center;font-weight:700;background:transparent;outline:none;}
    .pdp2__sel-row .rm{border:none;background:var(--sale-soft);color:var(--sale);width:28px;height:28px;border-radius:8px;cursor:pointer;flex:none;}
    .pdp2__total{display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--line);font-weight:800;}
    .pdp2__total b{font-size:18px;color:var(--leaf-deep);}

    .pdp2__buys{display:flex;gap:12px;margin-top:6px;}
    .pdp2__buys .zc-btn{flex:1;padding:14px;font-size:15px;}
    .pdp2__wish{flex:none !important;width:52px;padding:0 !important;}

    .pdp2__contact{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;}
    .pdp2__cc{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:var(--radius);padding:12px 14px;background:var(--surface);}
    .pdp2__cc svg{width:22px;height:22px;flex:none;}
    .pdp2__cc .l{font-size:11.5px;color:var(--muted);font-weight:700;}
    .pdp2__cc .v{font-weight:800;font-size:14.5px;}
    .pdp2__cc--call svg{color:var(--leaf);} .pdp2__cc--wa svg{color:#25d366;}

    .pdp2__tabs{display:flex;gap:6px;border-bottom:2px solid var(--line);margin:34px 0 0;flex-wrap:wrap;}
    .pdp2__tab{padding:12px 20px;font-weight:800;font-size:14px;color:var(--muted);border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;}
    .pdp2__tab.is-on{color:var(--leaf-deep);border-bottom-color:var(--leaf);}
    .pdp2__panel{display:none;padding:22px 2px;line-height:1.8;color:var(--ink);}
    .pdp2__panel.is-on{display:block;}
    .pdp2__panel :is(ul,ol){padding-left:20px;} .pdp2__panel li{margin:4px 0;}
    .pdp2__panel h2,.pdp2__panel h3{margin:10px 0 6px;}

    .pdp2__metarow{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin:-8px 0 16px;font-size:13px;}
    .pdp2__stock{display:inline-flex;align-items:center;gap:6px;font-weight:700;}
    .pdp2__stock.in{color:var(--leaf-deep);} .pdp2__stock.low{color:var(--honey-deep);} .pdp2__stock.out{color:var(--sale);}
    .pdp2__stock::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor;}
    .pdp2__origin{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-weight:600;}
    .pdp2__origin svg{width:15px;height:15px;color:var(--honey-deep);}
    .pdp2__util{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid var(--line);border-radius:10px;background:var(--surface);font-weight:700;font-size:13px;color:var(--leaf-deep);cursor:pointer;margin-bottom:14px;margin-right:8px;}
    .pdp2__util:hover{border-color:var(--leaf);background:var(--leaf-soft);}
    .pdp2__util svg{width:16px;height:16px;}
    .pdp2__videothumb{position:relative;}
    .pdp2__videothumb::after{content:"▶";position:absolute;inset:0;display:grid;place-items:center;background:rgba(9,20,13,.45);color:#fff;font-size:20px;}

    .pdp2-lb{position:fixed;inset:0;z-index:90;display:grid;place-items:center;padding:20px;}
    .pdp2-lb[hidden]{display:none;}
    .pdp2-lb__scrim{position:absolute;inset:0;background:rgba(9,20,13,.72);}
    .pdp2-lb__box{position:relative;z-index:2;max-width:760px;width:100%;max-height:88vh;background:var(--surface);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-lg);}
    .pdp2-lb__x{position:absolute;top:10px;right:10px;z-index:3;width:36px;height:36px;border-radius:50%;border:none;background:rgba(0,0,0,.55);color:#fff;font-size:22px;line-height:1;cursor:pointer;}
    #pdp2-lb-body img{width:100%;height:auto;display:block;max-height:88vh;object-fit:contain;}
    #pdp2-lb-body .vid{position:relative;padding-bottom:56.25%;height:0;}
    #pdp2-lb-body .vid iframe,#pdp2-lb-body video{position:absolute;inset:0;width:100%;height:100%;border:0;}

    @media(max-width:820px){
        .pdp2__grid{grid-template-columns:1fr;gap:22px;}
        .pdp2__gallery{position:static;}
        .pdp2__contact{grid-template-columns:1fr;}
    }
</style>
@endpush

@section('content')
<section class="pdp2 zc-wrap">
    <div class="pdp2__crumbs">
        <a href="{{ route('storefront.home') }}">Home</a> <span>›</span>
        @if ($product->category)<a href="{{ route('storefront.category.show', $product->category->slug) }}">{{ strtoupper($product->category->name) }}</a> <span>›</span>@endif
        <span>{{ $product->name }}</span>
    </div>

    <div class="pdp2__grid">
        {{-- GALLERY --}}
        <div class="pdp2__gallery">
            <div class="pdp2__main">
                @if ($gallery->isNotEmpty())
                    <img id="pdp2-main" src="{{ $gallery->first() }}" alt="{{ $product->name }}" data-cart-hero>
                @else
                    <svg class="pdp2__ph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg>
                @endif
            </div>
            @if ($gallery->count() > 1)
                <div class="pdp2__thumbs">
                    @foreach ($gallery as $g)
                        <div class="pdp2__thumb @if ($loop->first) is-on @endif" data-thumb="{{ $g }}"><img src="{{ $g }}" alt="" loading="lazy"></div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- INFO --}}
        <div class="pdp2__info">
            @if ($product->brand)<div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--honey-deep,#d97706);margin-bottom:6px;">{{ $product->brand->name }}</div>@endif
            <h1 class="pdp2__name">{{ $product->name }}</h1>
            @if ($product->short_description)<p class="pdp2__short">{{ $product->short_description }}</p>@endif

            <div class="pdp2__price">
                <span class="now" id="pdp2-price">৳{{ number_format($price) }}</span>
                @if ($hasOff)<span class="old">৳{{ number_format($compare) }}</span><span class="off">{{ $off }}% OFF</span>@endif
            </div>

            <div class="pdp2__metarow">
                @if ($totalStock > 0)
                    <span class="pdp2__stock {{ $totalStock <= 6 ? 'low' : 'in' }}">{{ $totalStock <= 6 ? 'Only '.$totalStock.' left' : 'In stock' }}</span>
                @else
                    <span class="pdp2__stock out">Out of stock</span>
                @endif
                @if ($artisan)<span class="pdp2__origin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $artisan }}</span>@endif
            </div>

            @if ($rcount)
                <div style="display:flex;align-items:center;gap:8px;margin:-8px 0 16px;font-size:13.5px;color:var(--muted);"><span style="color:var(--honey);letter-spacing:1px;">{{ str_repeat('★', max(1,(int)round($avg))) }}</span> {{ $avg }} · {{ $rcount }} reviews</div>
            @endif

            @forelse ($groups as $type => $items)
                <div class="pdp2__group">
                    <div class="pdp2__glabel">Select {{ $type }}: <span class="hint">tap to add — pick one or many</span></div>
                    <div class="pdp2__chips">
                        @foreach ($items as $v)
                            @php
                                $chipText = $isCombo ? $v->name : (array_values($v->option_values ?? [])[0] ?? $v->name);
                                $swColor = strtoupper((string) ($v->option_values['Color'] ?? (strtolower($type) === 'color' ? (array_values($v->option_values ?? [])[0] ?? '') : '')));
                                $showSw = $isCombo || strtolower($type) === 'color';
                                $out = (int) ($v->stock ?? 0) <= 0 && ! ($product->stock ?? 0);
                                $vi = $mediaUrl($v->image ?? null);
                            @endphp
                            <button type="button" class="pdp2-chip @if ($out) is-out @endif" data-variant="{{ $v->id }}" @if ($out) disabled @endif>
                                @if ($showSw)<span class="pdp2-chip__sw" style="{{ $vi ? "background-image:url('".$vi."')" : 'background:'.($colorMap[$swColor] ?? '#c9c2b2') }}"></span>@endif
                                {{ $chipText }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
            @endforelse

            @if ($sizeChartUrl || $videoEmbed || $videoUrl)
                <div style="margin-bottom:4px;">
                    @if ($sizeChartUrl)<button type="button" class="pdp2__util" data-lb-img="{{ $sizeChartUrl }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 8h18M3 12h18M3 16h18M8 5v3M13 9v3M8 13v3"/></svg> Size Chart</button>@endif
                    @if ($videoEmbed || $videoUrl)<button type="button" class="pdp2__util" data-lb-video="{{ $videoEmbed ?: $videoUrl }}" data-raw="{{ $videoEmbed ? '0' : '1' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M8 5v14l11-7z"/></svg> Watch Video</button>@endif
                </div>
            @endif

            {{-- Selection list (multi-variant) --}}
            <div class="pdp2__sel" id="pdp2-sel" @if ($variants->isEmpty()) style="display:none;" @endif>
                <div class="pdp2__sel-empty" id="pdp2-sel-empty">Select an option above to start your order.</div>
                <div id="pdp2-sel-rows"></div>
                <div class="pdp2__total" id="pdp2-sel-total" style="display:none;"><span>Total</span><b id="pdp2-sel-amt">৳0</b></div>
            </div>

            {{-- Base quantity (products without variants) --}}
            @if ($variants->isEmpty())
                <div class="pdp2__group" style="display:flex;align-items:center;gap:14px;">
                    <span style="font-weight:800;font-size:13px;">Qty:</span>
                    <div class="pdp2__qty"><button type="button" id="pdp2-bq-minus">−</button><input id="pdp2-bq" value="1" inputmode="numeric"><button type="button" id="pdp2-bq-plus">+</button></div>
                </div>
            @endif

            <form method="POST" action="{{ route('cart.add-many') }}" id="pdp2-form" data-cart-ajax>
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="checkout" id="pdp2-checkout" value="0">
                <div id="pdp2-inputs"></div>
                <div class="pdp2__buys">
                    <button type="button" class="zc-btn zc-btn--outline pdp2__wish" aria-label="Wishlist" onclick="this.classList.toggle('is-on');this.style.color=this.classList.contains('is-on')?'var(--sale)':'';"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-7-4.5-9.5-9C1 8 2.5 4.5 6 4.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 6.5C19 15.5 12 20 12 20Z"/></svg></button>
                    <button type="submit" class="zc-btn zc-btn--outline" data-checkout="0">Add to Cart</button>
                    <button type="submit" class="zc-btn zc-btn--primary zc-fire" data-checkout="1">Order Now ✓</button>
                </div>
            </form>

            @if ($contactPhone || $contactWa)
                <div class="pdp2__contact">
                    @if ($contactPhone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhone) }}" class="pdp2__cc pdp2__cc--call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5c0 9 6 15 15 15l-.5-4-4-1-2 2c-2-1-4-3-5-5l2-2-1-4z"/></svg>
                            <span><span class="l">Call Now</span><span class="v">{{ $contactPhone }}</span></span>
                        </a>
                    @endif
                    @if ($contactWa)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contactWa) }}" target="_blank" rel="noopener" class="pdp2__cc pdp2__cc--wa">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.2 1.1-1.7 1.2-.4.1-1 .1-1.6-.1-.4-.1-.9-.3-1.5-.5-2.7-1.2-4.4-3.9-4.5-4-.1-.2-1-1.3-1-2.5s.6-1.8.9-2c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.5l-.3.4c-.1.1-.2.3-.1.5.1.2.5.9 1.1 1.4.8.7 1.4.9 1.6 1 .2.1.3.1.4-.1l.6-.7c.1-.2.3-.1.5-.1l1.8.9c.2.1.4.2.4.3.1.1.1.5-.1 1Z"/></svg>
                            <span><span class="l">Message Now</span><span class="v">{{ $contactWa }}</span></span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- TABS --}}
    <div class="pdp2__tabs">
        <button type="button" class="pdp2__tab is-on" data-tab="desc">Description</button>
        <button type="button" class="pdp2__tab" data-tab="delivery">Delivery Options</button>
        <button type="button" class="pdp2__tab" data-tab="reviews">Reviews</button>
    </div>
    <div class="pdp2__panel is-on" data-panel="desc">
        @if ($product->description){!! $product->description !!}@else<p class="zc-muted">No description provided yet.</p>@endif
    </div>
    <div class="pdp2__panel" data-panel="delivery">
        <ul>
            <li><b>Cash on delivery</b> nationwide — inspect the item at your door before paying.</li>
            <li>Delivered in <b>2–4 days</b> inside Dhaka, <b>3–6 days</b> elsewhere in Bangladesh.</li>
            <li>Free delivery on orders over <b>৳3000</b>.</li>
            <li><b>7-day easy exchange</b> if the item isn't right.</li>
        </ul>
    </div>
    <div class="pdp2__panel" data-panel="reviews">
        @if ($rcount)
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--line);">
                <div style="font-size:36px;font-weight:800;color:var(--leaf-deep);line-height:1;">{{ $avg }}</div>
                <div>
                    <div style="color:var(--honey);font-size:18px;letter-spacing:2px;">{{ str_repeat('★', max(1,(int)round($avg))) }}<span style="color:var(--line);">{{ str_repeat('★', 5 - max(1,(int)round($avg))) }}</span></div>
                    <div class="zc-muted" style="font-size:13px;margin-top:2px;">Based on {{ $rcount }} verified review{{ $rcount === 1 ? '' : 's' }}</div>
                </div>
            </div>
            <div style="display:grid;gap:14px;">
                @foreach ($reviews as $rv)
                    @php $rr = (int) ($rv['rating'] ?? 5); @endphp
                    <div class="zc-card" style="padding:16px 18px;">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                            <div style="color:var(--honey);letter-spacing:2px;">{{ str_repeat('★', $rr) }}<span style="color:var(--line);">{{ str_repeat('★', 5 - $rr) }}</span></div>
                            @if (!empty($rv['is_verified_purchase']))<span class="zc-badge zc-badge--soft">✓ Verified purchase</span>@endif
                        </div>
                        @if (!empty($rv['title']))<div style="font-weight:800;margin-top:8px;">{{ $rv['title'] }}</div>@endif
                        @if (!empty($rv['body']))<p style="color:var(--muted);margin-top:6px;line-height:1.7;">{{ $rv['body'] }}</p>@endif
                        <div class="zc-muted" style="font-size:12.5px;margin-top:8px;font-weight:700;">{{ $rv['reviewer_name'] ?? 'Verified customer' }}@if (!empty($rv['reviewer_location'])) · {{ $rv['reviewer_location'] }}@endif</div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="zc-muted">No reviews yet — verified buyers can leave a review after delivery.</p>
        @endif
    </div>

    {{-- RELATED --}}
    @if ($related->isNotEmpty())
        <section class="zc-sec">
            <div class="zc-sec__head"><div class="zc-sec__title">Related products</div></div>
            <div class="zc-grid zc-grid--4">
                @foreach ($related->take(8) as $product)@include('storefront.partials.product-card')@endforeach
            </div>
        </section>
    @endif
</section>

<div class="pdp2-lb" id="pdp2-lb" hidden>
    <div class="pdp2-lb__scrim" data-lb-close></div>
    <div class="pdp2-lb__box"><button type="button" class="pdp2-lb__x" data-lb-close aria-label="Close">&times;</button><div id="pdp2-lb-body"></div></div>
</div>

@push('storefront-scripts')
<script>
(function(){
    // Lightbox (size chart image + product video)
    var lb=document.getElementById('pdp2-lb'), lbBody=document.getElementById('pdp2-lb-body');
    function openLb(html){ lbBody.innerHTML=html; lb.hidden=false; document.body.classList.add('zc-no-scroll'); }
    function closeLb(){ lb.hidden=true; lbBody.innerHTML=''; document.body.classList.remove('zc-no-scroll'); }
    document.querySelectorAll('[data-lb-img]').forEach(function(b){ b.addEventListener('click',function(){ openLb('<img src="'+b.getAttribute('data-lb-img')+'" alt="Size chart">'); }); });
    document.querySelectorAll('[data-lb-video]').forEach(function(b){ b.addEventListener('click',function(){
        var u=b.getAttribute('data-lb-video'), raw=b.getAttribute('data-raw')==='1';
        openLb('<div class="vid">'+(raw?'<video controls autoplay src="'+u+'"></video>':'<iframe src="'+u+'?autoplay=1" allow="autoplay; fullscreen" allowfullscreen></iframe>')+'</div>');
    }); });
    document.querySelectorAll('[data-lb-close]').forEach(function(b){ b.addEventListener('click',closeLb); });
    document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&!lb.hidden)closeLb(); });

    // Gallery
    document.querySelectorAll('[data-thumb]').forEach(function(t){
        t.addEventListener('click',function(){
            var m=document.getElementById('pdp2-main'); if(m)m.src=t.getAttribute('data-thumb');
            document.querySelectorAll('.pdp2__thumb').forEach(function(x){x.classList.remove('is-on');});
            t.classList.add('is-on');
        });
    });

    // Tabs
    document.querySelectorAll('.pdp2__tab').forEach(function(tab){
        tab.addEventListener('click',function(){
            document.querySelectorAll('.pdp2__tab').forEach(function(x){x.classList.remove('is-on');});
            document.querySelectorAll('.pdp2__panel').forEach(function(x){x.classList.remove('is-on');});
            tab.classList.add('is-on');
            var p=document.querySelector('.pdp2__panel[data-panel="'+tab.getAttribute('data-tab')+'"]'); if(p)p.classList.add('is-on');
        });
    });

    // Multi-variant selection
    var VARIANTS = {!! $variantJs->toJson() !!};
    var byId = {}; VARIANTS.forEach(function(v){ byId[v.id]=v; });
    var basePrice = {{ $price }};
    var sel = {}; // id -> qty
    var rowsEl=document.getElementById('pdp2-sel-rows'), emptyEl=document.getElementById('pdp2-sel-empty'),
        totalEl=document.getElementById('pdp2-sel-total'), amtEl=document.getElementById('pdp2-sel-amt'),
        inputsEl=document.getElementById('pdp2-inputs'), priceEl=document.getElementById('pdp2-price');
    var money=function(n){return '৳'+Number(n).toLocaleString();};

    function render(){
        var ids=Object.keys(sel), total=0;
        rowsEl.innerHTML='';
        document.querySelectorAll('.pdp2-chip').forEach(function(c){ c.classList.toggle('is-on', !!sel[c.getAttribute('data-variant')]); });
        if(ids.length===0){ emptyEl.style.display='block'; totalEl.style.display='none'; if(priceEl)priceEl.textContent=money(basePrice); }
        else {
            emptyEl.style.display='none'; totalEl.style.display='flex';
            ids.forEach(function(id){
                var v=byId[id], q=sel[id]; total+=v.price*q;
                var row=document.createElement('div'); row.className='pdp2__sel-row';
                row.innerHTML='<span class="nm">'+v.type+': '+v.label+'<span class="sku"> · SKU '+v.sku+'</span></span>'
                    +'<div class="pdp2__qty"><button type="button" data-dec="'+id+'">−</button><input value="'+q+'" data-qi="'+id+'" inputmode="numeric"><button type="button" data-inc="'+id+'">+</button></div>'
                    +'<span style="font-weight:800;min-width:64px;text-align:right;">'+money(v.price*q)+'</span>'
                    +'<button type="button" class="rm" data-rm="'+id+'">✕</button>';
                rowsEl.appendChild(row);
            });
            amtEl.textContent=money(total);
            if(priceEl && ids.length===1){ priceEl.textContent=money(byId[ids[0]].price); }
        }
        // hidden inputs for submit
        inputsEl.innerHTML='';
        ids.forEach(function(id,i){
            inputsEl.insertAdjacentHTML('beforeend','<input type="hidden" name="items['+i+'][variant_id]" value="'+id+'"><input type="hidden" name="items['+i+'][quantity]" value="'+sel[id]+'">');
        });
    }
    function setQty(id,q){ q=Math.max(1,Math.min(99,parseInt(q)||1)); sel[id]=q; render(); }

    document.querySelectorAll('.pdp2-chip[data-variant]').forEach(function(chip){
        chip.addEventListener('click',function(){
            if(chip.disabled)return;
            var id=chip.getAttribute('data-variant');
            if(sel[id]){ delete sel[id]; } else { sel[id]=1; }
            render();
        });
    });
    document.getElementById('pdp2-sel-rows').addEventListener('click',function(e){
        var inc=e.target.getAttribute('data-inc'), dec=e.target.getAttribute('data-dec'), rm=e.target.getAttribute('data-rm');
        if(inc)setQty(inc,sel[inc]+1); else if(dec)setQty(dec,sel[dec]-1); else if(rm){ delete sel[rm]; render(); }
    });
    document.getElementById('pdp2-sel-rows').addEventListener('input',function(e){
        var qi=e.target.getAttribute('data-qi'); if(qi)setQty(qi,e.target.value);
    });

    // Base qty (no-variant products)
    var bq=document.getElementById('pdp2-bq');
    function baseInputs(){
        if(!bq)return; var q=Math.max(1,Math.min(99,parseInt((bq.value||'').replace(/\D/g,''))||1)); bq.value=q;
        inputsEl.innerHTML='<input type="hidden" name="items[0][quantity]" value="'+q+'">';
    }
    if(bq){ baseInputs();
        document.getElementById('pdp2-bq-minus').addEventListener('click',function(){bq.value=Math.max(1,(parseInt(bq.value)||1)-1);baseInputs();});
        document.getElementById('pdp2-bq-plus').addEventListener('click',function(){bq.value=Math.min(99,(parseInt(bq.value)||1)+1);baseInputs();});
        bq.addEventListener('input',baseInputs);
    }

    // Submit
    var form=document.getElementById('pdp2-form');
    form.querySelectorAll('button[type="submit"]').forEach(function(b){
        b.addEventListener('click',function(ev){
            document.getElementById('pdp2-checkout').value=b.getAttribute('data-checkout');
            if(VARIANTS.length && Object.keys(sel).length===0){ ev.preventDefault(); alert('Please select at least one option.'); document.getElementById('pdp2-sel').scrollIntoView({behavior:'smooth',block:'center'}); }
        });
    });
    render();
})();
</script>
@endpush
@endsection
