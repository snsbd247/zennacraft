@extends('layouts.app')

@section('title', $storeName.' — Online Shopping in Bangladesh')
@section('meta_description', 'Shop quality products online with cash on delivery across Bangladesh. Fast delivery, easy exchange.')

@php
    // Editable homepage text — all managed from Studio > Website Setup > Homepage Text.
    $themeSettings = $themeSettings ?? collect();
    $tv = fn (string $k, $d = null) => filled($themeSettings->get($k)) ? $themeSettings->get($k) : $d;
    $mediaUrl = $mediaUrl ?? fn ($m): ?string => null;
    $latestProducts = $latestProducts ?? collect();
    $topSellingProducts = $topSellingProducts ?? collect();
    $activeCategories = $activeCategories ?? collect();
    $featuredReviews = collect($featuredReviews ?? []);
    $storefrontSliders = $storefrontSliders ?? collect();

    $topSelling = ($topSellingProducts->isNotEmpty() ? $topSellingProducts : $latestProducts)->take(5);

    // Build category showcase sections from the loaded products (no extra queries).
    $pool = $topSellingProducts->concat($latestProducts)->unique('id');
    $byCategory = $pool->filter(fn ($p) => $p->category)->groupBy(fn ($p) => $p->category->id);
    $catSections = $activeCategories->filter(fn ($c) => ($byCategory[$c->id] ?? collect())->count() >= 2)->take(3);

    $personalization = $personalization ?? [];
    $forYou = collect($personalization['recommended'] ?? []);
    if ($forYou->isEmpty()) { $forYou = $latestProducts->take(10); }
    $forYou = $forYou->take(10);

    // Sliders are split by placement: the hero rotates all its slides, while the
    // side + promo spots each show their top active banner (or a built-in default).
    $heroSliders = $storefrontSliders->where('placement', 'home_hero')->values();
    $sideSlider = $storefrontSliders->firstWhere('placement', 'home_side');
    $promoSlider = $storefrontSliders->firstWhere('placement', 'home_promo');
    $sideImg = $sideSlider ? $mediaUrl($sideSlider->image) : null;
    $promoImg = $promoSlider ? $mediaUrl($promoSlider->image) : null;
@endphp

