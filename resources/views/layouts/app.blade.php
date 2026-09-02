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
        'imo' => $themeValue('social_imo'),
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

    // IMO click-to-chat (number set in Studio → Website Setup → Footer →
    // Social links). Unlike WhatsApp, IMO has no official web click-to-chat
    // link — this is a best-effort app deep link that only opens anything
    // on a device with the IMO app installed; it's a silent no-op otherwise.
    $imoDigits = preg_replace('/\D/', '', (string) ($socialLinks['imo'] ?? ''));
    if ($imoDigits !== '' && str_starts_with($imoDigits, '0')) {
        $imoDigits = '88'.$imoDigits;
    }
    $imoLink = strlen($imoDigits) >= 10 ? 'imo://chat?phone='.$imoDigits : null;

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
    {{-- Static storefront CSS moved to a cacheable external file (was ~180
         lines of <style> re-sent inline on every single page load) — see
         public/assets/storefront.css. Only theme-color/settings-driven
         rules stay inline above, since those need server-rendered values. --}}
    <link rel="stylesheet" href="{{ asset('assets/storefront.css') }}?v={{ @filemtime(public_path('assets/storefront.css')) ?: '1' }}">
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
                // Real per-network icons — no more generic dots. WhatsApp and
                // IMO are skipped here because they have their own
                // floating/bottom-nav buttons.
                $socialIcons = [
                    'facebook' => '<path d="M14 9h3l.5-3H14V4.5c0-.9.3-1.5 1.6-1.5H17V.3C16.6.2 15.6 0 14.5 0 12.1 0 10.5 1.5 10.5 4.2V6H8v3h2.5v9H14z"/>',
                    'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.2" cy="6.8" r="1.3"/>',
                    'youtube' => '<path d="M23 12s0-3.5-.45-5.17a2.7 2.7 0 0 0-1.9-1.9C18.98 4.5 12 4.5 12 4.5s-6.98 0-8.65.43a2.7 2.7 0 0 0-1.9 1.9C1 8.5 1 12 1 12s0 3.5.45 5.17a2.7 2.7 0 0 0 1.9 1.9c1.67.43 8.65.43 8.65.43s6.98 0 8.65-.43a2.7 2.7 0 0 0 1.9-1.9C23 15.5 23 12 23 12ZM9.75 15.02V8.98L15.5 12Z"/>',
                ];
                $footerSocials = collect($socialLinks)->except(['whatsapp', 'imo'])->filter();
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

{{-- Floating WhatsApp / IMO contact buttons (bottom-left). Hidden on mobile,
     where the bottom nav carries them instead. --}}
@if ($whatsappLink)
<a href="{{ $whatsappLink }}" target="_blank" rel="noopener" class="zc-wafab zc-no-print" aria-label="Chat with us on WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.8 1-.3.2-.5 0a6.5 6.5 0 0 1-1.9-1.2 7.3 7.3 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.4v-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3A2.8 2.8 0 0 0 5.9 10a5 5 0 0 0 1 2.6 11 11 0 0 0 4.2 3.7c2 .8 2 .5 2.4.5a2.5 2.5 0 0 0 1.6-1.1 2 2 0 0 0 .1-1.1c0-.1-.2-.2-.4-.3Z"/></svg>
    <span class="zc-wafab__label">WhatsApp</span>
</a>
@endif
@if ($imoLink)
<a href="{{ $imoLink }}" @class(['zc-imofab', 'zc-no-print', 'zc-imofab--stacked' => $whatsappLink]) aria-label="Chat with us on IMO" data-imo-fab-cc data-imo-number="{{ $socialLinks['imo'] }}">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round" d="M12 2.5C6.7 2.5 2.5 6.2 2.5 10.7c0 2.4 1.2 4.6 3.2 6.1l-.9 3.7 4.3-2c.9.2 1.9.3 2.9.3 5.3 0 9.5-3.7 9.5-8.1S17.3 2.5 12 2.5Z"/><text x="12" y="13.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="800" font-size="7.2" fill="currentColor" stroke="none" letter-spacing="-0.3">imo</text></svg>
    <span class="zc-wafab__label">IMO</span>
</a>
<div id="zc-imo-fab-toast" class="zc-imo-fab-toast zc-no-print">IMO number copied — {{ $socialLinks['imo'] }}. No IMO app found? Save it and message us there.</div>
@endif

{{-- Mobile bottom nav --}}
@php
    // Home, Cart, Account are always present; Track/WhatsApp/IMO/Search are
    // conditional — the grid needs to match however many actually render.
    $botnavCount = 3 + ($showTracking ? 1 : 0) + ($whatsappLink ? 1 : 0) + ($imoLink ? 1 : 0) + (! $whatsappLink && ! $imoLink ? 1 : 0);
@endphp
<nav class="zc-botnav zc-no-print" aria-label="Mobile">
    <div class="zc-botnav__row" style="grid-template-columns:repeat({{ $botnavCount }}, minmax(0,1fr));">
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
        @endif
        @if ($imoLink)
        <a href="{{ $imoLink }}" class="zc-botnav__imo" aria-label="Chat on IMO">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round" d="M12 2.5C6.7 2.5 2.5 6.2 2.5 10.7c0 2.4 1.2 4.6 3.2 6.1l-.9 3.7 4.3-2c.9.2 1.9.3 2.9.3 5.3 0 9.5-3.7 9.5-8.1S17.3 2.5 12 2.5Z"/><text x="12" y="13.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="800" font-size="7.2" fill="currentColor" stroke="none" letter-spacing="-0.3">imo</text></svg><span>IMO</span>
        </a>
        @endif
        @if (! $whatsappLink && ! $imoLink)
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

@stack('storefront-scripts')
{{-- Static storefront JS moved to a cacheable external file (was ~250 lines
     of <script> re-sent inline on every single page load) — see
     public/assets/storefront.js. It needs 3 route URLs, injected here since
     that's the only part that actually depends on server-rendered values. --}}
<script>
    window.ZC_ROUTES = {
        searchSuggest: @json(route('storefront.search.suggest')),
        cartDrawer: @json(route('cart.drawer')),
        cartAdd: @json(route('cart.add'))
    };
</script>
<script src="{{ asset('assets/storefront.js') }}?v={{ @filemtime(public_path('assets/storefront.js')) ?: '1' }}"></script>

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
