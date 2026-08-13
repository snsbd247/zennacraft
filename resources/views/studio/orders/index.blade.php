@extends('layouts.studio')

@section('title', 'Manage Order')
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
    <style>
        .zc-ol-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .zc-ol-actions { display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center; }

        /* Filter hover dropdown (contains the whole filter form) */
        .zc-ol-filter { position:relative; }
        .zc-ol-filter > button { display:inline-flex; align-items:center; gap:0.4rem; }
        .zc-ol-filter-menu {
            position:absolute; right:0; top:100%; z-index:40; padding-top:0.5rem; width:24rem;
            opacity:0; visibility:hidden; transform:translateY(-6px); transition:opacity .16s ease, transform .16s ease, visibility .16s;
        }
        /* A hover-bridge so moving from button to menu doesn't drop it. */
        .zc-ol-filter-menu::before { content:''; position:absolute; top:0; left:0; right:0; height:0.6rem; }
        .zc-ol-filter:hover .zc-ol-filter-menu,
        .zc-ol-filter:focus-within .zc-ol-filter-menu { opacity:1; visibility:visible; transform:translateY(0); }
        .zc-ol-filter-form {
            display:grid; gap:0.6rem; padding:0.9rem; border-radius:14px; border:1px solid var(--studio-border);
            background:var(--studio-surface); box-shadow:0 26px 60px -28px rgba(16,24,40,0.28);
        }
        .zc-ol-fl-2 { display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; }
        .zc-ol-fl { display:grid; gap:0.25rem; }
        .zc-ol-fl > span { display:flex; align-items:center; gap:0.4rem; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; color:var(--studio-muted); }
        .zc-ol-fl > span svg { width:0.9rem; height:0.9rem; color:rgba(199,154,59,0.9); }
        .zc-ol-fl-actions { display:flex; gap:0.5rem; margin-top:0.2rem; }

        .zc-ol-tabs { display:flex; gap:0.4rem; overflow-x:auto; padding-bottom:0.35rem; }
        .zc-ol-tab {
            display:inline-flex; align-items:center; gap:0.4rem; white-space:nowrap;
            padding:0.42rem 0.8rem; border-radius:999px; text-decoration:none;
            border:1px solid var(--studio-border); background:var(--studio-surface-soft);
            color:var(--studio-muted); font-size:0.8rem; font-weight:700; transition:all .15s ease;
        }
        .zc-ol-tab:hover { border-color:rgba(212,180,131,0.4); color:var(--studio-text); }
        .zc-ol-tab.is-active {
            background:linear-gradient(135deg,#f8ecc9 0%,#e0bd7d 45%,#a9793f 100%);
            color:#1a1408; border-color:transparent; box-shadow:0 10px 24px -12px rgba(212,180,131,0.6);
        }
        .zc-ol-tab__count {
            display:inline-flex; align-items:center; justify-content:center; min-width:1.4rem; height:1.4rem;
            padding:0 0.4rem; border-radius:999px; background:rgba(0,0,0,0.14); font-size:0.72rem; font-weight:800;
        }
        .zc-ol-tab.is-active .zc-ol-tab__count { background:rgba(26,20,8,0.18); color:#1a1408; }
        .zc-ol-tab.is-active { transform:translateY(-1px); }
        /* Colour-coded count badges (non-active tabs) for quick scanning. */
        .zc-ol-tab--pending .zc-ol-tab__count { background:rgba(201,147,15,0.16); color:#a9793f; }
        .zc-ol-tab--confirmed .zc-ol-tab__count, .zc-ol-tab--processing .zc-ol-tab__count, .zc-ol-tab--shipped .zc-ol-tab__count { background:rgba(59,110,165,0.14); color:#3b6ea5; }
        .zc-ol-tab--delivered .zc-ol-tab__count { background:rgba(28,138,78,0.16); color:#1c8a4e; }
        .zc-ol-tab--cancelled .zc-ol-tab__count, .zc-ol-tab--returned .zc-ol-tab__count { background:rgba(192,57,43,0.14); color:#c0392b; }
        .zc-ol-tab.is-active .zc-ol-tab__count { color:#1a1408 !important; }

        .zc-ol-toolbar { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap; }
        .zc-ol-show { display:flex; align-items:center; gap:0.5rem; color:var(--studio-muted); font-size:0.82rem; }
        .zc-ol-search { display:flex; gap:0.5rem; }

        .zc-ol-tbl { width:100%; border-collapse:collapse; }
        .zc-ol-tbl th {
            text-align:left; font-size:0.66rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
            color:var(--studio-muted); padding:0.7rem 0.9rem; background:rgba(255,253,247,0.025);
            border-bottom:1px solid var(--studio-border); white-space:nowrap;
        }
        .zc-ol-tbl td { padding:0.45rem 0.7rem; border-bottom:1px solid var(--studio-border); vertical-align:middle; font-size:0.76rem; color:var(--studio-text); }
        .zc-ol-tbl tbody tr:hover { background:transparent; }

        .zc-ol-badge { display:inline-flex; align-items:center; gap:0.3rem; padding:0.12rem 0.5rem; border-radius:6px; font-size:0.68rem; font-weight:800; line-height:1.4; }
        .zc-ol-badge--regular { background:rgba(95,165,120,0.16); color:#1c8a4e; }
        .zc-ol-badge--vip { background:rgba(212,180,131,0.2); color:#a9793f; }
        .zc-ol-badge--fraud { background:rgba(208,135,112,0.2); color:#c0392b; }

        .zc-ol-fraud-i {
            display:inline-flex; align-items:center; justify-content:center; width:1.15rem; height:1.15rem;
            border-radius:999px; border:1px solid rgba(122,162,201,0.5); background:rgba(122,162,201,0.14);
            color:#3b6ea5; font-size:0.68rem; font-weight:900; font-style:italic; cursor:pointer; flex:none;
        }
        .zc-ol-fraud-i:hover { background:rgba(122,162,201,0.28); }

        .zc-ol-phone-actions { display:flex; gap:0.3rem; margin-top:0.4rem; }
        .zc-ol-icon-btn {
            display:inline-flex; align-items:center; justify-content:center; width:1.5rem; height:1.5rem;
            border-radius:7px; border:1px solid var(--studio-border); background:var(--studio-surface-soft);
            color:var(--studio-muted); cursor:pointer; text-decoration:none; transition:all .15s ease;
        }
        .zc-ol-icon-btn:hover { border-color:rgba(212,180,131,0.45); color:#a9793f; }
        .zc-ol-icon-btn svg { width:0.85rem; height:0.85rem; }
        .zc-ol-icon-btn--wa:hover { color:#25d366; border-color:#25d366; }

        .zc-ol-cust-stats { margin-top:0.4rem; font-size:0.72rem; color:var(--studio-muted); }
        .zc-ol-cust-stats b { color:#c0392b; }

        .zc-ol-prod { display:flex; gap:0.55rem; align-items:center; }
        .zc-ol-thumb { width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--studio-border); background:var(--studio-surface-soft); flex:none; }
        .zc-ol-thumb[data-zoom] { cursor:zoom-in; }

        .zc-ol-money { display:grid; gap:0.05rem; font-size:0.74rem; }
        .zc-ol-money span { color:var(--studio-muted); }
        .zc-ol-money b { color:var(--studio-text); font-variant-numeric:tabular-nums; }

        .zc-ol-act { display:grid; gap:0.25rem; font-size:0.75rem; color:var(--studio-muted); }

        .zc-ol-pop { position:relative; }
        .zc-ol-pop > summary { list-style:none; cursor:pointer; }
        .zc-ol-pop > summary::-webkit-details-marker { display:none; }
        .zc-ol-pop__menu {
            /* The menu is portaled to <body>, which sits in the DARK sidebar
               token scope — so it carries the light content tokens explicitly,
               otherwise it inherits a dark surface/text. */
            --studio-surface:#ffffff; --studio-surface-soft:#f7f9fc;
            --studio-border:#e7ebf1; --studio-border-strong:#cbd5e1;
            --studio-text:#0f172a; --studio-muted:#64748b;
            position:fixed; z-index:80; width:16rem; padding:0.5rem;
            max-height:min(74vh, 32rem); overflow-y:auto; overscroll-behavior:contain;
            border-radius:18px; border:1px solid #e7ebf1;
            background:#ffffff;
            box-shadow:0 2px 4px rgba(16,24,40,0.04), 0 26px 60px -22px rgba(16,24,40,0.32), 0 10px 24px -16px rgba(16,24,40,0.22);
            transform-origin:top right;
        }
        .zc-ol-pop__menu.is-left { transform-origin:top left; }
        .zc-ol-pop__menu.is-up { transform-origin:bottom right; }
        .zc-ol-pop__menu.is-up.is-left { transform-origin:bottom left; }
        /* Entrance animation runs ONCE on open (is-opening) — never on reposition,
           so scrolling the menu can't make it flicker. */
        .zc-ol-pop__menu.is-opening { animation:zc-pop-in .18s cubic-bezier(.33,1.3,.5,1); }
        .zc-ol-pop__menu.is-opening.is-up { animation-name:zc-pop-in-up; }
        .zc-ol-pop__menu::-webkit-scrollbar { width:10px; }
        .zc-ol-pop__menu::-webkit-scrollbar-thumb { background:#dfe4ec; border-radius:10px; border:3px solid #fff; }
        @keyframes zc-pop-in { from { opacity:0; transform:translateY(-7px) scale(0.96); } to { opacity:1; transform:none; } }
        @keyframes zc-pop-in-up { from { opacity:0; transform:translateY(7px) scale(0.96); } to { opacity:1; transform:none; } }
        /* No-JS fallback: keep it anchored under the button instead of at 0,0. */
        .no-js .zc-ol-pop__menu { position:absolute; right:0; top:100%; }
        .zc-ol-pop__title { text-align:center; font-weight:800; margin-bottom:0.6rem; color:var(--studio-text); }
        .zc-ol-formstack { display:grid; gap:0.5rem; }
        .zc-ol-flabel { font-size:0.72rem; font-weight:700; color:var(--studio-muted); }

        .zc-ol-verify-btns { display:grid; gap:0.3rem; }
        .zc-ol-verify-btns button {
            width:100%; text-align:left; padding:0.4rem 0.6rem; border-radius:8px; border:1px solid var(--studio-border);
            background:var(--studio-surface-soft); color:var(--studio-text); font-size:0.78rem; font-weight:700; cursor:pointer;
        }
        .zc-ol-verify-btns button:hover { border-color:rgba(212,180,131,0.45); background:rgba(212,180,131,0.08); }
        .zc-ol-verify-btns button.is-verify { background:rgba(95,165,120,0.16); color:#1c8a4e; border-color:transparent; }

        /* Premium action list: neutral rows, the status colour lives in a small
           icon tile that fills on hover — restrained and expert, not 12 loud bars. */
        .zc-ol-menu-head { font-size:0.62rem; font-weight:800; letter-spacing:0.09em; text-transform:uppercase; color:#94a3b8; padding:0.3rem 0.55rem 0.45rem; }
        .zc-ol-menu-sep { height:1px; background:#eef1f5; margin:0.4rem 0.3rem; }
        .zc-ol-actions-menu { width:14rem; display:grid; gap:0.06rem; }
        .zc-ol-act-btn {
            display:flex; align-items:center; gap:0.65rem; width:100%; text-align:left;
            padding:0.32rem 0.42rem; border-radius:11px; border:1px solid transparent;
            font-size:0.83rem; font-weight:700; color:#1f2937; cursor:pointer; text-decoration:none;
            background:transparent; transition:background .14s ease, box-shadow .14s ease;
        }
        .zc-ol-act-btn svg {
            width:1.85rem; height:1.85rem; flex:none; padding:0.45rem; border-radius:9px;
            background:#eef2f7; color:var(--c,#64748b);
            transition:background .15s ease, color .15s ease, transform .15s ease;
        }
        @supports (background:color-mix(in srgb,red,blue)) {
            .zc-ol-act-btn svg { background:color-mix(in srgb, var(--c,#64748b) 15%, #fff); }
        }
        .zc-ol-act-btn:hover { background:#f6f8fb; box-shadow:inset 0 0 0 1px #eceff4; }
        .zc-ol-act-btn:hover svg { background:var(--c,#64748b); color:#fff; transform:scale(1.06); }
        .zc-ol-act-btn:active { transform:translateY(0.5px); }
        .zc-ol-act-btn--approved { --c:#1c8a4e; }
        .zc-ol-act-btn--packaging{ --c:#2f66ad; }
        .zc-ol-act-btn--shipment { --c:#1f8b86; }
        .zc-ol-act-btn--delivered{ --c:#0f7a45; }
        .zc-ol-act-btn--return   { --c:#c76a1a; }
        .zc-ol-act-btn--pending  { --c:#b8860b; }
        .zc-ol-act-btn--cancel   { --c:#c0392b; }
        .zc-ol-act-btn--block    { --c:#9b2242; }
        .zc-ol-act-btn--edit     { --c:#64748b; }
        .zc-ol-act-btn--view     { --c:#334155; }
        .zc-ol-act-btn--label    { --c:#5b45a8; }
        .zc-ol-act-btn--pos      { --c:#2f7d5a; }
        .zc-ol-actions-menu form { margin:0; }
        /* Inline "pick courier then ship" (shown when no courier is assigned) */
        .zc-ol-shipform { background:#f5f8fb; border:1px solid #e9edf3; border-radius:11px; padding:0.5rem 0.55rem; margin:0.12rem 0; }
        .zc-ol-shipform__label { display:flex; align-items:center; gap:0.45rem; font-size:0.76rem; font-weight:800; color:#1f8b86; margin-bottom:0.4rem; }
        .zc-ol-shipform__label svg { width:1rem; height:1rem; flex:none; }
        .zc-ol-shipform__row { display:flex; gap:0.4rem; }
        .zc-ol-shipform__row .studio-form-control { flex:1; min-width:0; font-size:0.8rem; padding:0.35rem 0.5rem; height:auto; }
        .zc-ol-shipform__go { flex:none; border:none; border-radius:9px; background:linear-gradient(135deg,#3bb6b0,#1f8b86); color:#fff; font-weight:800; font-size:0.8rem; padding:0 0.9rem; cursor:pointer; }
        .zc-ol-shipform__go:hover { filter:brightness(1.06); }

        .zc-ol-check { width:1rem; height:1rem; accent-color:#c79a3b; }

        /* Floating image zoom (escapes the table's overflow via fixed pos). */
        #zc-ol-zoom {
            position:fixed; z-index:300; display:none; pointer-events:none;
            width:260px; height:260px; border-radius:16px; overflow:hidden;
            border:1px solid var(--studio-border); background:var(--studio-surface);
            box-shadow:0 40px 90px -30px rgba(16,24,40,0.28);
        }
        #zc-ol-zoom img { width:100%; height:100%; object-fit:contain; background:#fff; }

        /* Floating fraud-check popup. */
        #zc-ol-fraud { position:fixed; z-index:300; display:none; width:20rem; }
        #zc-ol-fraud .zc-fr-card {
            border-radius:16px; border:1px solid var(--studio-border); background:var(--studio-surface);
            box-shadow:0 40px 90px -30px rgba(16,24,40,0.28); padding:0.9rem;
        }
        .zc-fr-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; }
        .zc-fr-stat { text-align:center; border:1px solid var(--studio-border); border-radius:10px; padding:0.5rem 0.3rem; background:var(--studio-surface-soft); }
        .zc-fr-stat b { display:block; font-size:1.3rem; font-weight:800; color:var(--studio-text); }
        .zc-fr-stat span { font-size:0.66rem; color:var(--studio-muted); }
        .zc-fr-tbl { width:100%; border-collapse:collapse; margin-top:0.6rem; font-size:0.78rem; }
        .zc-fr-tbl th { text-align:left; color:var(--studio-muted); font-size:0.66rem; text-transform:uppercase; padding:0.3rem; border-bottom:1px solid var(--studio-border); }
        .zc-fr-tbl td { padding:0.35rem 0.3rem; border-bottom:1px solid var(--studio-border); color:var(--studio-text); }
        .zc-fr-rate { font-weight:800; color:#1c8a4e; }

        /* ---------- Sticky header ---------- */
        .zc-ol-sticky {
            position: sticky;
            top: var(--zc-topbar-h, 0px);
            z-index: 25;
            padding: 1rem 0 0.85rem;
            background: var(--studio-bg);
            display: grid;
            gap: 0.9rem;
            transition: box-shadow 0.25s ease, background 0.25s ease;
        }
        .zc-ol-sticky.is-stuck {
            box-shadow: 0 18px 30px -24px rgba(16,24,40,0.28);
            border-bottom: 1px solid rgba(199,154,59,0.16);
        }

        /* ---------- Premium colour pass ---------- */
        .zc-op-panel {
            background:
                radial-gradient(circle at 100% 0%, rgba(212,180,131,0.06), transparent 40%),
                linear-gradient(180deg, rgba(255,253,247,0.02), rgba(255,253,247,0));
            background-color: var(--studio-surface);
        }

        /* Strip the design-system's outer box (border + shadow + radius +
           bg) around the scroll container so the cards float cleanly with
           no stray frame/line on the edges. */
        .zc-ol-tablewrap .studio-responsive-scroll {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }

        /* ---------- Card rows: every row exactly ONE opaque dark-blue
           colour, with a gently animated gold border ---------- */
        @keyframes zc-row-glow {
            0%   { border-color: rgba(201,162,74,0.62); }
            20%  { border-color: rgba(107,143,214,0.62); }
            40%  { border-color: rgba(95,165,120,0.62); }
            60%  { border-color: rgba(166,121,201,0.62); }
            80%  { border-color: rgba(217,138,154,0.62); }
            100% { border-color: rgba(201,162,74,0.62); }
        }
        .zc-ol-tbl--cards { border-collapse: separate; border-spacing: 0 0.5rem; }
        .zc-ol-tbl--cards thead th {
            background: transparent; border: none; padding-bottom: 0.1rem; color: #667085;
        }
        /* Override the generic `main tbody tr:hover` (design-system, !important)
           that would otherwise tint whichever row is hovered. */
        .zc-ol-tbl--cards tbody tr,
        main .zc-ol-tbl--cards tbody tr:hover { background: transparent !important; }
        /* Solid, opaque dark-blue for EVERY row (normal + hover). */
        .zc-ol-tbl--cards tbody td,
        main .zc-ol-tbl--cards tbody tr:hover td {
            background: #ffffff !important;
            border-top: 1px solid rgba(212,180,131,0.28);
            border-bottom: 1px solid rgba(212,180,131,0.28);
            animation: zc-row-glow 9s linear infinite;
        }
        .zc-ol-tbl--cards tbody td:first-child {
            border-left: 1px solid rgba(212,180,131,0.28);
            border-top-left-radius: 12px; border-bottom-left-radius: 12px; padding-left: 0.85rem;
        }
        .zc-ol-tbl--cards tbody td:last-child {
            border-right: 1px solid rgba(212,180,131,0.28);
            border-top-right-radius: 12px; border-bottom-right-radius: 12px;
        }
        @media (prefers-reduced-motion: reduce) {
            .zc-ol-tbl--cards tbody td { animation: none; }
        }

        /* Premium status / verification badges */
        .zc-ol-tbl .studio-badge {
            border-radius: 999px !important;
            padding: 0.22rem 0.7rem !important;
            font-size: 0.66rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: 1px solid transparent !important;
        }
        .zc-ol-tbl .studio-badge--success { background: rgba(52,199,123,0.16) !important; color: #1c8a4e !important; border-color: rgba(52,199,123,0.34) !important; box-shadow: 0 0 0 3px rgba(52,199,123,0.06); }
        .zc-ol-tbl .studio-badge--warning { background: rgba(240,190,70,0.16) !important; color: #b45309 !important; border-color: rgba(240,190,70,0.34) !important; box-shadow: 0 0 0 3px rgba(240,190,70,0.06); }
        .zc-ol-tbl .studio-badge--danger  { background: rgba(240,110,96,0.16) !important; color: #c0392b !important; border-color: rgba(240,110,96,0.34) !important; box-shadow: 0 0 0 3px rgba(240,110,96,0.06); }
        .zc-ol-tbl .studio-badge--info    { background: rgba(126,158,236,0.18) !important; color: #2563eb !important; border-color: rgba(126,158,236,0.36) !important; box-shadow: 0 0 0 3px rgba(126,158,236,0.06); }
        .zc-ol-tbl .studio-badge--neutral { background: rgba(168,178,198,0.14) !important; color: #475467 !important; border-color: rgba(168,178,198,0.28) !important; }

        /* Premium customer badges */
        .zc-ol-badge { border-radius:999px; padding:0.16rem 0.6rem; border:1px solid transparent; }
        .zc-ol-badge--regular { background:rgba(52,199,123,0.14); color:#1c8a4e; border-color:rgba(52,199,123,0.28); }
        .zc-ol-badge--vip { background:linear-gradient(135deg,rgba(248,236,201,0.22),rgba(169,121,63,0.16)); color:#855d2b; border-color:rgba(212,180,131,0.4); }
        .zc-ol-badge--fraud { background:rgba(224,90,74,0.16); color:#c0392b; border-color:rgba(224,90,74,0.36); }

        /* Invoice link + phone accents */
        .zc-ol-tbl a.zc-op-strong { color:#a9793f !important; }
        .zc-ol-fraud-i { border-color:rgba(212,180,131,0.5); background:rgba(212,180,131,0.16); color:#a9793f; }
        .zc-ol-fraud-i:hover { background:rgba(212,180,131,0.3); }
    </style>
@endpush

@section('content')
    <div class="zc-ol-page">
        {{-- Sticky header: title + actions + tabs + toolbar stay pinned while the list scrolls --}}
        <div class="zc-ol-sticky" data-ol-sticky>
            <div class="zc-ol-head">
                <div>
                    <h1 class="studio-section-title">Orders</h1>
                    <p class="studio-section-subtitle">{{ number_format($totalOrders) }} total orders — manage, verify, and dispatch.</p>
                </div>
                <div class="zc-ol-actions">
                    <div class="zc-ol-filter" data-filter-hover>
                        <button type="button" class="studio-command-button">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:1rem;height:1rem;"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>
                            Filter
                        </button>
                        <div class="zc-ol-filter-menu">
                            <form method="GET" action="{{ route('orders.index') }}" class="zc-ol-filter-form">
                                @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                                <label class="zc-ol-fl">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>Search</span>
                                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="studio-form-control" placeholder="Name / phone / order #">
                                </label>
                                <div class="zc-ol-fl-2">
                                    <label class="zc-ol-fl"><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="studio-form-control"></label>
                                    <label class="zc-ol-fl"><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="studio-form-control"></label>
                                </div>
                                <label class="zc-ol-fl">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>Courier</span>
                                    <select name="courier" class="studio-form-control"><option value="">All couriers</option>@foreach ($couriers as $c)<option value="{{ $c->id }}" @selected((string)($filters['courier'] ?? '') === (string)$c->id)>{{ $c->name }}</option>@endforeach</select>
                                </label>
                                <label class="zc-ol-fl">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.3-7-11.5A7 7 0 0 1 19 9.5C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.4"/></svg>District</span>
                                    <select name="district" class="studio-form-control"><option value="">All districts</option>@foreach ($districts as $d)<option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>@endforeach</select>
                                </label>
                                <label class="zc-ol-fl">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>Order Source</span>
                                    <select name="source" class="studio-form-control"><option value="">All sources</option>@foreach ($orderSources as $s)<option value="{{ $s }}" @selected(($filters['source'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                                </label>
                                <label class="zc-ol-fl">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>Approve Admin</span>
                                    <select name="approve_admin" class="studio-form-control"><option value="">Any admin</option>@foreach ($approveAdmins as $a)<option value="{{ $a->id }}" @selected((string)($filters['approve_admin'] ?? '') === (string)$a->id)>{{ $a->name }}</option>@endforeach</select>
                                </label>
                                <label class="zc-ol-fl">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>Creator</span>
                                    <select name="creator" class="studio-form-control"><option value="">Anyone</option><option value="customer" @selected(($filters['creator'] ?? '') === 'customer')>Customer</option><option value="admin" @selected(($filters['creator'] ?? '') === 'admin')>Admin</option></select>
                                </label>
                                <div class="zc-ol-fl-actions">
                                    <button type="submit" class="studio-command-button studio-command-button--primary" style="flex:1; justify-content:center;">Apply</button>
                                    <a href="{{ route('orders.index') }}" class="studio-command-button">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <a href="{{ route('orders.exchange.create') }}" class="studio-command-button">Exchange</a>
                    <a href="{{ route('orders.create') }}" class="studio-command-button studio-command-button--primary">Create Order</a>
                </div>
            </div>

            <div class="zc-ol-tabs">
                <a href="{{ route('orders.index') }}" data-tab-status="" class="zc-ol-tab {{ empty($filters['status']) ? 'is-active' : '' }}">All <span class="zc-ol-tab__count">{{ number_format($totalOrders) }}</span></a>
                @php $tabLabels = ['pending'=>'New','confirmed'=>'Approved','processing'=>'Packaging','shipped'=>'Shipment','delivered'=>'Delivered','returned'=>'Return','cancelled'=>'Cancel']; @endphp
                @foreach ($statuses as $status)
                    <a href="{{ route('orders.index', ['status' => $status]) }}" data-tab-status="{{ $status }}" class="zc-ol-tab zc-ol-tab--{{ $status }} {{ ($filters['status'] ?? '') === $status ? 'is-active' : '' }}">
                        {{ $tabLabels[$status] ?? ucfirst($status) }} <span class="zc-ol-tab__count">{{ number_format((int) ($statusCounts[$status] ?? 0)) }}</span>
                    </a>
                @endforeach
            </div>

            <div class="zc-ol-toolbar">
                <form method="GET" action="{{ route('orders.index') }}" class="zc-ol-show">
                    @foreach ($filters as $k => $v)@if($k !== 'per_page')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                    <span>Show</span>
                    <select name="per_page" class="studio-form-control" style="width:auto;" onchange="this.form.submit()">
                        @foreach ($perPageOptions as $opt)<option value="{{ $opt }}" @selected($perPage === $opt)>{{ $opt }}</option>@endforeach
                    </select>
                </form>
                <form method="GET" action="{{ route('orders.index') }}" class="zc-ol-search">
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="studio-form-control" placeholder="type invoice or customer phone" style="min-width:16rem;">
                    <button type="submit" class="studio-command-button studio-command-button--primary">Order</button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="studio-callout studio-callout--success" style="margin-top:1rem;">{{ session('success') }}</div>
        @endif

        {{-- Live new-order watch (polls every 30s; never reloads the page) --}}
        <div class="zc-livewatch" data-latest-order-id="{{ (int) ($latestOrderId ?? 0) }}">
            <span class="zc-livewatch__status"><span class="zc-livewatch__dot"></span> Watching for new orders</span>
            <button type="button" class="zc-livewatch__sound" data-sound-toggle aria-pressed="true" title="Sound alert for new orders">
                <svg data-sound-ic viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
                <span data-sound-label>Sound on</span>
            </button>
        </div>

        {{-- Floating "N new orders" pill (appears only when new orders arrive) --}}
        <div class="zc-neworders" data-neworders>
            <button type="button" class="zc-neworders__load" data-load-new>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                <b data-new-count>0</b> new order<span data-new-plural>s</span> — Load
            </button>
        </div>

        {{-- Table (card rows) --}}
        <section class="zc-ol-tablewrap" style="margin-top:1rem;position:relative;" id="zc-orders-region" data-orders-region>
            @include('studio.orders.partials._results')
        </section>
    </div>

    {{-- Shared floating popups --}}
    <div id="zc-ol-zoom"><img src="" alt=""></div>
    <div id="zc-ol-fraud"></div>

    @push('studio-scripts')
        <script>
            (() => {
                document.querySelector('[data-check-all]')?.addEventListener('change', (e) => {
                    document.querySelectorAll('.zc-ol-check:not([data-check-all])').forEach(c => c.checked = e.target.checked);
                });

                // Sticky header shadow once stuck (the layout owns --zc-topbar-h
                // and the topbar hide-on-scroll).
                const sticky = document.querySelector('[data-ol-sticky]');
                const syncStuck = () => {
                    if (!sticky) return;
                    const topH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--zc-topbar-h')) || 0;
                    sticky.classList.toggle('is-stuck', sticky.getBoundingClientRect().top <= topH + 1);
                };
                syncStuck();
                window.addEventListener('scroll', syncStuck, true);

                // --- Row action popup ---
                // While open, the menu is PORTALED to <body>. A row lives inside
                // the table's horizontal-scroll wrapper; positioning the menu
                // there (even fixed) is unreliable and it ends up off-screen to
                // the right. As a direct child of <body> it's pinned to the
                // viewport under the 3-dot button and flips up when there's no
                // room below — always visible, never needing a sideways scroll.
                const positionPop = (det) => {
                    const summary = det.querySelector('summary');
                    const menu = det.__menu;
                    if (!summary || !menu) return;
                    const gap = 6, pad = 8;
                    const vw = document.documentElement.clientWidth, vh = window.innerHeight;
                    const r = summary.getBoundingClientRect();
                    const mw = menu.offsetWidth, mh = menu.offsetHeight;
                    // Horizontal: right-align to the button, clamp inside the viewport.
                    let left = menu.classList.contains('is-left') ? r.left : (r.right - mw);
                    left = Math.max(pad, Math.min(left, vw - mw - pad));
                    // Vertical: below by default; flip above only when needed.
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
                    const menu = det.__menu || det.querySelector('.zc-ol-pop__menu');
                    if (!menu) return;
                    det.__menu = menu; menu.__owner = det;
                    if (menu.parentElement !== document.body) document.body.appendChild(menu);
                    positionPop(det);
                    // Play the entrance animation exactly once, then drop the class
                    // so later repositioning (on scroll) never re-triggers it.
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
                    document.querySelectorAll('details.zc-ol-pop[open]').forEach((d) => { if (d !== except) closePop(d); });
                    document.querySelectorAll('body > .zc-ol-pop__menu').forEach((m) => {
                        if (m.__owner && m.__owner !== except) closePop(m.__owner);
                        else if (!m.__owner) m.remove();
                    });
                };

                // Open/close via the native <details> toggle…
                document.addEventListener('toggle', (e) => {
                    const det = e.target;
                    if (!det.matches || !det.matches('details.zc-ol-pop')) return;
                    if (det.open) { closeAllPops(det); openPop(det); } else { closePop(det); }
                }, true);
                // …plus a click fallback (the toggle event doesn't fire through
                // capture in every browser), run after the native toggle applies.
                document.addEventListener('click', (e) => {
                    const sum = e.target.closest && e.target.closest('details.zc-ol-pop > summary');
                    if (!sum) return;
                    const det = sum.parentElement;
                    requestAnimationFrame(() => { if (det.open) { closeAllPops(det); openPop(det); } else { closePop(det); } });
                }, true);

                // Close when clicking outside the button AND outside the menu.
                document.addEventListener('click', (e) => {
                    if (e.target.closest('.zc-ol-pop') || e.target.closest('.zc-ol-pop__menu')) return;
                    closeAllPops(null);
                });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAllPops(null); });

                const repositionOpenPops = () => document.querySelectorAll('details.zc-ol-pop[open]').forEach(positionPop);
                window.addEventListener('scroll', (e) => {
                    // Scrolling INSIDE the menu must not reposition it (that's what
                    // caused the shake). Only the page/table scrolling moves the
                    // button, so only then do we re-anchor.
                    const t = e.target;
                    if (t && t.nodeType === 1 && t.closest && t.closest('.zc-ol-pop__menu')) return;
                    repositionOpenPops();
                }, true);
                window.addEventListener('resize', repositionOpenPops);

                // Copy phone (delegated — survives row swaps)
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-copy]');
                    if (!btn) return;
                    navigator.clipboard?.writeText(btn.dataset.copy);
                    btn.style.borderColor = '#1c8a4e';
                    setTimeout(() => btn.style.borderColor = '', 900);
                });

                // Image hover zoom (fixed popup escapes table overflow)
                const zoom = document.getElementById('zc-ol-zoom');
                const zoomImg = zoom.querySelector('img');
                document.addEventListener('mouseover', (e) => {
                    const img = e.target.closest('.zc-ol-thumb[data-zoom]');
                    if (!img) return;
                    zoomImg.src = img.dataset.zoom;
                    zoom.style.display = 'block';
                    const r = img.getBoundingClientRect();
                    let left = r.right + 14, top = r.top - 100;
                    if (left + 260 > window.innerWidth) left = r.left - 274;
                    top = Math.max(12, Math.min(top, window.innerHeight - 272));
                    zoom.style.left = left + 'px';
                    zoom.style.top = top + 'px';
                });
                document.addEventListener('mouseout', (e) => {
                    if (e.target.closest('.zc-ol-thumb[data-zoom]')) zoom.style.display = 'none';
                });

                // Fraud check popup
                const fraud = document.getElementById('zc-ol-fraud');
                const closeFraud = () => { fraud.style.display = 'none'; };
                document.addEventListener('click', async (e) => {
                    const trigger = e.target.closest('[data-fraud-check]');
                    if (!trigger) {
                        if (!e.target.closest('#zc-ol-fraud')) closeFraud();
                        return;
                    }
                    e.preventDefault();
                    const r = trigger.getBoundingClientRect();
                    fraud.innerHTML = '<div class="zc-fr-card"><div style="color:var(--studio-muted);font-size:0.8rem;">Checking…</div></div>';
                    fraud.style.display = 'block';
                    let left = r.left, top = r.bottom + 8;
                    if (left + 320 > window.innerWidth) left = window.innerWidth - 332;
                    fraud.style.left = Math.max(12, left) + 'px';
                    fraud.style.top = top + 'px';
                    try {
                        const url = new URL(trigger.dataset.url, window.location.origin);
                        url.searchParams.set('phone', trigger.dataset.phone);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const d = await res.json();
                        const rows = (d.couriers || []).map(c =>
                            `<tr><td>${c.name}</td><td>${c.orders}</td><td>${c.delivered}</td><td>${c.cancelled}</td><td class="zc-fr-rate">${c.success_rate}%</td></tr>`).join('');
                        fraud.innerHTML = `<div class="zc-fr-card">
                            <div class="zc-fr-stats">
                                <div class="zc-fr-stat"><b>${d.total_orders}</b><span>Total Order</span></div>
                                <div class="zc-fr-stat"><b>${d.total_delivered}</b><span>Total Delivery</span></div>
                                <div class="zc-fr-stat"><b>${d.total_cancelled}</b><span>Total Cancel</span></div>
                            </div>
                            <table class="zc-fr-tbl"><thead><tr><th>Courier</th><th>Order</th><th>Deliv</th><th>Cancel</th><th>Rate</th></tr></thead><tbody>${rows}</tbody></table>
                            <div style="text-align:right;margin-top:0.5rem;"><button type="button" class="studio-command-button" style="font-size:0.72rem;padding:0.3rem 0.6rem;" data-fraud-close>Close</button></div>
                        </div>`;
                    } catch (err) {
                        fraud.innerHTML = '<div class="zc-fr-card"><div style="color:#c0392b;font-size:0.8rem;">Could not load fraud check.</div></div>';
                    }
                });
                document.addEventListener('click', (e) => { if (e.target.closest('[data-fraud-close]')) closeFraud(); });
                window.addEventListener('scroll', closeFraud, true);

                // Close any open row popup after a successful in-place action.
                // The menu is portaled to <body>, so find its owning <details>
                // via the menu, not via closest().
                document.addEventListener('click', (e) => {
                    const submit = e.target.closest('[data-ajax-submit]');
                    if (submit) {
                        const menu = submit.closest('.zc-ol-pop__menu');
                        const det = (menu && menu.__owner) || submit.closest('details.zc-ol-pop');
                        if (det) setTimeout(() => closePop(det), 60);
                    }
                });
            })();
        </script>
    @endpush

    @push('studio-styles')
        <style>
        #zc-orders-region.is-loading{opacity:.5;transition:opacity .15s ease;pointer-events:none;}
        .zc-livewatch{display:flex;align-items:center;justify-content:flex-end;gap:.7rem;margin:.7rem 0 -.4rem;font-size:.72rem;}
        .zc-livewatch__status{display:inline-flex;align-items:center;gap:.42rem;color:var(--studio-muted);font-weight:700;}
        .zc-livewatch__dot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:zcLiveDot 2s infinite;}
        @keyframes zcLiveDot{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5);}70%{box-shadow:0 0 0 6px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
        .zc-livewatch__sound{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--studio-border);background:var(--studio-surface);color:var(--studio-text);border-radius:999px;padding:.28rem .75rem;font-size:.72rem;font-weight:800;cursor:pointer;transition:border-color .15s ease,color .15s ease,opacity .15s ease;}
        .zc-livewatch__sound svg{width:14px;height:14px;}
        .zc-livewatch__sound:hover{border-color:rgba(199,154,59,.5);}
        .zc-livewatch__sound.is-off{color:var(--studio-muted);opacity:.75;}
        .zc-livewatch__sound.is-off [data-sound-ic] path:nth-child(2),.zc-livewatch__sound.is-off [data-sound-ic] path:nth-child(3){display:none;}
        .zc-neworders{position:fixed;top:76px;left:50%;transform:translateX(-50%) translateY(-18px);z-index:90;opacity:0;pointer-events:none;transition:opacity .3s ease,transform .35s cubic-bezier(.2,1.35,.4,1);}
        .zc-neworders.is-show{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:auto;}
        .zc-neworders__load{display:inline-flex;align-items:center;gap:.55rem;border:none;cursor:pointer;font-family:inherit;background:linear-gradient(135deg,#1fa15b,#12703d);color:#fff;font-weight:800;font-size:.85rem;padding:.72rem 1.35rem;border-radius:999px;box-shadow:0 16px 34px -12px rgba(28,138,78,.7);animation:zcNewPulse 2.2s ease-in-out infinite;}
        .zc-neworders__load svg{width:18px;height:18px;animation:zcBell 2.2s ease-in-out infinite;transform-origin:top center;}
        .zc-neworders__load:hover{filter:brightness(1.06);}
        .zc-neworders__load b{background:rgba(255,255,255,.25);padding:.05rem .55rem;border-radius:999px;font-size:.9rem;}
        @keyframes zcNewPulse{0%,100%{box-shadow:0 16px 34px -12px rgba(28,138,78,.68);}50%{box-shadow:0 18px 44px -10px rgba(28,138,78,.95);}}
        @keyframes zcBell{0%,60%,100%{transform:rotate(0);}70%{transform:rotate(12deg);}80%{transform:rotate(-9deg);}90%{transform:rotate(5deg);}}
        @media(prefers-reduced-motion:reduce){.zc-livewatch__dot,.zc-neworders__load,.zc-neworders__load svg{animation:none;}}
        </style>
    @endpush
    @push('studio-scripts')
        <script>
            // Live AJAX search + pagination for the orders table (no page reload).
            (function(){
                var region = document.getElementById('zc-orders-region');
                var searchForm = document.querySelector('form.zc-ol-search');
                if(!region || !searchForm) return;
                var input = searchForm.querySelector('input[name="search"]');
                var baseUrl = "{{ route('orders.index') }}";
                var t, controller;

                function params(extra){
                    var p = new URLSearchParams(window.location.search);
                    Object.keys(extra||{}).forEach(function(k){ (extra[k]===null||extra[k]==='') ? p.delete(k) : p.set(k, extra[k]); });
                    return p;
                }
                function load(p){
                    var url = baseUrl + '?' + p.toString();
                    region.classList.add('is-loading');
                    if(controller) controller.abort();
                    controller = new AbortController();
                    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}, signal:controller.signal})
                        .then(function(r){ return r.text(); })
                        .then(function(html){ region.innerHTML = html; region.classList.remove('is-loading'); window.history.replaceState({}, '', url); })
                        .catch(function(e){ if(e.name!=='AbortError') region.classList.remove('is-loading'); });
                }
                if(input){
                    input.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(function(){ load(params({search: input.value.trim(), page: null})); }, 350); });
                }
                searchForm.addEventListener('submit', function(e){ e.preventDefault(); clearTimeout(t); load(params({search: input ? input.value.trim() : '', page: null})); });
                // AJAX pagination inside the swapped region
                region.addEventListener('click', function(e){
                    var a = e.target.closest('nav a[href], .p-4 a[href]'); if(!a) return;
                    e.preventDefault();
                    var u = new URL(a.href, window.location.origin);
                    load(new URLSearchParams(u.search));
                    var top = region.getBoundingClientRect().top + window.pageYOffset - 90;
                    window.scrollTo({top: top, behavior:'smooth'});
                });

                // AJAX status tabs — switch filters instantly, no page reload
                // (falls back to the plain link if the swap can't run).
                var tabWrap = document.querySelector('.zc-ol-tabs');
                if (tabWrap) tabWrap.addEventListener('click', function (e) {
                    var tab = e.target.closest('.zc-ol-tab'); if (!tab) return;
                    e.preventDefault();
                    tabWrap.querySelectorAll('.zc-ol-tab').forEach(function (t) { t.classList.remove('is-active'); });
                    tab.classList.add('is-active');
                    load(params({ status: tab.getAttribute('data-tab-status') || null, page: null }));
                });

                // ---------- Live new-order watch (polls every 30s, no reload) ----------
                var pollUrl = "{{ route('orders.new-check') }}";
                var watch = document.querySelector('.zc-livewatch');
                var baseline = watch ? (parseInt(watch.getAttribute('data-latest-order-id'), 10) || 0) : 0;
                var latestKnown = baseline;
                var pill = document.querySelector('[data-neworders]');
                var loadNewBtn = pill ? pill.querySelector('[data-load-new]') : null;
                var pillCount = document.querySelector('[data-new-count]');
                var pillPlural = document.querySelector('[data-new-plural]');
                var baseTitle = document.title;
                var lastCount = 0, audioCtx = null, unlocked = false;
                var soundBtn = document.querySelector('[data-sound-toggle]');
                var soundOn = (function(){ try { return localStorage.getItem('zc.orders.sound') !== 'off'; } catch(e){ return true; } })();

                function paintSound(){
                    if(!soundBtn) return;
                    soundBtn.classList.toggle('is-off', !soundOn);
                    soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
                    var l = soundBtn.querySelector('[data-sound-label]'); if(l) l.textContent = soundOn ? 'Sound on' : 'Sound off';
                }
                paintSound();

                // Browsers need a user gesture before audio/notifications work — the
                // first click anywhere unlocks both.
                function unlock(){
                    if(unlocked) return; unlocked = true;
                    try { audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)(); if(audioCtx.state === 'suspended') audioCtx.resume(); } catch(e){}
                    if('Notification' in window && Notification.permission === 'default'){ try { Notification.requestPermission(); } catch(e){} }
                }
                document.addEventListener('click', unlock, { once:true });

                function chime(){
                    try {
                        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                        if(audioCtx.state === 'suspended') audioCtx.resume();
                        var t0 = audioCtx.currentTime;
                        [880, 1320].forEach(function(f, i){
                            var o = audioCtx.createOscillator(), g = audioCtx.createGain();
                            o.type = 'sine'; o.frequency.value = f; o.connect(g); g.connect(audioCtx.destination);
                            var s = t0 + i * 0.16;
                            g.gain.setValueAtTime(0.0001, s);
                            g.gain.exponentialRampToValueAtTime(0.16, s + 0.02);
                            g.gain.exponentialRampToValueAtTime(0.0001, s + 0.32);
                            o.start(s); o.stop(s + 0.34);
                        });
                    } catch(e){}
                }

                if(soundBtn) soundBtn.addEventListener('click', function(){
                    soundOn = !soundOn;
                    try { localStorage.setItem('zc.orders.sound', soundOn ? 'on' : 'off'); } catch(e){}
                    paintSound();
                    if(soundOn){ unlock(); chime(); }   // preview + unlock on enable
                });

                function showPill(count, order){
                    if(pill){
                        if(pillCount) pillCount.textContent = count;
                        if(pillPlural) pillPlural.style.display = count === 1 ? 'none' : '';
                        pill.classList.add('is-show');
                    }
                    document.title = '(' + count + ') ' + baseTitle;
                    if(count > lastCount){
                        if(soundOn) chime();
                        if('Notification' in window && Notification.permission === 'granted'){
                            var body = order ? (order.number + ' · ' + order.customer + ' · ৳' + Math.round(order.total)) : (count + ' new order(s)');
                            try { new Notification('🛒 New order received', { body: body, tag: 'zc-new-order' }); } catch(e){}
                        }
                    }
                    lastCount = count;
                }
                function clearPill(){
                    if(pill) pill.classList.remove('is-show');
                    document.title = baseTitle; lastCount = 0;
                }

                function poll(){
                    fetch(pollUrl + '?after=' + baseline, { headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } })
                        .then(function(r){ return r.ok ? r.json() : null; })
                        .then(function(d){
                            if(!d) return;
                            latestKnown = d.latest_id || latestKnown;
                            if((d.count || 0) > 0) showPill(d.count, d.order); else clearPill();
                        })
                        .catch(function(){});
                }
                setInterval(poll, 30000);

                if(loadNewBtn) loadNewBtn.addEventListener('click', function(){
                    baseline = latestKnown;   // everything up to now counts as seen
                    load(params({}));         // refresh the current filtered view in place
                    clearPill();
                });
            })();
        </script>
    @endpush
@endsection
