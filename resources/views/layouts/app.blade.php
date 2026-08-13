@php
    $themeSettings = $themeSettings ?? collect();
    $themeMediaUrl = $themeMediaUrl ?? fn (string $key): ?string => null;
    $themeValue = fn (string $key, mixed $fallback = null) => filled($themeSettings->get($key)) ? $themeSettings->get($key) : $fallback;
    $themeEnabled = fn (string $key, bool $default = true) => filter_var($themeValue($key, $default), FILTER_VALIDATE_BOOLEAN);
    $settingService = app(\App\Modules\Settings\Services\SettingService::class);
    $storeName = $storeName ?? ($themeValue('brand_name', $settingService->get('general', 'site_name')) ?: 'Store');
    $brandSlogan = $brandSlogan ?? $themeValue('brand_slogan', 'Quality you can trust');
    $siteLogoUrl = $themeMediaUrl('site_logo');
    $faviconUrl = $themeMediaUrl('site_favicon');
    $footerText = $themeValue('footer_description', $themeValue('footer_text', 'Quality products delivered straight to your door — trusted service, fair prices, cash on delivery across Bangladesh.'));
    $footerCopyright = $themeValue('footer_copyright', $storeName.'. All rights reserved.');
    $showTracking = $themeEnabled('show_tracking', true);
    $showAccount = $themeEnabled('show_account', true);
    $activeCategories = $activeCategories ?? collect();
    $customerLoggedIn = session()->has('customer_id');
    try {
        $cartService = app(\App\Modules\Checkout\Services\CartService::class);
        $cartCount = $cartService->count();
        $cartPreviewItems = $cartCount > 0 ? collect($cartService->items())->take(4) : collect();
        $cartTotal = (float) ($cartService->summary()['total'] ?? 0);
    } catch (\Throwable $e) {
        $cartCount = 0; $cartPreviewItems = collect(); $cartTotal = 0;
    }
    $socialLinks = array_filter([
        'facebook' => $themeValue('social_facebook', $themeValue('facebook_url')),
        'instagram' => $themeValue('social_instagram', $themeValue('instagram_url')),
        'youtube' => $themeValue('social_youtube', $themeValue('youtube_url')),
        'whatsapp' => $themeValue('social_whatsapp', $themeValue('contact_whatsapp')),
    ]);
    $contactPhone = $themeValue('contact_phone');
    $contactEmail = $themeValue('contact_email');
    $contactAddress = $themeValue('contact_address');
    $cmsFooterPages = $cmsFooterPages ?? collect();

    // WhatsApp click-to-chat (number set in Studio → Website Setup → Footer →
    // Social links). Normalise to an international wa.me target: a BD local
    // number like "01814…" becomes "8801814…". A default message is pre-filled
    // into the customer's chat box (editable in Website Setup).
    $whatsappDigits = preg_replace('/\D/', '', (string) ($socialLinks['whatsapp'] ?? ''));
    if ($whatsappDigits !== '' && str_starts_with($whatsappDigits, '0')) {
        $whatsappDigits = '88'.$whatsappDigits;
    }
    $whatsappLink = null;
    if (strlen($whatsappDigits) >= 10) {
        $whatsappMsg = trim((string) $themeValue('social_whatsapp_message', 'আসসালামু আলাইকুম! 🌿 আমি '.$storeName.' এর একটি পণ্য সম্পর্কে জানতে / অর্ডার করতে চাই।'));
        $whatsappLink = 'https://wa.me/'.$whatsappDigits;
        if ($whatsappMsg !== '') {
            $whatsappLink .= '?text='.rawurlencode($whatsappMsg);
        }
    }

    // Anti-copy / content protection (Studio → Setting & Configuration →
    // Content Protection). A deterrent for casual copying — it can't stop a
    // determined viewer, and it never touches the Studio admin.
    $acOn = filter_var($settingService->get('general', 'public_anti_copy_enabled', false), FILTER_VALIDATE_BOOLEAN);
    $acRightClick = $acOn && filter_var($settingService->get('general', 'public_disable_right_click', false), FILTER_VALIDATE_BOOLEAN);
    $acSelection = $acOn && filter_var($settingService->get('general', 'public_disable_text_selection', false), FILTER_VALIDATE_BOOLEAN);
    $acCopy = $acOn && filter_var($settingService->get('general', 'public_disable_copy_shortcuts', false), FILTER_VALIDATE_BOOLEAN);
    $acDevtools = $acOn && filter_var($settingService->get('general', 'public_disable_devtool_shortcuts', false), FILTER_VALIDATE_BOOLEAN);

    // yieldContent() already HTML-escapes its result once (Blade's inline
    // @section('title', '...') form escapes at definition time — see
    // ManagesLayouts::startSection()); the $seoPayload branch pulls raw
    // database content (product/category meta fields) that was never
    // escaped, so it needs an explicit e() here instead. Either way
    // $seoTitle/$seoDesc end up escaped exactly once — print them with
    // {!! !!} below, not {{ }}, or '&' becomes the literal text '&amp;'.
    $seoTitle = (isset($seoPayload['title']) && $seoPayload['title']) ? e($seoPayload['title']) : trim((string) $__env->yieldContent('title', $storeName));
    $seoDesc = (isset($seoPayload['description']) && $seoPayload['description']) ? e($seoPayload['description']) : trim((string) $__env->yieldContent('meta_description', 'Quality products delivered across Bangladesh with cash on delivery.'));
    $facebookPixelId = trim((string) $settingService->get('general', 'facebook_pixel_id', ''));
    $facebookPixelActive = filter_var($settingService->get('general', 'facebook_pixel_enabled', false), FILTER_VALIDATE_BOOLEAN) && $facebookPixelId !== '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{!! $seoTitle !!}</title>
    <meta name="description" content="{!! $seoDesc !!}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{!! $seoTitle !!}">
    <meta property="og:description" content="{!! $seoDesc !!}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($faviconUrl)<link rel="icon" href="{{ $faviconUrl }}">@endif
    @include('storefront.partials.design-system')
    {{-- Website Setup → Theme Color & Font Family (overrides the design-system defaults) --}}
    @php
        $themeFont = $themeValue('font_family', 'Plus Jakarta Sans');
        $themeSet = fn (string $k) => filled($themeSettings->get($k));
    @endphp
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $themeFont) }}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --leaf:{{ $themeValue('primary_color', '#1f7a3d') }};
            --leaf-deep:{{ $themeValue('menu_bg_color', '#155e2e') }};
            --honey:{{ $themeValue('primary_hover_color', '#f2a20c') }};
            --sale:{{ $themeValue('discount_price_color', '#e0483a') }};
            --line:{{ $themeValue('default_border_color', '#e8e1d3') }};
        }
        body{ font-family:'{{ $themeFont }}','Hind Siliguri',sans-serif; }
        @if ($themeSet('primary_text_color')).zc-btn--primary{color:{{ $themeValue('primary_text_color') }};}@endif
        @if ($themeSet('footer_bg_color') || $themeSet('footer_text_color')).zc-footer{background:{{ $themeValue('footer_bg_color', '#12271a') }};color:{{ $themeValue('footer_text_color', '#cfe0d4') }};}@endif
        @if ($themeSet('footer_hover_color')).zc-footer a:hover{color:{{ $themeValue('footer_hover_color') }} !important;}@endif
        @if ($themeSet('cart_bg_color') || $themeSet('cart_text_color')).pcard__add{background:{{ $themeValue('cart_bg_color', '#1f7a3d') }};color:{{ $themeValue('cart_text_color', '#ffffff') }};border-color:{{ $themeValue('cart_border_color', '#1f7a3d') }};}@endif
        @if ($themeSet('cart_hover_color')).pcard__add:hover{background:{{ $themeValue('cart_hover_color') }};color:{{ $themeValue('cart_hover_text_color', '#ffffff') }};border-color:{{ $themeValue('cart_hover_color') }};}@endif
    </style>
    @stack('storefront-styles')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @if ($facebookPixelActive)
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($facebookPixelId));fbq('track','PageView');</script>
    @endif
    <style>
        /* ===== Announcement bar ===== */
        /* Static & centered by default (fits → no repeat). JS adds .is-scrolling
           only when the messages overflow, so the text is never visibly doubled. */
        .zc-announce{overflow:hidden;padding:0;position:relative;}
        /* A soft light sheen sweeps across the bar for a premium feel. */
        .zc-announce::after{content:"";position:absolute;top:0;bottom:0;left:-25%;width:18%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.22),transparent);transform:skewX(-18deg);animation:zcAnnSheen 6.5s ease-in-out infinite;pointer-events:none;z-index:0;}
        .zc-marquee{overflow:hidden;position:relative;z-index:1;}
        .zc-marquee__track{display:flex;justify-content:center;align-items:center;padding:8px 0;}
        .zc-marquee__group{display:inline-flex;align-items:center;white-space:nowrap;flex:none;}
        .zc-marquee.is-scrolling .zc-marquee__track{justify-content:flex-start;width:max-content;animation:zcMarquee var(--marq-dur,30s) linear infinite;will-change:transform;}
        .zc-marquee.is-scrolling:hover .zc-marquee__track{animation-play-state:paused;}
        .zc-marquee__item{padding:0 10px;display:inline-block;animation:zcAnnGlow 3.4s ease-in-out infinite;}
        .zc-marquee__sep{color:var(--honey);opacity:.85;font-size:10px;padding:0 4px;display:inline-block;animation:zcAnnTwinkle 2.4s ease-in-out infinite;}
        @keyframes zcMarquee{from{transform:translateX(0);}to{transform:translateX(-50%);}}
        @keyframes zcAnnSheen{0%,8%{left:-25%;}55%,100%{left:120%;}}
        @keyframes zcAnnGlow{0%,100%{text-shadow:0 0 0 rgba(255,245,204,0);}50%{text-shadow:0 0 9px rgba(255,245,204,.5);}}
        @keyframes zcAnnTwinkle{0%,100%{opacity:.35;transform:scale(.8);}50%{opacity:1;transform:scale(1.25);}}
        @media(prefers-reduced-motion:reduce){.zc-marquee.is-scrolling .zc-marquee__track{animation:none;}.zc-announce::after,.zc-marquee__item,.zc-marquee__sep{animation:none;}}

        /* ===== Contained shimmer on the Buy-now / Order-now CTA buttons =====
           A light sweep INSIDE the button only — no page-wide glow. Landing-page
           CTAs keep their own template effects (untouched). */
        .zc-fire { position: relative; overflow: hidden; }
        .zc-fire::after {
            content:""; position:absolute; top:0; left:-60%; width:45%; height:100%; pointer-events:none;
            background:linear-gradient(115deg,transparent,rgba(255,255,255,.5),transparent);
            transform:skewX(-18deg); animation:zcFireShine 2.8s ease-in-out infinite;
        }
        @keyframes zcFireShine { 0%{left:-60%;} 55%,100%{left:130%;} }
        @media(prefers-reduced-motion:reduce){ .zc-fire::after { display:none; } }

        /* ===== Mobile: menu icon on the top-left (mirrors search on the right) ===== */
        @media(max-width:820px){
            .zc-menu-btn{ display:inline-flex !important; position:absolute; left:0; top:50%; transform:translateY(-50%); padding:8px; }
            .zc-menu-btn svg{ width:23px; height:23px; }
        }
        /* ===== Mobile: footer links in 3 columns (brand full-width above) ===== */
        @media(max-width:560px){
            .zc-footer__top{ grid-template-columns:repeat(3,1fr); gap:22px 8px; text-align:left; }
            .zc-footer__brand{ grid-column:1 / -1; text-align:center; margin-bottom:6px; }
            .zc-footer__top h4{ font-size:13px; margin-bottom:9px; }
            .zc-footer__links a, .zc-footer__links span{ font-size:11.5px; line-height:1.5; }
            .zc-footer__links span{ padding:3px 0 !important; }
        }

        /* ===== Countdown / flash-sale bar ===== */
        .zc-countbar{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;text-decoration:none;
            background:linear-gradient(90deg,var(--sale),#c0392b);color:#fff;font-weight:700;font-size:13.5px;padding:9px 16px;text-align:center;
            transition:filter .15s ease;}
        .zc-countbar:hover{filter:brightness(1.05);}
        .zc-countbar__title{letter-spacing:.01em;}
        .zc-countbar__timer{display:inline-flex;align-items:center;gap:3px;}
        .zc-countbar__timer b{background:rgba(255,255,255,.2);padding:2px 7px;border-radius:6px;font-variant-numeric:tabular-nums;font-weight:800;min-width:26px;display:inline-block;text-align:center;}
        .zc-countbar__timer i{font-style:normal;opacity:.85;margin:0 3px 0 1px;font-size:11.5px;}
        .zc-countbar__cta{background:#fff;color:var(--sale);padding:4px 13px;border-radius:999px;font-weight:800;font-size:12.5px;white-space:nowrap;}
        @media(max-width:560px){.zc-countbar{font-size:12px;gap:9px;padding:8px 12px;}.zc-countbar__title{width:100%;}}

        /* ===== Welcome popup ===== */
        .zc-pop{position:fixed;inset:0;z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;}
        .zc-pop.is-open{display:flex;}
        .zc-pop__backdrop{position:absolute;inset:0;background:rgba(10,20,14,.62);backdrop-filter:blur(3px);animation:zcPopFade .25s ease;}
        .zc-pop__box{position:relative;z-index:1;background:#fff;border-radius:18px;overflow:hidden;max-width:400px;width:100%;box-shadow:0 40px 90px -30px rgba(0,0,0,.6);animation:zcPopIn .3s cubic-bezier(.2,.8,.3,1);}
        .zc-pop__x{position:absolute;top:10px;right:10px;z-index:2;width:34px;height:34px;border-radius:50%;border:none;background:rgba(255,255,255,.92);color:var(--ink);font-size:15px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.18);line-height:1;}
        .zc-pop__x:hover{background:#fff;}
        .zc-pop__link{display:block;text-decoration:none;color:var(--ink);}
        .zc-pop__img{width:100%;height:auto;display:block;}
        .zc-pop__body{padding:22px 22px 24px;text-align:center;}
        .zc-pop__title{font-size:22px;font-weight:800;margin-bottom:6px;}
        .zc-pop__text{color:var(--muted);font-size:14.5px;line-height:1.55;margin-bottom:16px;}
        .zc-pop__cta{display:inline-block;background:var(--leaf);color:#fff;font-weight:800;padding:11px 26px;border-radius:999px;box-shadow:0 12px 24px -12px rgba(31,122,61,.6);}
        @keyframes zcPopIn{from{opacity:0;transform:translateY(22px) scale(.96);}to{opacity:1;transform:none;}}
        @keyframes zcPopFade{from{opacity:0;}to{opacity:1;}}
        @media(prefers-reduced-motion:reduce){.zc-marquee__track{animation:none;}.zc-pop__box,.zc-pop__backdrop{animation:none;}}
    </style>
</head>
<body>
<a href="#main-content" class="sr-only focus:not-sr-only" style="position:absolute;top:.5rem;left:.5rem;z-index:100;background:#fff;color:#18251c;padding:.5rem 1rem;border-radius:.5rem;font-weight:700;">Skip to content</a>

{{-- Announcement (premium scrolling marquee) --}}
@php
    $announceMsgs = collect([
        $themeValue('announce_1', 'Free delivery over ৳3000'),
        $themeValue('announce_2', 'Cash on delivery across Bangladesh'),
        $themeValue('announce_3', '100% genuine products, guaranteed'),
    ])->filter(fn ($m) => filled($m))->values();
@endphp
@if ($announceMsgs->isNotEmpty())
<div class="zc-announce zc-no-print" role="region" aria-label="Store announcements">
    <div class="zc-marquee" data-marquee>
        <div class="zc-marquee__track">
            <div class="zc-marquee__group">
                @foreach ($announceMsgs as $msg)
                    <span class="zc-marquee__item">{{ $msg }}</span><span class="zc-marquee__sep" aria-hidden="true">✦</span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Countdown / Flash Sale bar (Studio → Website Setup → Promotions) --}}
@php
    $cdEnabled = filter_var($themeValue('countdown_enabled', false), FILTER_VALIDATE_BOOLEAN);
    $cdEnds = null;
    try {
        $cdRaw = trim((string) $themeValue('countdown_ends_at', ''));
        $cdEnds = $cdRaw !== '' ? \Illuminate\Support\Carbon::parse($cdRaw) : null;
    } catch (\Throwable $e) { $cdEnds = null; }
    $cdShow = $cdEnabled && $cdEnds && $cdEnds->isFuture();
    $cdLink = trim((string) $themeValue('countdown_link', '/products')) ?: '/products';
@endphp
@if ($cdShow)
<a href="{{ $cdLink }}" class="zc-countbar zc-no-print" data-countdown="{{ $cdEnds->getTimestamp() * 1000 }}">
    <span class="zc-countbar__title">{{ $themeValue('countdown_title', '⚡ Flash Sale ends in') }}</span>
    <span class="zc-countbar__timer" data-count-timer>
        <b data-d>00</b><i>d</i> <b data-h>00</b><i>h</i> <b data-m>00</b><i>m</i> <b data-s>00</b><i>s</i>
    </span>
    <span class="zc-countbar__cta">{{ $themeValue('countdown_cta', 'Shop now') }} →</span>
</a>
@endif

{{-- Header --}}
<header class="zc-header zc-no-print">
    <div class="zc-wrap">
        <div class="zc-header__row">
            <button class="zc-act zc-menu-btn" type="button" aria-label="Menu" data-drawer-open>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('storefront.home') }}" class="zc-brand" aria-label="{{ $storeName }} home">
                @if ($siteLogoUrl)
                    <img src="{{ $siteLogoUrl }}" alt="{{ $storeName }}" style="height:42px;width:auto;">
                @else
                    <span class="zc-brand__mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c5-2 8-6 8-11V5l-8-2-8 2v5c0 5 3 9 8 11Z"/><path d="M9.5 12.5 11.5 14.5 15 10.5"/></svg></span>
                    <span><span class="zc-brand__name">{{ $storeName }}</span><span class="zc-brand__sub">{{ $brandSlogan }}</span></span>
                @endif
            </a>

            <form class="zc-search" action="{{ route('storefront.products') }}" method="GET" role="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $themeValue('search_placeholder', 'Search for products…') }}" aria-label="Search products" data-suggest autocomplete="off">
                <button type="submit">Search</button>
            </form>

            <button type="button" class="zc-hsearch-btn" data-msearch-open aria-label="Search products">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
            </button>

            <div class="zc-header__actions">
                @if ($showTracking)
                    <a href="{{ route('tracking.form') }}" class="zc-act zc-act--hidem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="1" y="6" width="15" height="11" rx="1"/><path d="M16 9h4l3 3v5h-7z"/><circle cx="6" cy="19" r="1.6"/><circle cx="18" cy="19" r="1.6"/></svg>
                        <span>Track</span>
                    </a>
                @endif
                @if ($showAccount)
                    <a href="{{ $customerLoggedIn ? route('customer.dashboard') : route('customer.login') }}" class="zc-act zc-act--hidem">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                        <span>{{ $customerLoggedIn ? 'Account' : 'Sign in' }}</span>
                    </a>
                @endif
                <a href="{{ route('cart.index') }}" class="zc-act" aria-label="Cart" data-cart-open>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/><circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/></svg>
                    <span>Cart</span>
                    @if ($cartCount > 0)<span class="zc-act__badge">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>
    </div>

    {{-- Category nav --}}
    <nav class="zc-catnav" aria-label="Categories">
        <div class="zc-wrap">
            <div class="zc-catnav__row">
                <a href="{{ route('storefront.products') }}" class="zc-catnav__all">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg> All Products
                </a>
                @forelse ($activeCategories->take(11) as $navCat)
                    <a href="{{ route('storefront.category.show', $navCat->slug) }}">{{ $navCat->name }}</a>
                @empty
                    <a href="{{ route('storefront.products') }}">Shop</a>
                @endforelse
            </div>
        </div>
    </nav>
