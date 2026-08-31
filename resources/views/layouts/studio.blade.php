<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{!! trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')) : 'Zenna Studio' !!}</title>
    @php $studioFaviconUrl = app(\App\Modules\Theme\Services\ThemeService::class)->mediaUrl('site_favicon'); @endphp
    <link rel="icon" href="{{ $studioFaviconUrl ?: asset('favicon.ico') }}">
    @include('studio.partials.design-system')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @stack('studio-styles')
    <style>
        .studio-sidebar {
            transition: width 0.2s ease;
        }

        .studio-reference-workspace {
            transition: padding-left 0.2s ease;
        }

        /* Full-width topbar so the profile sits at the true far right. */
        .studio-topbar__inner { max-width: none !important; }

        /* Hide the topbar on scroll-down, reveal on scroll-up. */
        .studio-topbar { transition: transform 0.28s ease; will-change: transform; }
        .studio-topbar.is-hidden { transform: translateY(-100%); }

        /* Profile dropdown menu items. */
        .studio-profile-dropdown { min-width: 15rem; }
        .studio-profile-menu-list { margin-top: 0.6rem; display: grid; gap: 0.15rem; border-top: 1px solid rgba(148,163,184,0.16); padding-top: 0.5rem; }
        .studio-profile-menu-item {
            display: flex; align-items: center; gap: 0.6rem; width: 100%;
            padding: 0.55rem 0.6rem; border-radius: 10px; border: none; background: transparent;
            color: var(--studio-reference-text, #e9ebf0); font-size: 0.85rem; font-weight: 700;
            text-align: left; text-decoration: none; cursor: pointer;
        }
        .studio-profile-menu-item svg { width: 1.05rem; height: 1.05rem; color: rgba(199,154,59,0.9); flex: none; }
        .studio-profile-menu-item:hover { background: rgba(199,154,59,0.12); }
        .studio-profile-menu-item--danger { color: #e2a28c; }
        .studio-profile-menu-item--danger svg { color: #e2a28c; }
        .studio-profile-menu-item--danger:hover { background: rgba(224,90,74,0.14); }
        .studio-profile-avatar { overflow: hidden; }
        .studio-profile-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; display: block; }

        /* In-place action feedback toast (no-reload actions). */
        .studio-ajax-toast {
            position: fixed;
            bottom: 1.4rem;
            right: 1.4rem;
            z-index: 200;
            max-width: 22rem;
            padding: 0.8rem 1.1rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #171b26, #10131b);
            border: 1px solid rgba(95, 165, 120, 0.4);
            color: #e9ebf0;
            font-size: 0.85rem;
            font-weight: 700;
            box-shadow: 0 24px 60px -28px rgba(0, 0, 0, 0.85);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .studio-ajax-toast.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .studio-ajax-toast.is-error {
            border-color: rgba(208, 135, 112, 0.55);
        }

        /* Brief highlight on a row/region the moment AJAX swaps it in, so a
           live update (verify, status change, courier…) is visibly confirmed
           without a page reload. */
        @keyframes zcRegionFlash {
            0%   { background-color: rgba(95, 165, 120, 0.32); }
            100% { background-color: transparent; }
        }
        .zc-region-flash,
        .zc-region-flash > td {
            animation: zcRegionFlash 1.2s ease-out;
        }

        /* ---- Compact, premium sidebar navigation ---- */
        /* Tighten the whole nav column — the reference sidebar is dense,
           not airy. Overrides the roomier design-system defaults. */
        .studio-sidebar__nav {
            padding: 0.75rem 0.6rem !important;
        }

        .studio-sidebar__nav nav {
            display: grid;
            gap: 0.12rem;
        }

        .studio-sidebar__nav .studio-nav-link {
            min-height: 0 !important;
            margin: 0 !important;
            gap: 0.6rem !important;
            padding: 0.5rem 0.65rem !important;
            border-radius: 0.7rem !important;
            font-size: 0.8rem !important;
            font-weight: 760 !important;
            line-height: 1.15 !important;
        }

        .studio-sidebar__nav .studio-nav-link__icon {
            width: 1.6rem !important;
            height: 1.6rem !important;
            border-radius: 8px !important;
        }

        .studio-sidebar__nav .studio-nav-link__icon svg {
            width: 1rem;
            height: 1rem;
        }

        /* Expandable group summary is itself a nav-link — strip the legacy
           section-heading styling it would otherwise inherit. */
        .studio-nav-group {
            padding: 0 !important;
        }

        .studio-nav-group > summary {
            list-style: none;
            cursor: pointer;
            letter-spacing: normal !important;
            text-transform: none !important;
            color: rgba(226, 232, 240, 0.82) !important;
        }

        .studio-nav-group > summary::-webkit-details-marker {
            display: none;
        }

        .studio-nav-group[open] > summary {
            color: #fffaf0 !important;
        }

        /* Active group header stays a subtle gold tint (not the heavy
           white block a standalone active link gets) so the active child
           below it is what draws the eye. */
        .studio-sidebar__nav .studio-nav-group > summary.is-active {
            background: rgba(199, 154, 59, 0.1) !important;
            color: #fffaf0 !important;
            border-color: rgba(199, 154, 59, 0.26) !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .studio-nav-group > summary.is-active .studio-nav-link__icon {
            background: rgba(199, 154, 59, 0.2) !important;
            color: rgba(243, 227, 184, 0.95) !important;
        }

        .studio-nav-group__chevron {
            margin-left: auto;
            display: inline-flex;
            color: rgba(199, 154, 59, 0.75);
            transition: transform 0.2s ease;
        }

        .studio-nav-group__chevron svg {
            width: 0.85rem;
            height: 0.85rem;
        }

        .studio-nav-group[open] > summary .studio-nav-group__chevron {
            transform: rotate(90deg);
        }

        /* Children rail: a thin dashed connector with a dot per row,
           tucked under the group icon. */
        .studio-nav-group__children {
            margin: 0.1rem 0 0.25rem 1.5rem;
            padding-left: 0.55rem;
            border-left: 1px dashed rgba(199, 154, 59, 0.32);
            display: grid;
            gap: 0.02rem;
        }

        .studio-sidebar__nav .studio-nav-link--child {
            padding: 0.4rem 0.55rem !important;
            font-size: 0.765rem !important;
            font-weight: 700 !important;
            color: rgba(226, 232, 240, 0.66) !important;
        }

        .studio-nav-link--soon {
            opacity: 0.42;
            cursor: not-allowed;
        }
        .studio-nav-link--soon:hover {
            background: transparent !important;
            transform: none !important;
        }

        .studio-nav-link__dot {
            position: relative;
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: rgba(199, 154, 59, 0.5);
            flex: none;
            margin-right: 0.35rem;
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }

        /* Pull each dot onto the dashed rail so they read as one connected line. */
        .studio-nav-link--child .studio-nav-link__dot::before {
            content: '';
            position: absolute;
            left: -0.9rem;
            top: 50%;
            width: 0.55rem;
            height: 1px;
            transform: translateY(-50%);
            background: rgba(199, 154, 59, 0.32);
        }

        .studio-sidebar__nav .studio-nav-link--child:hover {
            color: #fffaf0 !important;
            transform: none !important;
            background: rgba(199, 154, 59, 0.09) !important;
        }

        .studio-sidebar__nav .studio-nav-link--child.is-active {
            background: rgba(199, 154, 59, 0.14) !important;
            color: #fffaf0 !important;
            box-shadow: none !important;
        }

        .studio-nav-link--child.is-active .studio-nav-link__dot {
            background: var(--zc-ds-color-gold-600, #c79a3b);
            box-shadow: 0 0 0 3px rgba(199, 154, 59, 0.18);
        }

        /* Collapsed sidebar: hide the chevron + children (unreachable
           without labels) rather than showing a stray marker. */
        body[data-sidebar-collapsed="true"] .studio-nav-group__chevron,
        body[data-sidebar-collapsed="true"] .studio-nav-group__children {
            display: none;
        }

        .studio-sidebar-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            flex: none;
            margin-left: auto;
            border: none;
            border-radius: 10px;
            background: rgba(255, 253, 247, 0.08);
            color: rgba(243, 227, 184, 0.92);
            cursor: pointer;
        }

        .studio-sidebar-toggle:hover,
        .studio-sidebar-toggle:focus-visible {
            background: rgba(199, 154, 59, 0.22);
        }

        .studio-sidebar-toggle svg {
            width: 1rem;
            height: 1rem;
            transition: transform 0.2s ease;
        }

        body[data-sidebar-collapsed="true"] .studio-sidebar-toggle svg {
            transform: rotate(180deg);
        }

        body[data-sidebar-collapsed="true"] .studio-sidebar__brand-text,
        body[data-sidebar-collapsed="true"] .studio-nav-link__label,
        body[data-sidebar-collapsed="true"] .studio-sidebar__logout-label {
            display: none;
        }

        body[data-sidebar-collapsed="true"] .studio-sidebar__brand {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        body[data-sidebar-collapsed="true"] .studio-sidebar__brand > div {
            flex-direction: column;
            gap: 0.65rem;
        }

        body[data-sidebar-collapsed="true"] .studio-sidebar-toggle {
            margin-left: 0;
        }

        body[data-sidebar-collapsed="true"] .studio-nav-link {
            justify-content: center;
        }

        @media (min-width: 1024px) {
            body[data-sidebar-collapsed="true"] .studio-sidebar {
                width: 76px !important;
            }

            body[data-sidebar-collapsed="true"] .studio-reference-workspace {
                padding-left: 76px !important;
            }
        }
    </style>

    {{-- ============================================================
         Studio Premium Polish — a final, system-wide refinement layer.
         It enhances FORM only (motion, elevation, focus, rhythm,
         scrollbars, typography) and derives every colour from the
         existing --studio-* tokens, so it lifts the whole panel without
         disturbing the established light-content / dark-sidebar theme.
         ============================================================ --}}
    <style>
        /* Crisper text rendering + tabular figures for all the numbers. */
        .studio-reference-layout { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }
        .studio-reference-layout h1, .studio-section-title { letter-spacing: -0.015em; }
        .studio-reference-layout .zc-op-money, .studio-reference-layout .zc-sm-tbl td, .studio-reference-layout [class*="__v"], .studio-reference-layout [class*="__value"] { font-variant-numeric: tabular-nums; }

        /* One smooth motion curve across every interactive surface. */
        .studio-command-button, a.studio-command-button, .studio-card, .studio-form-control,
        .studio-badge, .studio-nav-link, .zc-sm-tbl tbody tr, .zc-op-tbl tbody tr,
        .zc-sp-kpi, .zc-od-kpi, .zc-od-stat, .studio-profile-menu-item {
            transition: transform .16s cubic-bezier(.2,.7,.3,1), box-shadow .2s ease, border-color .18s ease, background-color .18s ease, color .16s ease, filter .18s ease;
        }

        /* Cards: a smoother radius and a soft layered elevation. */
        .studio-card { border-radius: 18px; box-shadow: 0 1px 2px rgba(15,23,42,.05), 0 22px 48px -34px rgba(15,23,42,.30); }

        /* Buttons: refined radius, a subtle lift on hover, a real press. */
        .studio-command-button, a.studio-command-button { border-radius: 12px; }
        .studio-command-button:hover, a.studio-command-button:hover { transform: translateY(-1px); }
        .studio-command-button:active, a.studio-command-button:active { transform: translateY(0); }
        .studio-command-button--primary { box-shadow: 0 12px 26px -14px rgba(0,0,0,.4); }
        .studio-command-button--primary:hover { box-shadow: 0 16px 32px -14px rgba(0,0,0,.46); filter: brightness(1.03); }

        /* Inputs: an accent focus ring that adapts to the active theme. */
        .studio-form-control:focus, .studio-form-control:focus-visible {
            outline: none;
            border-color: var(--studio-accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--studio-accent) 22%, transparent);
        }

        /* Keyboard focus visibility (accessibility) without touching mouse focus. */
        .studio-command-button:focus-visible, a.studio-command-button:focus-visible,
        .studio-nav-link:focus-visible, .studio-profile-menu-item:focus-visible {
            outline: 2px solid var(--studio-accent); outline-offset: 2px;
        }

        /* Data tables: a gentle accent-tinted row hover. */
        .zc-sm-tbl tbody tr:hover, .zc-op-tbl tbody tr:hover, .zc-od-item:hover {
            background: color-mix(in srgb, var(--studio-accent) 6%, transparent);
        }

        /* Stat / KPI tiles: a barely-there hover lift makes dashboards feel alive. */
        .zc-sp-kpi:hover, .zc-od-kpi:hover, .zc-od-stat:hover { transform: translateY(-2px); }

        /* Premium, unobtrusive scrollbars. */
        .studio-sidebar, .studio-responsive-scroll, .studio-reference-workspace, .studio-reference-main { scrollbar-width: thin; scrollbar-color: rgba(148,163,184,.42) transparent; }
        .studio-reference-layout ::-webkit-scrollbar { width: 10px; height: 10px; }
        .studio-reference-layout ::-webkit-scrollbar-thumb { background: rgba(148,163,184,.35); border-radius: 999px; border: 2px solid transparent; background-clip: content-box; }
        .studio-reference-layout ::-webkit-scrollbar-thumb:hover { background: rgba(148,163,184,.58); background-clip: content-box; }
        .studio-reference-layout ::-webkit-scrollbar-track { background: transparent; }

        /* A calm fade-in as each page settles in. */
        @keyframes zcStudioFade { from { opacity: 0; transform: translateY(7px); } to { opacity: 1; transform: none; } }
        #main-content { animation: zcStudioFade .34s cubic-bezier(.2,.7,.3,1) both; }

        /* ===== Premium finishing layer — applies to every Studio page ===== */
        /* Buttons lift gently and primary actions get a soft shine sweep. */
        .studio-reference-layout .studio-command-button { transition: transform .15s cubic-bezier(.2,.7,.3,1), box-shadow .2s ease, filter .16s ease, background-color .18s ease, border-color .18s ease; }
        .studio-reference-layout .studio-command-button:hover { transform: translateY(-1px); }
        .studio-reference-layout .studio-command-button:active { transform: translateY(0); }
        .studio-reference-layout .studio-command-button--primary { position: relative; overflow: hidden; }
        .studio-reference-layout .studio-command-button--primary::after {
            content: ""; position: absolute; top: 0; bottom: 0; left: -80%; width: 42%;
            transform: skewX(-20deg); pointer-events: none;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.5), transparent);
        }
        .studio-reference-layout .studio-command-button--primary:hover::after { animation: zcBtnShine .85s ease; }
        @keyframes zcBtnShine { from { left: -80%; } to { left: 140%; } }

        /* Inputs animate their focus glow in smoothly. */
        .studio-reference-layout .studio-form-control { transition: border-color .18s ease, box-shadow .2s ease, background-color .18s ease; }

        /* A gold accent sweeps in under the page title on every page. */
        .studio-reference-layout .studio-page-title { position: relative; }
        .studio-reference-layout .studio-page-title::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: -7px; height: 2px; border-radius: 2px;
            background: linear-gradient(90deg, rgba(199,154,59,.95), rgba(199,154,59,0));
            transform-origin: left; animation: zcTitleLine .6s cubic-bezier(.2,.8,.2,1) both;
        }
        @keyframes zcTitleLine { from { transform: scaleX(0); opacity: 0; } to { transform: scaleX(1); opacity: 1; } }

        /* Rows highlight softly on hover for easier scanning. */
        .studio-reference-main table tbody tr { transition: background-color .14s ease; }
        .studio-reference-main table tbody tr:hover { background: rgba(199,154,59,.055); }

        /* Mobile nav dropdown (hamburger): force the dark sidebar look so its
           links are always readable — the light content theme was leaving it
           white with near-invisible light-on-white text on small screens. */
        .studio-reference-layout .studio-reference-mobile-nav {
            background: linear-gradient(180deg, #141b25, #0b0f16) !important;
            border: 1px solid rgba(212,180,131,0.16) !important;
            box-shadow: 0 30px 70px -24px rgba(0,0,0,0.7) !important;
        }
        .studio-reference-mobile-nav .studio-nav-link { color: #d7dbe4 !important; }
        .studio-reference-mobile-nav .studio-nav-link__label { color: inherit !important; }
        .studio-reference-mobile-nav .studio-nav-link__icon { color: #c79a3b !important; }
        .studio-reference-mobile-nav .studio-nav-link__dot { background: rgba(215,219,228,0.5) !important; }
        .studio-reference-mobile-nav .studio-nav-group__chevron { color: #8b93a1 !important; }
        .studio-reference-mobile-nav .studio-nav-link:hover { background: rgba(212,180,131,0.12) !important; color: #ffffff !important; }
        .studio-reference-mobile-nav .studio-nav-link.is-active,
        .studio-reference-mobile-nav .studio-nav-link[aria-current="page"] { background: rgba(212,180,131,0.18) !important; color: #f5e9cf !important; }
        .studio-reference-mobile-nav .studio-nav-link--soon { color: #6b7280 !important; }

        @media (prefers-reduced-motion: reduce) {
            .studio-reference-layout .studio-command-button:hover,
            .studio-reference-layout .studio-command-button:active { transform: none; }
            .studio-reference-layout .studio-command-button--primary::after { display: none; }
            .studio-reference-layout .studio-page-title::after { animation: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .studio-command-button, .studio-card, #main-content, .zc-sp-kpi:hover, .zc-od-kpi:hover, .zc-od-stat:hover { animation: none !important; transition: none !important; transform: none !important; }
        }
    </style>
</head>
<body class="studio-shell studio-reference-layout" data-studio-staff-id="{{ Auth::guard('staff')->id() ?: 'guest' }}" data-studio-base-path="{{ url(config('admin.path')) }}">
    <script>
        // Read the saved collapse state before first paint so the sidebar
        // never flashes wide-then-narrow (or the reverse) on load.
        (function () {
            try {
                if (window.localStorage.getItem('zenna.studio.sidebarCollapsed') === 'true') {
                    document.body.dataset.sidebarCollapsed = 'true';
                }
            } catch (error) {
                // Local storage unavailable (private mode etc.) — default to expanded.
            }
        })();
    </script>
    <a href="#main-content" class="sr-only focus:not-sr-only" style="position:absolute;top:0.5rem;left:0.5rem;z-index:100;background:#fff;color:#0f172a;padding:0.5rem 1rem;border-radius:0.5rem;font-weight:700;">Skip to content</a>
    @php
        $staffUser = Auth::guard('staff')->user();
        $yieldedTitle = trim($__env->yieldContent('title'));
        $yieldedSubtitle = trim($__env->yieldContent('subtitle'));
        $studioPageTitle = $yieldedTitle !== '' ? $yieldedTitle : 'Studio';
        $studioPageSubtitle = $yieldedSubtitle !== '' ? $yieldedSubtitle : 'Zenna Craft admin workspace';

        // Display-only — never lets a license-check problem break page
        // rendering. Enforcement itself happens in EnsureLicenseIsValid /
        // AdminAccess, not here.
        $licenseBanner = null;
        if ($staffUser) {
            try {
                $licenseSvc = app(\App\Modules\License\Services\LicenseService::class);
                $licenseStatus = $licenseSvc->getEffectiveStatus();
                $licenseDaysLeft = $licenseSvc->daysUntilExpiry();
                $licenseBanner = $licenseStatus['status'] === 'grace' ? $licenseStatus : null;
                $licenseShowExpiryModal = $licenseStatus['status'] === 'active' && $licenseDaysLeft !== null && $licenseDaysLeft <= 3;
            } catch (\Throwable $e) {
                $licenseShowExpiryModal = false;
            }
        }
    @endphp

    <div class="studio-shell studio-reference-shell">
        @if ($staffUser)
            <aside class="studio-sidebar studio-reference-sidebar fixed inset-y-0 left-0 z-40 hidden w-[268px] overflow-y-auto border-r border-white/8 lg:flex lg:flex-col">
                <div class="studio-sidebar__brand">
                    <div class="flex items-center gap-3">
                        <div class="studio-brand-mark">ZC</div>
                        <div class="studio-sidebar__brand-text">
                            <div class="studio-sidebar__eyebrow">Commerce OS</div>
                            <div class="text-lg font-semibold text-white">Zenna Studio</div>
                            <div class="text-xs text-slate-400">Handmade operations</div>
                        </div>
                        <button
                            type="button"
                            class="studio-sidebar-toggle"
                            data-sidebar-toggle
                            aria-expanded="true"
                            aria-label="Collapse sidebar"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 5l-7 7 7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="studio-sidebar__nav flex-1 overflow-y-auto px-3 py-4">
                    @include('studio.partials.sidebar-navigation')
                </div>

                <div class="studio-sidebar__footer border-t border-white/10 p-4">
                    <form method="POST" action="{{ url(config('admin.path').'/logout') }}">
                        @csrf
                        <button type="submit" class="studio-sidebar__logout flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-200">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10 17l5-5-5-5"/>
                                <path d="M15 12H4"/>
                                <path d="M20 4v16"/>
                            </svg>
                            <span class="studio-sidebar__logout-label">Logout</span>
                        </button>
                    </form>
                </div>
            </aside>
        @endif

        <div class="studio-reference-workspace {{ $staffUser ? 'lg:pl-[268px]' : '' }} min-h-screen">
            <header class="studio-topbar">
                <div class="studio-topbar__inner studio-reference-topbar__inner mx-auto flex max-w-none items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    @if ($staffUser)
                        <details class="studio-menu-toggle relative lg:hidden">
                            <summary class="studio-reference-menu-toggle inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm">
                                <span class="sr-only">Open navigation menu</span>
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 7h16"/>
                                    <path d="M4 12h16"/>
                                    <path d="M4 17h16"/>
                                </svg>
                            </summary>

                            <div class="studio-dropdown studio-reference-mobile-nav left-0 right-auto mt-3 w-[min(22rem,calc(100vw-2rem))] p-3">
                                <div class="mb-3 rounded-2xl bg-slate-950 px-4 py-4 text-white">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Studio</div>
                                    <div class="mt-1 text-lg font-semibold">{!! $studioPageTitle !!}</div>
                                </div>
                                @include('studio.partials.sidebar-navigation')
                            </div>
                        </details>
                    @endif

                    <div class="studio-topbar__titleblock min-w-0 flex-1">
                        <div class="studio-breadcrumbs">
                            <span>Studio</span>
                        </div>

                        <div class="mt-1 flex items-center gap-3">
                            <h1 class="studio-page-title">{!! $studioPageTitle !!}</h1>
                            @if ($staffUser)
                                <span class="studio-live-status-pill">
                                    <span aria-hidden="true"></span>
                                    Live
                                </span>
                            @endif
                        </div>
                        <p class="studio-page-subtitle">{!! $studioPageSubtitle !!}</p>
                    </div>

                    @if ($staffUser)
                        <details class="studio-profile-menu relative" style="margin-left:auto;">
                            <summary class="studio-profile-summary">
                                <span class="studio-profile-avatar">@if ($staffUser->avatar)<img src="{{ $staffUser->avatar }}" alt="">@else{{ strtoupper(substr($staffUser->name ?? 'S', 0, 2)) }}@endif</span>
                                <span class="hidden text-left sm:block">
                                    <span class="block text-sm font-semibold text-slate-950">{{ $staffUser->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $staffUser->email }}</span>
                                </span>
                                <svg viewBox="0 0 20 20" class="h-4 w-4 text-slate-400 studio-profile-chevron" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 7l5 5 5-5"/>
                                </svg>
                            </summary>

                            <div class="studio-dropdown studio-dropdown--right studio-profile-dropdown">
                                <div class="studio-dropdown__label">Signed in as</div>
                                <div class="mt-1 studio-dropdown__name">{{ $staffUser->name }}</div>
                                <div class="studio-dropdown__meta">{{ $staffUser->email }}</div>

                                <div class="studio-profile-menu-list">
                                    <a href="{{ route('account.show') }}" class="studio-profile-menu-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                                        Profile
                                    </a>
                                    <a href="{{ route('account.show') }}#password" class="studio-profile-menu-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1"/></svg>
                                        Change Password
                                    </a>
                                    <form method="POST" action="{{ url(config('admin.path').'/logout') }}">
                                        @csrf
                                        <button type="submit" class="studio-profile-menu-item studio-profile-menu-item--danger">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12H4"/><path d="M8 8l-4 4 4 4"/><path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/></svg>
                                            Sign out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    @endif
                </div>
            </header>

            @if ($licenseBanner)
                <div style="background:#fff5e0;color:#8a6d1f;border-bottom:1px solid #f3d9a0;padding:.65rem 1.25rem;font-size:.85rem;font-weight:600;text-align:center;">
                    Your license is in its grace period — renew soon to avoid losing access.
                    <a href="{{ route('license.verification') }}" style="text-decoration:underline;font-weight:800;">Renew now</a>
                </div>
            @endif

            <main id="main-content" class="studio-reference-main mx-auto max-w-none px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    @if (! empty($licenseShowExpiryModal) && $licenseShowExpiryModal)
        <div id="zc-lic-expiry-modal" style="position:fixed;inset:0;z-index:1300;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.55);">
            <div style="background:#fff;border-radius:18px;max-width:420px;width:100%;padding:1.75rem;box-shadow:0 40px 90px -30px rgba(0,0,0,.5);">
                <div style="font-size:1.05rem;font-weight:800;color:#101828;margin-bottom:.5rem;">Your license expires in {{ $licenseDaysLeft }} day{{ $licenseDaysLeft === 1 ? '' : 's' }}</div>
                <p style="font-size:.88rem;color:#5b6675;line-height:1.55;margin:0 0 1.25rem;">Renew now to avoid any interruption once it lapses.</p>
                <div style="display:flex;gap:.6rem;justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('zc-lic-expiry-modal').remove();" class="studio-command-button">Dismiss</button>
                    <a href="{{ route('license.verification') }}" class="studio-command-button studio-command-button--primary">Renew</a>
                </div>
            </div>
        </div>
        <script>
            (function () {
                try {
                    if (sessionStorage.getItem('zc_lic_expiry_seen')) {
                        document.getElementById('zc-lic-expiry-modal').remove();
                    } else {
                        sessionStorage.setItem('zc_lic_expiry_seen', '1');
                    }
                } catch (e) {}
            })();
        </script>
    @endif

    {{-- AJAX feedback: the data-ajax-form handler writes here on every
         in-place (no-reload) action. --}}
    <div data-ajax-announcer aria-live="polite" class="sr-only"></div>
    <div data-ajax-toast class="studio-ajax-toast" role="status"></div>

    <script>
        (() => {
            // Topbar hide-on-scroll (global): reveal on scroll up, hide on
            // scroll down for more list room. Owns --zc-topbar-h so any
            // sticky sub-header (e.g. Manage Order) can pin right below it,
            // and drop to the top when the bar is hidden.
            const topbar = document.querySelector('.studio-topbar');
            if (topbar) {
                const setVar = () => document.documentElement.style.setProperty(
                    '--zc-topbar-h', (topbar.classList.contains('is-hidden') ? 0 : topbar.offsetHeight) + 'px'
                );
                let lastY = window.scrollY;
                setVar();
                window.addEventListener('resize', setVar);
                window.addEventListener('scroll', () => {
                    const y = Math.max(0, window.scrollY);
                    if (y > lastY && y > topbar.offsetHeight + 20) {
                        topbar.classList.add('is-hidden');
                    } else if (y < lastY) {
                        topbar.classList.remove('is-hidden');
                    }
                    setVar();
                    lastY = y;
                }, { passive: true });
            }

            // Close open topbar dropdowns (profile, mobile nav) on outside click.
            document.addEventListener('click', (event) => {
                document.querySelectorAll('.studio-profile-menu[open], .studio-menu-toggle[open]').forEach((det) => {
                    if (!det.contains(event.target)) det.removeAttribute('open');
                });
            });

            // The Studio admin panel was removed in full on 2026-07-24 to
            // be rebuilt page by page with new design/new code. This shell
            // keeps only the one piece of infrastructure every rebuilt page
            // is expected to reuse: the data-ajax-form convention from
            // _design/ZENNA-CRAFT-MASTER-BUILD-SPEC.md §2.6. A form marked
            // data-ajax-form submits via fetch instead of a full page load;
            // the server stays the single source of truth — this never
            // computes anything client-side, it only sends the request and
            // renders whatever HTML the server returns. Expected JSON
            // success shape: {success, message, regions: {name: html},
            // redirect?}. Every [data-region="name"] element whose name
            // appears in the response gets replaced (outerHTML).
            const ajaxAnnounce = (text, isError) => {
                const announcer = document.querySelector('[data-ajax-announcer]');
                if (announcer) announcer.textContent = text;

                const toast = document.querySelector('[data-ajax-toast]');
                if (!toast) return;
                toast.textContent = text;
                toast.classList.toggle('is-error', Boolean(isError));
                toast.classList.add('is-visible');
                clearTimeout(toast._zcHideTimer);
                toast._zcHideTimer = setTimeout(() => toast.classList.remove('is-visible'), 3200);
            };

            const ajaxClearErrors = (form) => {
                form.querySelectorAll('.zc-oc-field-error, .zc-oc-form-error').forEach((el) => el.remove());
            };

            const ajaxShowFieldError = (form, field, message) => {
                let target = null;
                try {
                    target = form.querySelector(`[name="${CSS.escape(field)}"]`);
                } catch (error) {
                    target = null;
                }

                if (target) {
                    const note = document.createElement('div');
                    note.className = 'zc-oc-field-error';
                    note.textContent = message;
                    (target.closest('div') || target).insertAdjacentElement('afterend', note);
                    return;
                }

                const banner = document.createElement('div');
                banner.className = 'zc-oc-form-error';
                banner.textContent = message;
                form.prepend(banner);
            };

            const ajaxSetLoading = (form, submitter, loading) => {
                form.classList.toggle('is-loading', loading);
                form.dataset.ajaxSubmitting = loading ? 'true' : 'false';
                form.querySelectorAll('[data-ajax-submit]').forEach((button) => {
                    button.disabled = loading;
                    button.classList.toggle('is-loading', loading && button === submitter);
                });
            };

            const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
            const syncSidebarToggle = () => {
                const collapsed = document.body.dataset.sidebarCollapsed === 'true';
                sidebarToggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                sidebarToggle?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            };
            syncSidebarToggle();

            sidebarToggle?.addEventListener('click', () => {
                const collapsed = document.body.dataset.sidebarCollapsed === 'true';
                document.body.dataset.sidebarCollapsed = collapsed ? 'false' : 'true';
                try {
                    window.localStorage.setItem('zenna.studio.sidebarCollapsed', collapsed ? 'false' : 'true');
                } catch (error) {
                    // Local storage unavailable (private mode etc.) — state just won't persist across loads.
                }
                syncSidebarToggle();
            });

            // Generic collapsible toggle: any [data-collapsible-toggle]
            // button shows/hides the [data-collapsible-body] that follows
            // it in the same parent.
            document.addEventListener('click', (event) => {
                const toggle = event.target.closest('[data-collapsible-toggle]');
                if (!toggle) {
                    return;
                }
                const body = toggle.parentElement?.querySelector('[data-collapsible-body]');
                if (!body) {
                    return;
                }
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                body.hidden = expanded;
            });

            document.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-ajax-form]');
                if (!form) return;

                event.preventDefault();

                if (form.dataset.ajaxSubmitting === 'true') {
                    return; // already in flight — ignore a fast double-click/double-submit
                }

                const confirmMessage = form.dataset.confirm;
                if (confirmMessage && !window.confirm(confirmMessage)) {
                    return;
                }

                const submitter = event.submitter || form.querySelector('[data-ajax-submit]');
                const sourceRegion = form.closest('[data-region]')?.dataset.region;

                ajaxClearErrors(form);
                ajaxSetLoading(form, submitter, true);

                // Only honour a button's formaction if it actually declares
                // one — per the HTML spec, button.formAction returns the
                // *document URL* (not the form's action) when the attribute
                // is absent, which would misroute every plain submit button.
                const submitUrl = (event.submitter && event.submitter.hasAttribute('formaction'))
                    ? event.submitter.getAttribute('formaction')
                    : form.action;

                (async () => {
                    try {
                        // Build the body explicitly. new FormData(form) drops
                        // submit buttons entirely, and the 2-arg constructor
                        // `new FormData(form, submitter)` is silently ignored on
                        // browsers before ~2023 — which meant the clicked verify
                        // button's `outcome` value never reached the server ("The
                        // outcome field is required"). Appending the submitter's
                        // own name/value by hand works on every browser.
                        const formData = new FormData(form);
                        if (event.submitter && event.submitter.name) {
                            formData.append(event.submitter.name, event.submitter.value ?? '');
                        }

                        const response = await fetch(submitUrl, {
                            method: 'POST', // real HTTP method comes from the form's own _method hidden field
                            body: formData,
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });

                        let payload = null;
                        try {
                            payload = await response.json();
                        } catch (error) {
                            payload = null;
                        }

                        if (response.ok) {
                            if (payload?.redirect) {
                                window.location.href = payload.redirect;
                                return;
                            }

                            if (payload?.regions) {
                                Object.entries(payload.regions).forEach(([name, html]) => {
                                    const el = document.querySelector(`[data-region="${name}"]`);
                                    if (!el) return;
                                    // Insert the fresh markup before the old node
                                    // and drop the old one — reliable for <tr>
                                    // (which parses correctly in its parent's
                                    // context) and gives us the new node to flash.
                                    el.insertAdjacentHTML('beforebegin', html);
                                    const fresh = el.previousElementSibling;
                                    el.remove();
                                    if (fresh && fresh.classList) {
                                        fresh.classList.add('zc-region-flash');
                                        setTimeout(() => fresh.classList.remove('zc-region-flash'), 1200);
                                    }
                                });
                            }

                            ajaxAnnounce(payload?.message || 'Updated.', false);

                            if (sourceRegion) {
                                const focusTarget = document.querySelector(`[data-region="${sourceRegion}"]`);
                                focusTarget?.focus();
                            }

                            return;
                        }

                        if (response.status === 422 && payload?.errors) {
                            Object.entries(payload.errors).forEach(([field, messages]) => {
                                ajaxShowFieldError(form, field, Array.isArray(messages) ? messages[0] : String(messages));
                            });
                            ajaxAnnounce(payload?.message || 'Please fix the highlighted fields.', true);
                            return;
                        }

                        const message = payload?.message
                            || (response.status === 403 ? 'You do not have permission to do that.' : 'Something went wrong. Please try again.');
                        ajaxShowFieldError(form, '__general__', message);
                        ajaxAnnounce(message, true);
                    } catch (error) {
                        ajaxShowFieldError(form, '__general__', 'Could not reach the server. Check your connection and try again.');
                        ajaxAnnounce('Could not reach the server.', true);
                    } finally {
                        ajaxSetLoading(form, submitter, false);
                    }
                })();
            });
        })();
    </script>

    @stack('studio-scripts')
</body>
</html>