@section('content')

    {{-- HERO --}}
    <section class="zc-hero zc-wrap">
        <div class="zc-hero__grid">
            <div class="zc-hero__main" data-hero>
                <div class="zc-hero__track" data-hero-track>
                    @forelse ($heroSliders as $slider)
                        @php
                            $sImg = $mediaUrl($slider->image);
                            $hasText = filled($slider->title) || filled($slider->subtitle) || filled($slider->badge_text) || filled($slider->button_text);
                            // With text we darken the image so the copy is readable; a text-less
                            // banner shows the image clean (no overlay).
                            $sBg = $sImg
                                ? ($hasText
                                    ? "background-image:linear-gradient(105deg,rgba(9,30,17,.9),rgba(9,30,17,.35)),url('".$sImg."');"
                                    : "background-image:url('".$sImg."');")
                                : '';
                        @endphp
                        <div class="zc-hero__slide {{ $hasText ? '' : 'zc-hero__slide--plain' }}" @if ($sBg) style="{{ $sBg }}" @endif>
                            @if ($hasText)
                                <div style="position:relative;z-index:2;">
                                    @if (filled($slider->badge_text) || filled($slider->title))<span class="zc-hero__eyebrow"><svg width="28" height="2" viewBox="0 0 28 2"><rect width="20" height="2" fill="currentColor"/></svg> {{ $slider->badge_text ?: $tv('hero_kicker', 'Featured collection') }}</span>@endif
                                    @if (filled($slider->title))<h1>{{ $slider->title }}</h1>@endif
                                    @if (filled($slider->subtitle))<p class="lede" style="margin-top:12px;">{{ $slider->subtitle }}</p>@endif
                                    <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap;">
                                        <a href="{{ $slider->button_url ?: route('storefront.products') }}" class="zc-btn zc-btn--honey">{{ $slider->button_text ?: 'Shop the collection' }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                                        <a href="#top-selling" class="zc-btn zc-btn--ghost">Best sellers</a>
                                    </div>
                                </div>
                            @elseif (filled($slider->button_url))
                                {{-- Clean image banner: make the whole slide clickable if a link was set. --}}
                                <a href="{{ $slider->button_url }}" style="position:absolute;inset:0;z-index:2;" aria-label="Banner"></a>
                            @endif
                        </div>
                    @empty
                        <div class="zc-hero__slide">
                            <div style="position:relative;z-index:2;">
                                <span class="zc-hero__eyebrow"><svg width="28" height="2" viewBox="0 0 28 2"><rect width="20" height="2" fill="currentColor"/></svg> {{ $tv('hero_kicker', 'Featured collection') }}</span>
                                <h1>{{ $tv('hero_title', 'Everything you need, delivered to your door') }}</h1>
                                <p class="lede bn" style="margin-top:12px;">{{ $tv('hero_subtitle', 'আপনার পছন্দের পণ্য এখন আপনার ঘরে — আজই অর্ডার করুন।') }}</p>
                                <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap;">
                                    <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--honey">{{ $tv('hero_button', 'Shop the collection') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                                    <a href="#top-selling" class="zc-btn zc-btn--ghost">Best sellers</a>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
                @if ($heroSliders->count() > 1)
                    <button type="button" class="zc-hero__arrow zc-hero__arrow--prev" data-hero-prev aria-label="Previous slide">&#8249;</button>
                    <button type="button" class="zc-hero__arrow zc-hero__arrow--next" data-hero-next aria-label="Next slide">&#8250;</button>
                    <div class="zc-hero__dots">@for ($i = 0; $i < $heroSliders->count(); $i++)<button type="button" class="zc-hero__dot @if ($i === 0) is-active @endif" data-hero-dot="{{ $i }}" aria-label="Slide {{ $i + 1 }}"></button>@endfor</div>
                @endif
            </div>
            @php
                $sideHasText = $sideSlider && (filled($sideSlider->title) || filled($sideSlider->subtitle) || filled($sideSlider->badge_text) || filled($sideSlider->button_text));
                // Keep the image bright (~92%): only a light 8% wash when there's a
                // button/text so it can be read; a text-less banner shows the image clean.
                $sideBg = $sideImg
                    ? ($sideHasText
                        ? "background-image:linear-gradient(rgba(0,0,0,.08),rgba(0,0,0,.08)),url('".$sideImg."');background-size:cover;background-position:center;"
                        : "background-image:url('".$sideImg."');background-size:cover;background-position:center;")
                    : '';
            @endphp
            <div class="zc-hero__side">
                <div class="zc-hero__side-card" @if ($sideBg) style="{{ $sideBg }}" @endif>
                    @if ($sideSlider)
                        @if ($sideHasText)
                            @php $sideShadow = $sideImg ? 'text-shadow:0 1px 8px rgba(0,0,0,.6);' : ''; @endphp
                            @if (filled($sideSlider->badge_text))<span class="zc-hero__eyebrow" style="{{ $sideShadow }}">{{ $sideSlider->badge_text }}</span>@endif
                            @if (filled($sideSlider->title))<h1 style="font-size:26px;{{ $sideShadow }}">{{ $sideSlider->title }}</h1>@endif
                            @if (filled($sideSlider->subtitle))<p style="opacity:.95;font-size:14.5px;{{ $sideShadow }}">{{ $sideSlider->subtitle }}</p>@endif
                            @if (filled($sideSlider->button_text))<a href="{{ $sideSlider->button_url ?: route('storefront.products') }}" class="zc-btn zc-btn--primary zc-fire" style="align-self:flex-start;margin-top:6px;">{{ $sideSlider->button_text }}</a>@endif
                        @elseif (filled($sideSlider->button_url))
                            <a href="{{ $sideSlider->button_url }}" style="position:absolute;inset:0;z-index:2;" aria-label="Banner"></a>
                        @endif
                    @else
                        <span class="zc-hero__eyebrow">This week</span>
                        <h1 style="font-size:26px;">Free delivery over ৳3000</h1>
                        <p style="opacity:.9;font-size:14.5px;">Cash on delivery nationwide. Check every item at your door before you pay a single taka.</p>
                        <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary zc-fire" style="align-self:flex-start;margin-top:6px;">Order now</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURED CATEGORIES --}}
    @if ($activeCategories->isNotEmpty())
        <section class="zc-sec zc-wrap">
            <div class="zc-sec__head"><div class="zc-sec__title zc-center" style="margin:0 auto;">{{ $tv('heading_categories', 'Shop by category') }}</div></div>
            <div class="zc-featcats">
                @foreach ($activeCategories->take(8) as $cat)
                    @php $ci = $mediaUrl($cat->image ?? $cat->thumbnail ?? null); @endphp
                    <a href="{{ route('storefront.category.show', $cat->slug) }}" class="zc-featcat">
                        <span class="zc-featcat__img">
                            @if ($ci)<img src="{{ $ci }}" alt="{{ $cat->name }}">@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9 12 3l9 6v11H3z"/><path d="M9 20v-6h6v6"/></svg>@endif
                        </span>
                        <span>{{ $cat->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- TOP SELLING --}}
    @if ($topSelling->isNotEmpty())
        <section class="zc-sec zc-wrap" id="top-selling">
            <div class="zc-sec__head">
                <div class="zc-sec__title">{{ $tv('heading_top_selling', 'Top selling products') }}</div>
                <a href="{{ route('storefront.products') }}" class="zc-viewall">View all <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            </div>
            <div class="zc-grid zc-grid--5">
                @foreach ($topSelling as $product)@include('storefront.partials.product-card')@endforeach
            </div>
        </section>
    @endif

    {{-- OFFER BANNERS after Top Selling (Campaign/Offer > Offer Banner) --}}
    @include('storefront.partials.offer-banner', ['obPlacement' => 'after_top_selling_1'])
    @include('storefront.partials.offer-banner', ['obPlacement' => 'after_top_selling_2'])

    {{-- TRUST / PROMISE --}}
    <section class="zc-sec zc-sec--band">
        <div class="zc-wrap">
            <div class="zc-grid zc-grid--4" style="gap:14px;">
                @foreach ([
                    [$tv('trust_1_title', '100% Genuine'), $tv('trust_1_text', 'Authentic products, quality checked'), 'M12 2 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6z'],
                    [$tv('trust_2_title', 'Cash on Delivery'), $tv('trust_2_text', 'Pay only after you inspect it'), 'M3 7h18v10H3z|M3 11h18'],
                    [$tv('trust_3_title', 'Fast Delivery'), $tv('trust_3_text', '2–4 days Dhaka, 3–6 nationwide'), 'M1 6h15v9H1z|M16 9h4l3 3v3h-7'],
                    [$tv('trust_4_title', 'Easy Exchange'), $tv('trust_4_text', '7-day hassle-free returns'), 'M3 12a9 9 0 1 0 3-6|M3 6v6h6'],
                ] as [$t,$d,$path])
                    <div class="zc-card" style="padding:18px;display:flex;gap:14px;align-items:center;">
                        <span style="width:46px;height:46px;border-radius:12px;background:var(--leaf-soft);color:var(--leaf-deep);display:grid;place-items:center;flex:none;">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">@foreach (explode('|', $path) as $p)<path d="{{ $p }}"/>@endforeach</svg>
                        </span>
                        <span><span style="display:block;font-weight:800;font-size:14.5px;">{{ $t }}</span><span style="display:block;font-size:12.5px;color:var(--muted);">{{ $d }}</span></span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CATEGORY SHOWCASES --}}
    @foreach ($catSections as $cat)
        <section class="zc-sec zc-wrap">
            <div class="zc-sec__head">
                <div class="zc-sec__title">{{ $cat->name }}</div>
                <a href="{{ route('storefront.category.show', $cat->slug) }}" class="zc-viewall">View all <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            </div>
            <div class="zc-grid zc-grid--5">
                @foreach (($byCategory[$cat->id] ?? collect())->take(5) as $product)@include('storefront.partials.product-card')@endforeach
            </div>
        </section>
    @endforeach

    {{-- PROMO --}}
    <section class="zc-wrap" style="padding:10px 20px 4px;">
        <div class="zc-promo" @if ($promoImg) style="background-image:linear-gradient(105deg,rgba(9,20,13,.9),rgba(9,20,13,.4)),url('{{ $promoImg }}');background-size:cover;background-position:center;" @endif>
            <div style="position:relative;z-index:2;max-width:60ch;">
                @if ($promoSlider)
                    @if ($promoSlider->badge_text)<span class="zc-hero__eyebrow" style="color:var(--honey);">{{ $promoSlider->badge_text }}</span>@endif
                    <h2 style="font-size:clamp(22px,2.6vw,32px);margin-top:8px;">{{ $promoSlider->title }}</h2>
                    @if ($promoSlider->subtitle)<p style="opacity:.9;margin-top:8px;">{{ $promoSlider->subtitle }}</p>@endif
                    @if ($promoSlider->button_text)<a href="{{ $promoSlider->button_url ?: route('storefront.products') }}" class="zc-btn zc-btn--honey" style="margin-top:16px;">{{ $promoSlider->button_text }}</a>@endif
                @else
                    <span class="zc-hero__eyebrow" style="color:var(--honey);">বিশেষ কালেকশন</span>
                    <h2 style="font-size:clamp(22px,2.6vw,32px);margin-top:8px;">Great products at great prices — shop the latest today.</h2>
                    <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--honey" style="margin-top:16px;">Explore the collection</a>
                @endif
            </div>
        </div>
    </section>

    {{-- JUST FOR YOU --}}
    @if ($forYou->isNotEmpty())
        <section class="zc-sec zc-wrap">
            <div class="zc-sec__head">
                <div class="zc-sec__title">{{ $tv('heading_for_you', 'Just for you') }}</div>
                <a href="{{ route('storefront.products') }}" class="zc-viewall">View all <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            </div>
            <div class="zc-grid zc-grid--5">
                @foreach ($forYou as $product)@include('storefront.partials.product-card')@endforeach
            </div>
        </section>
    @endif

    {{-- REVIEWS --}}
    @if ($featuredReviews->isNotEmpty())
        <section class="zc-sec zc-sec--band">
            <div class="zc-wrap">
                <div class="zc-sec__head"><div class="zc-sec__title">{{ $tv('heading_reviews', 'Loved by our customers') }}</div></div>
                <div class="zc-reviews">
                    @foreach ($featuredReviews as $review)
                        @php $rt = (int) ($review['rating'] ?? 5); @endphp
                        <div class="zc-review">
                            <span class="stars">{{ str_repeat('★', $rt) }}{{ str_repeat('☆', 5 - $rt) }}</span>
                            <p>“{{ $review['body'] ?: 'Fresh, genuine and delivered fast.' }}”</p>
                            <div class="zc-review__who">
                                <span class="zc-review__av">{{ strtoupper(substr($review['reviewer_name'] ?? 'Z', 0, 1)) }}</span>
                                <span>
                                    <span class="zc-review__nm">{{ $review['reviewer_name'] ?? 'Verified customer' }}</span>
                                    <span class="zc-review__meta">✓ Verified purchase{{ !empty($review['product_name']) ? ' · '.$review['product_name'] : '' }}</span>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- OFFER BANNER above the footer (Campaign/Offer > Offer Banner) --}}
    @include('storefront.partials.offer-banner', ['obPlacement' => 'before_footer'])

@endsection

{{-- Hero carousel CSS/JS moved to public/assets/storefront.css / .js (linked
     once from layouts/app.blade.php) — see there for the [data-hero] rules
     and behavior. --}}