</header>

<main id="main-content">
    @if (session('success'))
        <div class="zc-wrap zc-no-print" style="margin-top:16px;"><div class="zc-note">{{ session('success') }} <a href="{{ route('cart.index') }}" style="font-weight:800;text-decoration:underline;">View cart</a></div></div>
    @endif
    @yield('content')
</main>

{{-- Footer --}}
<footer class="zc-footer zc-no-print">
    <div class="zc-wrap zc-footer__top">
        <div class="zc-footer__brand">
            <a href="{{ route('storefront.home') }}" class="zc-brand" aria-label="{{ $storeName }} home">
                @if ($siteLogoUrl)
                    <img src="{{ $siteLogoUrl }}" alt="{{ $storeName }}" style="height:46px;width:auto;">
                @else
                    <span class="zc-brand__mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c5-2 8-6 8-11V5l-8-2-8 2v5c0 5 3 9 8 11Z"/><path d="M9.5 12.5 11.5 14.5 15 10.5"/></svg></span>
                    <span class="zc-brand__name">{{ $storeName }}</span>
                @endif
            </a>
            @if ($brandSlogan)<div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;opacity:.75;margin-top:8px;">{{ $brandSlogan }}</div>@endif
            <p>{{ $footerText }}</p>
            <div class="zc-footer__contact">
                @if ($contactAddress)<span style="display:flex;gap:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $contactAddress }}</span>@endif
                @if ($contactPhone)<span style="display:flex;gap:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5c0 9 6 15 15 15l-.5-4-4-1-2 2c-2-1-4-3-5-5l2-2-1-4z"/></svg>{{ $contactPhone }}</span>@endif
                @if ($contactEmail)<span style="display:flex;gap:8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>{{ $contactEmail }}</span>@endif
            </div>
            @php
                // Real per-network icons — no more generic dots. WhatsApp is
                // skipped here because it has its own floating/bottom-nav button.
                $socialIcons = [
                    'facebook' => '<path d="M14 9h3l.5-3H14V4.5c0-.9.3-1.5 1.6-1.5H17V.3C16.6.2 15.6 0 14.5 0 12.1 0 10.5 1.5 10.5 4.2V6H8v3h2.5v9H14z"/>',
                    'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.2" cy="6.8" r="1.3"/>',
                    'youtube' => '<path d="M23 12s0-3.5-.45-5.17a2.7 2.7 0 0 0-1.9-1.9C18.98 4.5 12 4.5 12 4.5s-6.98 0-8.65.43a2.7 2.7 0 0 0-1.9 1.9C1 8.5 1 12 1 12s0 3.5.45 5.17a2.7 2.7 0 0 0 1.9 1.9c1.67.43 8.65.43 8.65.43s6.98 0 8.65-.43a2.7 2.7 0 0 0 1.9-1.9C23 15.5 23 12 23 12ZM9.75 15.02V8.98L15.5 12Z"/>',
                ];
                $footerSocials = collect($socialLinks)->except('whatsapp')->filter();
            @endphp
            @if ($footerSocials->isNotEmpty())
                <div class="zc-footer__soc">
                    @foreach ($footerSocials as $net => $url)
                        @if (isset($socialIcons[$net]))
                            <a href="{{ $url }}" aria-label="{{ ucfirst($net) }}" rel="noopener" target="_blank">
                                <svg viewBox="0 0 24 24" fill="currentColor">{!! $socialIcons[$net] !!}</svg>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h4>Shop</h4>
            <div class="zc-footer__links">
                <a href="{{ route('storefront.products') }}">All Products</a>
                @foreach ($activeCategories->take(5) as $c)<a href="{{ route('storefront.category.show', $c->slug) }}">{{ $c->name }}</a>@endforeach
            </div>
        </div>
        <div>
            <h4>Support</h4>
            <div class="zc-footer__links">
                @if ($showTracking)<a href="{{ route('tracking.form') }}">Track your order</a>@endif
                @if ($showAccount)<a href="{{ $customerLoggedIn ? route('customer.dashboard') : route('customer.login') }}">My Account</a>@endif
                @foreach ($cmsFooterPages->take(4) as $page)<a href="{{ route('storefront.cms-pages.show', $page->slug) }}">{{ $page->title }}</a>@endforeach
            </div>
        </div>
        <div>
            <h4>Why {{ \Illuminate\Support\Str::words($storeName, 1, '') }}</h4>
            <div class="zc-footer__links" style="opacity:.85;">
                @foreach ([
                    $themeValue('footer_why_1', '100% genuine products'),
                    $themeValue('footer_why_2', 'Cash on delivery'),
                    $themeValue('footer_why_3', 'Inspect before you pay'),
                    $themeValue('footer_why_4', 'Easy 7-day exchange'),
                ] as $why)
                    @if (filled($why))<span style="display:block;padding:5px 0;">✓ {{ $why }}</span>@endif
                @endforeach
            </div>
        </div>
    </div>
    <div class="zc-footer__bottom">
        <div class="zc-wrap">
            <span>&copy; {{ date('Y') }} {{ $footerCopyright }}</span>
        </div>
    </div>
</footer>

{{-- Floating WhatsApp contact button (bottom-left). Hidden on mobile, where
     the bottom nav carries WhatsApp instead. --}}
@if ($whatsappLink)
<a href="{{ $whatsappLink }}" target="_blank" rel="noopener" class="zc-wafab zc-no-print" aria-label="Chat with us on WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.8 1-.3.2-.5 0a6.5 6.5 0 0 1-1.9-1.2 7.3 7.3 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.4v-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3A2.8 2.8 0 0 0 5.9 10a5 5 0 0 0 1 2.6 11 11 0 0 0 4.2 3.7c2 .8 2 .5 2.4.5a2.5 2.5 0 0 0 1.6-1.1 2 2 0 0 0 .1-1.1c0-.1-.2-.2-.4-.3Z"/></svg>
    <span class="zc-wafab__label">WhatsApp</span>
</a>
@endif

{{-- Mobile bottom nav --}}
<nav class="zc-botnav zc-no-print" aria-label="Mobile">
    <div class="zc-botnav__row">
        <a href="{{ route('storefront.home') }}" @class(['is-active' => request()->routeIs('storefront.home')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/></svg><span>Home</span>
        </a>
        @if ($showTracking)
        <a href="{{ route('tracking.form') }}" @class(['is-active' => request()->routeIs('tracking.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="6" width="13" height="10" rx="1.5"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="6" cy="18" r="1.7"/><circle cx="17" cy="18" r="1.7"/></svg><span>Track</span>
        </a>
        @endif
        <a href="{{ route('cart.index') }}" style="position:relative;" data-cart-open @class(['is-active' => request()->routeIs('cart.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/><circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/></svg><span>Cart</span>
            @if ($cartCount > 0)<span class="zc-botnav__badge">{{ $cartCount }}</span>@endif
        </a>
        @if ($whatsappLink)
        <a href="{{ $whatsappLink }}" target="_blank" rel="noopener" class="zc-botnav__wa" aria-label="Chat on WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.8 1-.3.2-.5 0a6.5 6.5 0 0 1-1.9-1.2 7.3 7.3 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.4v-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3A2.8 2.8 0 0 0 5.9 10a5 5 0 0 0 1 2.6 11 11 0 0 0 4.2 3.7c2 .8 2 .5 2.4.5a2.5 2.5 0 0 0 1.6-1.1 2 2 0 0 0 .1-1.1c0-.1-.2-.2-.4-.3Z"/></svg><span>WhatsApp</span>
        </a>
        @else
        <button type="button" data-msearch-open style="background:none;border:none;color:inherit;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 2px;font-size:10.5px;font-weight:600;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg><span>Search</span>
        </button>
        @endif
        <a href="{{ $showAccount ? ($customerLoggedIn ? route('customer.dashboard') : route('customer.login')) : route('storefront.home') }}" @class(['is-active' => request()->routeIs('customer.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg><span>Account</span>
        </a>
    </div>
</nav>

{{-- Mobile drawer --}}
<div class="zc-drawer zc-no-print" data-drawer>
    <div class="zc-drawer__scrim" data-drawer-close></div>
    <div class="zc-drawer__panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span class="zc-brand__name">{{ $storeName }}</span>
            <button type="button" data-drawer-close aria-label="Close" style="background:none;border:none;font-size:26px;line-height:1;color:var(--muted);cursor:pointer;">&times;</button>
        </div>
        <form class="zc-dsearch" action="{{ route('storefront.products') }}" method="GET" role="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
            <input type="search" name="q" placeholder="Search products…" aria-label="Search" data-suggest autocomplete="off">
        </form>
        <a href="{{ route('storefront.products') }}" style="font-weight:800;color:var(--leaf-deep);">All Products</a>
        @foreach ($activeCategories->take(12) as $c)<a href="{{ route('storefront.category.show', $c->slug) }}">{{ $c->name }}</a>@endforeach
        @if ($showTracking)<a href="{{ route('tracking.form') }}">Track Order</a>@endif
        <a href="{{ $customerLoggedIn ? route('customer.dashboard') : route('customer.login') }}">{{ $customerLoggedIn ? 'My Account' : 'Sign in' }}</a>
    </div>
</div>

{{-- Mobile search overlay --}}
<div class="zc-msearch zc-no-print" data-msearch aria-hidden="true">
    <form action="{{ route('storefront.products') }}" method="GET" role="search">
        <button type="button" data-msearch-close aria-label="Close search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg></button>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search for products…" autocomplete="off" aria-label="Search products" data-msearch-input data-suggest>
        <button type="submit" aria-label="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg></button>
    </form>
</div>

{{-- Slide-out cart drawer --}}
<div class="zc-cartdrawer zc-no-print" data-cart-drawer aria-hidden="true">
    <div class="zc-cartdrawer__scrim" data-cart-close></div>
    <aside class="zc-cartdrawer__panel" role="dialog" aria-label="Shopping cart">
        <div class="zc-cartdrawer__head">
            <span>SHOPPING CART</span>
            <button type="button" data-cart-close>Close <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        </div>
        <div class="zc-cartdrawer__body" data-cart-body><div class="zc-cd-loading">Loading…</div></div>
    </aside>
</div>
<style>
    /* Fly-to-cart animation */
    .zc-fly{position:fixed;z-index:2000;border-radius:12px;object-fit:cover;pointer-events:none;box-shadow:0 16px 36px -10px rgba(0,0,0,.55);transition:transform .85s cubic-bezier(.5,-0.15,.25,1),opacity .85s ease;will-change:transform,opacity;}
    .zc-cartbump{animation:zc-cartbump .5s ease;}
    @keyframes zc-cartbump{0%,100%{transform:scale(1);}35%{transform:scale(1.6);}}
    .pcard__add.is-added,.zc-btn.is-added{background:var(--leaf-deep)!important;color:#fff!important;border-color:var(--leaf-deep)!important;}
    @media (prefers-reduced-motion:reduce){.zc-fly{display:none;}}
    /* Mobile search overlay */
    .zc-msearch{position:fixed;top:0;left:0;right:0;z-index:1100;background:#fff;box-shadow:0 8px 24px -12px rgba(0,0,0,.35);transform:translateY(-115%);transition:transform .28s cubic-bezier(.4,0,.2,1);padding:10px 12px;padding-top:calc(10px + env(safe-area-inset-top));visibility:hidden;}
    .zc-msearch.is-open{transform:translateY(0);visibility:visible;}
    .zc-msearch form{display:flex;align-items:center;gap:8px;max-width:760px;margin:0 auto;}
    .zc-msearch input{flex:1;min-width:0;height:46px;border:1.6px solid var(--line);border-radius:999px;padding:0 18px;font-size:15px;font-family:inherit;color:var(--ink);outline:none;background:var(--surface);}
    .zc-msearch input:focus{border-color:var(--leaf);box-shadow:0 0 0 4px var(--leaf-soft);}
    .zc-msearch button{flex:none;width:46px;height:46px;border-radius:50%;border:none;display:grid;place-items:center;cursor:pointer;background:var(--leaf);color:#fff;}
    .zc-msearch button[data-msearch-close]{background:transparent;color:var(--ink);}
    .zc-msearch svg{width:20px;height:20px;}
    .zc-cartdrawer{position:fixed;inset:0;z-index:1000;visibility:hidden;}
    .zc-cartdrawer.is-open{visibility:visible;}
    .zc-cartdrawer__scrim{position:absolute;inset:0;background:rgba(15,23,20,.5);opacity:0;transition:opacity .25s;}
    .zc-cartdrawer.is-open .zc-cartdrawer__scrim{opacity:1;}
    /* Mobile: cart is a bottom sheet that slides up from the bottom */
    .zc-cartdrawer__panel{position:absolute;left:0;right:0;bottom:0;top:auto;height:auto;max-height:88vh;width:100%;background:#fff;display:flex;flex-direction:column;border-radius:22px 22px 0 0;box-shadow:0 -18px 44px -22px rgba(0,0,0,.45);transform:translateY(100%);transition:transform .34s cubic-bezier(.32,.72,0,1);}
    .zc-cartdrawer.is-open .zc-cartdrawer__panel{transform:translateY(0);}
    .zc-cartdrawer__panel::before{content:"";display:block;flex:none;width:42px;height:4px;border-radius:999px;background:#d8d2c4;margin:9px auto 0;}
    /* Desktop: cart opens as a dropdown under the cart icon (top-to-bottom reveal) */
    @media(min-width:821px){
        .zc-cartdrawer__scrim{background:rgba(15,23,20,.16);}
        .zc-cartdrawer__panel{top:74px;right:20px;left:auto;bottom:auto;height:auto;max-height:min(74vh,580px);width:min(400px,94vw);border-radius:18px;box-shadow:0 30px 64px -22px rgba(0,0,0,.42);transform:translateY(-14px) scaleY(.92);transform-origin:top right;opacity:0;transition:transform .28s cubic-bezier(.34,1.15,.4,1),opacity .2s ease;overflow:hidden;}
        .zc-cartdrawer.is-open .zc-cartdrawer__panel{transform:translateY(0) scaleY(1);opacity:1;}
        .zc-cartdrawer__panel::before{display:none;}
    }
    .zc-cartdrawer__head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid #eee;font-weight:800;letter-spacing:.04em;color:#1a1a1a;}
    .zc-cartdrawer__head button{display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:#e67e22;font-weight:700;cursor:pointer;font-size:14px;}
    .zc-cartdrawer__body{flex:1;overflow-y:auto;display:flex;flex-direction:column;}
    .zc-cd-loading{padding:40px;text-align:center;color:#999;}
    /* offer bar */
    .zc-cd-offer{margin:14px;padding:12px 14px;border-radius:12px;background:linear-gradient(135deg,#fff3dc,#ffe6bf);border:1px solid #ffd699;}
    .zc-cd-offer.is-unlocked{background:linear-gradient(135deg,#e7f6ec,#cdeed7);border-color:#a6dcb8;}
    .zc-cd-offer__row{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#7a4c00;line-height:1.35;}
    .zc-cd-offer.is-unlocked .zc-cd-offer__row{color:#1c6b3c;}
    .zc-cd-offer__row .gift{font-size:18px;}
    .zc-cd-offer__bar{margin-top:9px;height:7px;border-radius:999px;background:rgba(0,0,0,.08);overflow:hidden;}
    .zc-cd-offer__bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#f2a20c,#f4b840);transition:width .4s;}
    .zc-cd-offer.is-unlocked .zc-cd-offer__bar span{background:linear-gradient(90deg,#1c8a4e,#22a35c);}
    /* items */
    .zc-cd-items{padding:4px 14px;}
    .zc-cd-item{display:flex;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #f0efe9;}
    .zc-cd-item__img{width:58px;height:58px;border-radius:10px;overflow:hidden;background:#f6f4ee;flex:none;display:grid;place-items:center;}
    .zc-cd-item__img img{width:100%;height:100%;object-fit:cover;}
    .zc-cd-item__mid{flex:1;min-width:0;}
    .zc-cd-item__mid .nm{font-weight:700;font-size:13.5px;color:#222;line-height:1.25;}
    .zc-cd-item__mid .row{display:flex;align-items:center;gap:10px;margin-top:7px;flex-wrap:wrap;}
    .zc-cd-item .qty{display:inline-flex;align-items:center;border:1px solid #e2ded2;border-radius:8px;overflow:hidden;}
    .zc-cd-item .qty button{width:26px;height:26px;border:none;background:#f7f5ef;color:#333;font-size:15px;cursor:pointer;}
    .zc-cd-item .qty .q{min-width:26px;text-align:center;font-weight:700;font-size:13px;}
    .zc-cd-item .pr{font-size:12.5px;color:#666;} .zc-cd-item .pr b{color:#1a1a1a;}
    .zc-cd-item__rm{border:none;background:none;color:#c0392b;font-size:22px;line-height:1;cursor:pointer;flex:none;}
    .zc-cd-empty{padding:48px 20px;text-align:center;color:#999;}
    .zc-cd-empty svg{color:#d8d2c2;margin-bottom:10px;} .zc-cd-empty p{margin-bottom:16px;font-weight:600;}
    /* suggest carousel */
    .zc-cd-suggest{margin-top:8px;padding:14px 0 6px 14px;border-top:8px solid #f6f4ee;}
    .zc-cd-suggest__head{display:flex;align-items:center;justify-content:space-between;padding-right:14px;margin-bottom:10px;}
    .zc-cd-suggest__head h4{font-size:14px;font-weight:800;color:#1a1a1a;position:relative;padding-left:10px;}
    .zc-cd-suggest__head h4::before{content:'';position:absolute;left:0;top:2px;bottom:2px;width:3px;border-radius:2px;background:#e67e22;}
    .zc-cd-suggest__head .nav button{width:26px;height:26px;border-radius:50%;border:1px solid #e2ded2;background:#fff;cursor:pointer;color:#666;margin-left:4px;}
    .zc-cd-track{display:flex;gap:10px;overflow-x:auto;scroll-behavior:smooth;padding-bottom:8px;scrollbar-width:thin;}
    .zc-cd-track::-webkit-scrollbar{height:5px;} .zc-cd-track::-webkit-scrollbar-thumb{background:#e0dccf;border-radius:999px;}
    .zc-cd-scard{flex:0 0 150px;border:1px solid #f0efe9;border-radius:12px;padding:8px;background:#fff;}
    .zc-cd-scard .img{display:block;height:92px;border-radius:8px;overflow:hidden;background:#f6f4ee;display:grid;place-items:center;}
    .zc-cd-scard .img img{width:100%;height:100%;object-fit:cover;}
    .zc-cd-scard .nm{font-size:12px;font-weight:600;color:#333;margin-top:7px;line-height:1.3;min-height:31px;}
    .zc-cd-scard .pr{font-weight:800;color:#e67e22;font-size:13px;margin-top:3px;}
    .zc-cd-scard .add{margin-top:7px;width:100%;border:none;border-radius:7px;background:#1f7a3d;color:#fff;font-weight:700;font-size:12px;padding:7px;cursor:pointer;}
    .zc-cd-scard .add:hover{background:#186a34;}
    /* footer */
    .zc-cd-foot{position:sticky;bottom:0;background:#fff;border-top:1px solid #eee;padding:14px;margin-top:auto;}
    .zc-cd-foot .tot{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-weight:700;}
    .zc-cd-foot .tot b{font-size:19px;color:#1a1a1a;}
    .zc-cd-checkout{display:block;text-align:center;background:linear-gradient(90deg,#f2830c,#f59e0b);color:#fff;font-weight:800;letter-spacing:.04em;padding:15px;border-radius:11px;text-decoration:none;box-shadow:0 14px 26px -12px rgba(242,131,12,.7);}
    .zc-cd-checkout.is-off{background:#cfcabb;box-shadow:none;pointer-events:none;}

    /* ===== Storefront premium polish — form-level, token-derived, desktop + mobile ===== */
    .zc-sec__title, .zc-pagehero h1 { letter-spacing:-.012em; }
    /* Accessible keyboard focus rings (don't affect mouse users) */
    .zc-btn:focus-visible, .pcard__add:focus-visible, .zc-input:focus-visible, .zc-act:focus-visible, .zc-search input:focus-visible, .zc-dsearch input:focus-visible {
        outline:2px solid var(--leaf); outline-offset:2px; border-radius:10px;
    }
    /* Scroll reveal: ONLY off-screen sections get .zc-pre (added by JS), so if JS
       ever fails nothing is hidden — content stays fully visible. */
    .zc-sec.zc-pre { opacity:0; transform:translateY(22px); }
    .zc-sec.zc-pre.zc-in { opacity:1; transform:none; transition:opacity .6s cubic-bezier(.2,.7,.3,1), transform .55s cubic-bezier(.2,.7,.3,1); }
    /* Slight stagger for product cards inside a revealed grid */
    .zc-sec.zc-pre.zc-in .pcard { animation:zcCardRise .5s cubic-bezier(.2,.7,.3,1) both; }
    @keyframes zcCardRise { from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:none;} }
    /* Premium, unobtrusive scrollbar */
    html { scrollbar-width:thin; scrollbar-color:rgba(31,122,61,.3) transparent; }
    ::-webkit-scrollbar { width:11px; height:11px; }
    ::-webkit-scrollbar-thumb { background:rgba(31,122,61,.26); border-radius:999px; border:3px solid transparent; background-clip:content-box; }
    ::-webkit-scrollbar-thumb:hover { background:rgba(31,122,61,.44); background-clip:content-box; }
    @media (prefers-reduced-motion: reduce) {
        .zc-sec.zc-pre { opacity:1 !important; transform:none !important; }
        .zc-sec.zc-pre.zc-in .pcard { animation:none !important; }
    }
</style>

@stack('storefront-scripts')
<script>
    (function(){
        var drawer=document.querySelector('[data-drawer]');
        function open(){drawer&&drawer.classList.add('is-open');document.body.classList.add('zc-no-scroll');}
        function close(){drawer&&drawer.classList.remove('is-open');document.body.classList.remove('zc-no-scroll');}
        document.querySelectorAll('[data-drawer-open]').forEach(function(b){b.addEventListener('click',open);});
        document.querySelectorAll('[data-drawer-close]').forEach(function(b){b.addEventListener('click',close);});

        // ---- Mobile search overlay ----
        var ms=document.querySelector('[data-msearch]');
        if(ms){
            var msInput=ms.querySelector('[data-msearch-input]');
            function msOpen(){ ms.classList.add('is-open'); ms.setAttribute('aria-hidden','false'); setTimeout(function(){ msInput&&msInput.focus(); },120); }
            function msClose(){ ms.classList.remove('is-open'); ms.setAttribute('aria-hidden','true'); }
            document.querySelectorAll('[data-msearch-open]').forEach(function(b){ b.addEventListener('click',function(e){ e.preventDefault(); close(); msOpen(); }); });
            ms.querySelectorAll('[data-msearch-close]').forEach(function(b){ b.addEventListener('click',msClose); });
            ms.querySelector('form').addEventListener('submit', function(){ if(!(msInput&&msInput.value.trim())){ /* let it go to all products */ } });
            document.addEventListener('keydown', function(e){ if(e.key==='Escape') msClose(); });
        }
    })();

    // ---- Search autocomplete (AJAX suggestions) ----
    (function(){
        var url='{{ route('storefront.search.suggest') }}';
        var inputs=document.querySelectorAll('[data-suggest]'); if(!inputs.length) return;
        var money=function(v){ return '৳'+Number(v).toLocaleString('en-US',{maximumFractionDigits:0}); };
        var esc=function(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; };
        inputs.forEach(function(input){
            var box=document.createElement('div'); box.className='zc-suggest'; input.parentNode.appendChild(box);
            var t, lastQ='', active=-1, items=[];
            function close(){ box.classList.remove('is-open'); active=-1; }
            function render(res,q){
                items=res||[];
                var form=input.closest('form'); var allUrl=(form?form.getAttribute('action'):'/products')+'?q='+encodeURIComponent(q);
                if(!items.length){ box.innerHTML='<div class="zc-suggest__empty">No products match “'+esc(q)+'”.</div>'; box.classList.add('is-open'); return; }
                var html=items.map(function(p){
                    return '<a class="zc-suggest__item" href="'+esc(p.url)+'">'+
                        (p.image?'<img class="zc-suggest__img" src="'+esc(p.image)+'" alt="">':'<span class="zc-suggest__img"></span>')+
                        '<span class="zc-suggest__b"><span class="zc-suggest__nm">'+esc(p.name)+'</span><span class="zc-suggest__pr">'+money(p.price)+'</span></span></a>';
                }).join('');
                html+='<a class="zc-suggest__foot" href="'+esc(allUrl)+'">See all results for “'+esc(q)+'” →</a>';
                box.innerHTML=html; box.classList.add('is-open'); active=-1;
            }
            input.addEventListener('input', function(){
                var q=input.value.trim(); clearTimeout(t);
                if(q.length<2){ close(); return; }
                t=setTimeout(function(){
                    lastQ=q;
                    fetch(url+'?q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
                        .then(function(r){return r.json();}).then(function(d){ if(input.value.trim()===q) render(d.results,q); }).catch(function(){});
                },220);
            });
            input.addEventListener('keydown', function(e){
                if(!box.classList.contains('is-open')) return;
                var links=box.querySelectorAll('.zc-suggest__item, .zc-suggest__foot');
                if(e.key==='ArrowDown'){ e.preventDefault(); active=Math.min(active+1,links.length-1); }
                else if(e.key==='ArrowUp'){ e.preventDefault(); active=Math.max(active-1,-1); }
                else if(e.key==='Enter'){ if(active>=0&&links[active]){ e.preventDefault(); window.location.href=links[active].href; } return; }
                else if(e.key==='Escape'){ close(); return; }
                else return;
                links.forEach(function(l,i){ l.classList.toggle('is-active',i===active); });
                if(active>=0&&links[active]) links[active].scrollIntoView({block:'nearest'});
            });
            input.addEventListener('focus', function(){ if(input.value.trim().length>=2 && box.innerHTML) box.classList.add('is-open'); });
            document.addEventListener('click', function(e){ if(!box.contains(e.target)&&e.target!==input) close(); });
        });
    })();

    // ---- Slide-out cart drawer ----
    (function(){
        var cd=document.querySelector('[data-cart-drawer]'); if(!cd) return;
        var body=cd.querySelector('[data-cart-body]');
        var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content;
        var drawerUrl='{{ route('cart.drawer') }}', addUrl='{{ route('cart.add') }}';
        var autoTimer=null;

        function positionDropdown(trigger){
            var panel = cd.querySelector('.zc-cartdrawer__panel');
            if (window.matchMedia('(min-width:821px)').matches && trigger && trigger.getBoundingClientRect){
                var r = trigger.getBoundingClientRect();
                panel.style.top = (r.bottom + 10) + 'px';
                panel.style.right = Math.max(12, window.innerWidth - r.right) + 'px';
                panel.style.left = 'auto';
            } else { panel.style.top = ''; panel.style.right = ''; panel.style.left = ''; }
        }
        function openCart(e){ if(e) e.preventDefault(); positionDropdown(e ? e.currentTarget : cartTarget()); cd.classList.add('is-open'); document.body.classList.add('zc-no-scroll'); load(); }
        function closeCart(){ cd.classList.remove('is-open'); document.body.classList.remove('zc-no-scroll'); stopAuto(); }
        function setCount(n){ document.querySelectorAll('.zc-act__badge,.zc-botnav__badge').forEach(function(b){ if(n>0){ b.textContent=n; b.style.display=''; } else { b.style.display='none'; } }); }
        function inject(html){ body.innerHTML=html; initCarousel(); }
        function load(){ fetch(drawerUrl,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.text();}).then(inject); }
        function req(url,method,payload){
            return fetch(url,{method:method,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded'},body:payload||null})
                .then(function(r){return r.json();}).then(function(d){ if(d.html!==undefined){ inject(d.html); setCount(d.count); } });
        }
        document.querySelectorAll('[data-cart-open]').forEach(function(a){ a.addEventListener('click',openCart); });
        cd.querySelectorAll('[data-cart-close]').forEach(function(b){ b.addEventListener('click',closeCart); });

        body.addEventListener('click', function(e){
            var inc=e.target.closest('[data-cd-inc]'), dec=e.target.closest('[data-cd-dec]');
            if(inc||dec){ var it=e.target.closest('.zc-cd-item'); var q=parseInt(it.getAttribute('data-qty'),10)||1; q=inc?q+1:Math.max(1,q-1);
                req(it.getAttribute('data-update'),'PATCH','quantity='+q); return; }
            var rm=e.target.closest('[data-cd-remove]');
            if(rm){ req(rm.getAttribute('data-cd-remove'),'DELETE'); return; }
            var add=e.target.closest('[data-cd-add]');
            if(add){ add.textContent='Added ✓'; req(addUrl,'POST','product_id='+add.getAttribute('data-cd-add')+'&quantity=1'); return; }
            var prev=e.target.closest('[data-cd-prev]'), next=e.target.closest('[data-cd-next]');
            if(prev||next){ var tr=body.querySelector('[data-cd-track]'); if(tr) tr.scrollBy({left:(next?1:-1)*165,behavior:'smooth'}); }
        });

        function initCarousel(){ stopAuto(); var tr=body.querySelector('[data-cd-track]'); if(!tr||tr.children.length<2) return;
            autoTimer=setInterval(function(){ if(!cd.classList.contains('is-open')) return;
                if(tr.scrollLeft+tr.clientWidth>=tr.scrollWidth-4){ tr.scrollTo({left:0,behavior:'smooth'}); }
                else { tr.scrollBy({left:165,behavior:'smooth'}); } }, 2600); }
        function stopAuto(){ if(autoTimer){ clearInterval(autoTimer); autoTimer=null; } }

        // ---- Animated add-to-cart from product cards (fly-to-cart + AJAX) ----
        function cartTarget(){ var t=null; document.querySelectorAll('[data-cart-open]').forEach(function(a){ if(a.offsetParent!==null) t=a; }); return t; }
        function bumpCart(target){ if(!target) return; var b=target.querySelector('.zc-act__badge,.zc-botnav__badge'); if(b){ b.classList.remove('zc-cartbump'); void b.offsetWidth; b.classList.add('zc-cartbump'); } }
        function fly(srcImg, done){
            var target=cartTarget();
            if(!srcImg || !target || !srcImg.getBoundingClientRect){ bumpCart(target); done&&done(); return; }
            var s=srcImg.getBoundingClientRect(), t=target.getBoundingClientRect();
            if(!s.width){ bumpCart(target); done&&done(); return; }
            var clone=srcImg.cloneNode(true); clone.className='zc-fly'; clone.removeAttribute('loading');
            clone.style.left=s.left+'px'; clone.style.top=s.top+'px'; clone.style.width=s.width+'px'; clone.style.height=s.height+'px';
            document.body.appendChild(clone); clone.getBoundingClientRect();
            var tx=(t.left+t.width/2)-(s.left+s.width/2), ty=(t.top+t.height/2)-(s.top+s.height/2);
            clone.style.transform='translate('+tx+'px,'+ty+'px) scale(.12)'; clone.style.opacity='.25';
            var gone=false; function end(){ if(gone) return; gone=true; clone.remove(); bumpCart(target); done&&done(); }
            clone.addEventListener('transitionend', end); setTimeout(end, 900);
        }
        document.addEventListener('submit', function(e){
            var form=e.target.closest('form[data-cart-ajax]'); if(!form) return;
            // "Order Now" (checkout=1) navigates to checkout — leave it alone.
            var co=form.querySelector('[name="checkout"]'); if(co && co.value==='1') return;
            e.preventDefault();
            var card=form.closest('.pcard');
            var img=card ? card.querySelector('.pcard__media img') : (form.querySelector('[data-cart-hero]') || document.querySelector('[data-cart-hero]'));
            var btn=form.querySelector('button[data-checkout="0"]') || form.querySelector('button[type="submit"]');
            if(btn && !btn.dataset.orig) btn.dataset.orig=btn.innerHTML;
            fly(img);
            fetch(form.action,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(new FormData(form)).toString()})
                .then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
                .then(function(res){
                    if(!res.ok){ if(btn) btn.innerHTML=btn.dataset.orig; if(res.d && res.d.message) alert(res.d.message); return; }
                    var d=res.d; if(d && d.html!==undefined){ inject(d.html); setCount(d.count); }
                    if(btn){ btn.classList.add('is-added'); btn.innerHTML='Added ✓'; setTimeout(function(){ btn.classList.remove('is-added'); btn.innerHTML=btn.dataset.orig; }, 1500); }
                })
                .catch(function(){ if(btn) btn.innerHTML=btn.dataset.orig; });
        });
    })();

    // Premium scroll-reveal for storefront sections. Fail-safe by design:
    // only sections that are OFF-screen at load get hidden (.zc-pre) and then
    // revealed as they scroll into view — so any JS failure leaves everything
    // visible. Skipped entirely without IntersectionObserver or with reduced motion.
    (function () {
        if (!('IntersectionObserver' in window)) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var sections = [].slice.call(document.querySelectorAll('.zc-sec'));
        if (!sections.length) return;

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('zc-in'); io.unobserve(e.target); }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

        var vh = window.innerHeight || document.documentElement.clientHeight;
        sections.forEach(function (el) {
            // Already visible (above the fold) → leave it be, no hide/flash.
            if (el.getBoundingClientRect().top < vh * 0.9) return;
            el.classList.add('zc-pre');
            io.observe(el);
        });

        // Safety net: never leave a section stuck hidden.
        setTimeout(function () {
            document.querySelectorAll('.zc-sec.zc-pre:not(.zc-in)').forEach(function (el) { el.classList.add('zc-in'); });
        }, 2600);
    })();

    // Announcement bar: scroll only if the messages overflow (otherwise the
    // static centered bar would look "doubled" when duplicated for looping).
    (function () {
        var m = document.querySelector('[data-marquee]');
        if (!m) return;
        var track = m.querySelector('.zc-marquee__track');
        var group = track.querySelector('.zc-marquee__group');
        function setup() {
            m.classList.remove('is-scrolling');
            track.querySelectorAll('.zc-marquee__group.is-clone').forEach(function (c) { c.remove(); });
            var groupW = group.getBoundingClientRect().width;
            var contW = m.getBoundingClientRect().width;
            if (groupW > contW - 4) {
                var clone = group.cloneNode(true);
                clone.classList.add('is-clone');
                clone.setAttribute('aria-hidden', 'true');
                track.appendChild(clone);
                track.style.setProperty('--marq-dur', Math.max(14, Math.round(groupW / 45)) + 's');
                m.classList.add('is-scrolling');
            }
        }
        setup();
        var t;
        window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(setup, 200); });
    })();

    // Live countdown / flash-sale bar.
    (function () {
        var bar = document.querySelector('[data-countdown]');
        if (!bar) return;
        var target = parseInt(bar.getAttribute('data-countdown'), 10);
        var elD = bar.querySelector('[data-d]'), elH = bar.querySelector('[data-h]'), elM = bar.querySelector('[data-m]'), elS = bar.querySelector('[data-s]');
        function pad(n){ return (n < 10 ? '0' : '') + n; }
        function tick() {
            var diff = target - Date.now();
            if (diff <= 0) { bar.style.display = 'none'; clearInterval(t); return; }
            var s = Math.floor(diff / 1000);
            elD.textContent = pad(Math.floor(s / 86400));
            elH.textContent = pad(Math.floor((s % 86400) / 3600));
            elM.textContent = pad(Math.floor((s % 3600) / 60));
            elS.textContent = pad(s % 60);
        }
        tick();
        var t = setInterval(tick, 1000);
    })();

    // Welcome popup — shown once per browsing session. Deferred to
    // DOMContentLoaded because the popup markup is rendered after this script.
    document.addEventListener('DOMContentLoaded', function () {
        var pop = document.querySelector('[data-popup]');
        if (!pop) return;
        var KEY = 'zc_popup_seen';
        try { if (sessionStorage.getItem(KEY)) return; } catch (e) {}

        function close() {
            pop.classList.remove('is-open');
            try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
            setTimeout(function () { pop.setAttribute('hidden', ''); }, 250);
        }
        pop.querySelectorAll('[data-popup-close]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); close(); }); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pop.classList.contains('is-open')) close(); });
        var link = pop.querySelector('.zc-pop__link');
        if (link) link.addEventListener('click', function () { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} });

        setTimeout(function () { pop.removeAttribute('hidden'); pop.classList.add('is-open'); }, 900);
    });
</script>

{{-- Welcome popup markup (Studio → Website Setup → Promotions) --}}
@php
    $popupEnabled = filter_var($themeValue('popup_enabled', false), FILTER_VALIDATE_BOOLEAN);
    $popupImg = $themeMediaUrl('popup_image');
    $popupTitle = trim((string) $themeValue('popup_title', ''));
    $popupText = trim((string) $themeValue('popup_text', ''));
    $popupCta = trim((string) $themeValue('popup_cta', 'Shop now'));
    $popupLink = trim((string) $themeValue('popup_link', '/products')) ?: '/products';
    $popupShow = $popupEnabled && (filled($popupImg) || $popupTitle !== '' || $popupText !== '');
@endphp
@if ($popupShow)
<div class="zc-pop zc-no-print" data-popup hidden>
    <div class="zc-pop__backdrop" data-popup-close></div>
    <div class="zc-pop__box" role="dialog" aria-modal="true" aria-label="{{ $popupTitle ?: 'Special offer' }}">
        <button class="zc-pop__x" type="button" data-popup-close aria-label="Close">✕</button>
        <a href="{{ $popupLink }}" class="zc-pop__link">
            @if ($popupImg)<img src="{{ $popupImg }}" alt="" class="zc-pop__img">@endif
            <div class="zc-pop__body">
                @if ($popupTitle)<div class="zc-pop__title">{{ $popupTitle }}</div>@endif
                @if ($popupText)<p class="zc-pop__text">{{ $popupText }}</p>@endif
                @if ($popupCta)<span class="zc-pop__cta">{{ $popupCta }}</span>@endif
            </div>
        </a>
    </div>
</div>
@endif

@if ($acOn)
    @if ($acSelection)
    <style>
        body, body *{ -webkit-user-select:none; -moz-user-select:none; -ms-user-select:none; user-select:none; -webkit-touch-callout:none; }
        input, textarea, select, [contenteditable]{ -webkit-user-select:text !important; user-select:text !important; }
        img{ -webkit-user-drag:none; user-drag:none; }
    </style>
    @endif
    <script>
    (function () {
        var editable = function (t) { return t && t.closest && t.closest('input,textarea,select,[contenteditable]'); };
        @if ($acRightClick)
        document.addEventListener('contextmenu', function (e) { if (!editable(e.target)) e.preventDefault(); });
        @endif
        @if ($acSelection)
        document.addEventListener('dragstart', function (e) { if (!editable(e.target)) e.preventDefault(); });
        document.addEventListener('copy', function (e) { if (!editable(e.target)) e.preventDefault(); });
        @endif
        @if ($acCopy || $acDevtools)
        document.addEventListener('keydown', function (e) {
            var k = (e.key || '').toLowerCase();
            @if ($acDevtools)
            if (e.key === 'F12') { e.preventDefault(); return false; }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (k === 'i' || k === 'j' || k === 'c')) { e.preventDefault(); return false; }
            if ((e.ctrlKey || e.metaKey) && k === 'u') { e.preventDefault(); return false; }
            @endif
            @if ($acCopy)
            if ((e.ctrlKey || e.metaKey) && (k === 'c' || k === 'x' || k === 's' || k === 'a' || k === 'p') && !editable(e.target)) { e.preventDefault(); return false; }
            @endif
        });
        @endif
    })();
    </script>
@endif
</body>
</html>
