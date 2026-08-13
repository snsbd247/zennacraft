@include('partials.global-design-system')

<style>
    :root {
        --studio-bg: #f8fafc;
        --studio-surface: #ffffff;
        --studio-surface-soft: #f8fafc;
        --studio-border: #e2e8f0;
        --studio-border-strong: #cbd5e1;
        --studio-text: #0f172a;
        --studio-muted: #64748b;
        --studio-primary: #0f172a;
        --studio-accent: #2563eb;
        --studio-success: #16a34a;
        --studio-warning: #d97706;
        --studio-danger: #dc2626;
        --studio-info: #0284c7;
        --studio-radius: 12px;
        --studio-radius-lg: 16px;
        --studio-shadow: 0 16px 42px -28px rgba(15, 23, 42, 0.4);
        --studio-shadow-soft: 0 2px 12px rgba(15, 23, 42, 0.04);
    }

    html {
        background: var(--studio-bg);
        scroll-behavior: smooth;
    }

    body {
        min-height: 100vh;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.06), transparent 24%),
            linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
        color: var(--studio-text);
    }

    * {
        box-sizing: border-box;
    }

    @keyframes studio-shimmer {
        100% {
            transform: translateX(100%);
        }
    }

    :where(a, button, input, select, textarea, summary):focus-visible {
        outline: 2px solid #0f172a;
        outline-offset: 2px;
    }

    :where(a, button, summary) {
        transition:
            color 0.18s ease,
            background 0.18s ease,
            border-color 0.18s ease,
            box-shadow 0.18s ease,
            transform 0.18s ease;
    }

    :where(button, .studio-empty-state__action, .studio-action-btn):active {
        transform: translateY(1px) scale(0.99);
    }

    .studio-shell {
        min-height: 100vh;
    }

    .studio-sidebar {
        background: #020617;
        color: #cbd5e1;
    }

    .studio-sidebar__brand {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    }

    .studio-brand-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #ffffff, #cbd5e1);
        color: #020617;
        font-size: 0.875rem;
        font-weight: 800;
        letter-spacing: -0.04em;
        box-shadow: 0 12px 32px rgba(255, 255, 255, 0.1);
    }

    .studio-nav-group {
        padding: 0.5rem 0;
    }

    .studio-nav-group > summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem 0.4rem;
        color: #94a3b8;
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .studio-nav-group > summary::-webkit-details-marker {
        display: none;
    }

    .studio-nav-group > summary svg {
        width: 0.9rem;
        height: 0.9rem;
        transition: transform 0.2s ease;
        color: #64748b;
    }

    .studio-nav-group[open] > summary svg {
        transform: rotate(90deg);
    }

    .studio-menu-toggle > summary {
        list-style: none;
    }

    .studio-menu-toggle > summary::-webkit-details-marker {
        display: none;
    }

    .studio-nav-link {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin: 0 0.5rem;
        padding: 0.8rem 0.9rem;
        border-radius: 14px;
        color: #cbd5e1;
        font-size: 0.93rem;
        font-weight: 600;
        transition:
            background 0.18s ease,
            color 0.18s ease,
            transform 0.18s ease,
            box-shadow 0.18s ease;
    }

    .studio-nav-link:hover {
        background: rgba(148, 163, 184, 0.12);
        color: #ffffff;
        transform: translateX(1px);
    }

    .studio-nav-link.is-active,
    .studio-nav-link[aria-current="page"] {
        background: #ffffff;
        color: #020617;
        box-shadow: var(--studio-shadow);
    }

    .studio-nav-link__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 10px;
        background: rgba(148, 163, 184, 0.12);
        color: inherit;
        flex: none;
    }

    .studio-nav-link.is-active .studio-nav-link__icon,
    .studio-nav-link[aria-current="page"] .studio-nav-link__icon {
        background: rgba(15, 23, 42, 0.08);
    }

    .studio-topbar {
        position: sticky;
        top: 0;
        z-index: 30;
        background: rgba(255, 255, 255, 0.86);
        backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
    }

    .studio-topbar__inner {
        min-height: 5rem;
    }

    .studio-breadcrumbs {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8125rem;
        color: var(--studio-muted);
    }

    .studio-breadcrumbs a {
        color: #475569;
    }

    .studio-breadcrumbs__separator {
        color: #cbd5e1;
    }

    .studio-page-title {
        font-size: 1.7rem;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #020617;
    }

    .studio-page-subtitle {
        margin-top: 0.35rem;
        color: var(--studio-muted);
        font-size: 0.95rem;
    }

    .studio-search {
        position: relative;
    }

    .studio-search input {
        width: min(22rem, 100%);
        padding: 0.8rem 1rem 0.8rem 2.65rem;
        border-radius: 9999px;
        border: 1px solid var(--studio-border);
        background: #ffffff;
        color: var(--studio-text);
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-search svg {
        position: absolute;
        left: 0.95rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: #94a3b8;
    }

    .studio-profile-menu > summary {
        list-style: none;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.8rem;
        border-radius: 9999px;
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
        cursor: pointer;
    }

    .studio-profile-menu > summary::-webkit-details-marker {
        display: none;
    }

    .studio-profile-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, #020617, #334155);
        color: #ffffff;
        font-size: 0.875rem;
        font-weight: 800;
    }

    .studio-dropdown {
        position: absolute;
        right: 0;
        margin-top: 0.6rem;
        width: 18rem;
        border-radius: 18px;
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow);
        padding: 0.85rem;
        transform-origin: top right;
        animation: studio-dropdown-in 0.16s ease-out;
    }

    @keyframes studio-dropdown-in {
        from {
            opacity: 0;
            transform: translateY(-4px) scale(0.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .studio-dropdown__label {
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--studio-muted);
    }

    .studio-dropdown__name {
        font-weight: 700;
        color: #020617;
    }

    .studio-dropdown__meta {
        font-size: 0.75rem;
        color: var(--studio-muted);
    }

    .studio-dropdown__action {
        display: block;
        margin-top: 0.75rem;
        width: 100%;
        border-radius: 12px;
        background: #0f172a;
        color: #ffffff;
        padding: 0.75rem 1rem;
        font-weight: 700;
        text-align: center;
    }

    .studio-card,
    .studio-panel,
    .studio-form-panel,
    .studio-stat-card,
    .studio-summary-card,
    .studio-module-card,
    main :is(section, form).rounded.border.border-gray-200.bg-white,
    main :is(section, form).rounded.border.border-slate-200.bg-white {
        border-radius: var(--studio-radius) !important;
        border-color: var(--studio-border) !important;
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
    }

    main :is(section, form).bg-gray-50 {
        border-radius: var(--studio-radius) !important;
    }

    .studio-stat-card {
        position: relative;
        overflow: hidden;
    }

    .studio-card,
    .studio-metric-card,
    .studio-form-section,
    .studio-page-hero {
        transition:
            border-color 0.18s ease,
            box-shadow 0.18s ease,
            transform 0.18s ease;
    }

    .studio-card:hover,
    .studio-metric-card:hover,
    .studio-form-section:hover {
        border-color: var(--studio-border-strong);
        box-shadow: var(--studio-shadow);
    }

    .studio-stat-card::after {
        content: '';
        position: absolute;
        inset: auto -2rem -2rem auto;
        width: 7rem;
        height: 7rem;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(15, 23, 42, 0.08), transparent 70%);
        pointer-events: none;
    }

    .studio-kpi-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--studio-muted);
    }

    .studio-kpi-value {
        margin-top: 0.5rem;
        font-size: 1.9rem;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #020617;
    }

    .studio-kpi-sub {
        margin-top: 0.45rem;
        font-size: 0.8rem;
        color: var(--studio-muted);
    }

    .studio-badge,
    main span.inline-flex.rounded.px-2.py-1.text-xs.font-semibold,
    main span.inline-flex.rounded.px-2.py-1.text-sm.font-semibold {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 9999px !important;
        padding: 0.35rem 0.7rem !important;
        line-height: 1;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.28);
    }

    .studio-badge--neutral {
        background: #e2e8f0;
        color: #334155;
    }

    .studio-badge--success {
        background: #dcfce7;
        color: #166534;
    }

    .studio-badge--warning {
        background: #fef3c7;
        color: #92400e;
    }

    .studio-badge--danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .studio-badge--info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    main table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    main .overflow-x-auto {
        border-radius: var(--studio-radius);
        overflow: auto;
        -webkit-overflow-scrolling: touch;
        box-shadow: var(--studio-shadow-soft);
        border: 1px solid var(--studio-border);
        background: #ffffff;
    }

    main .overflow-x-auto table {
        border: none;
        box-shadow: none;
    }

    main thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #f8fafc;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    main table th,
    main table td {
        border-bottom: 1px solid #e2e8f0;
        padding-top: 0.95rem;
        padding-bottom: 0.95rem;
    }

    main tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    main tbody tr:hover {
        background: #eef2ff;
    }

    main tbody tr {
        transition: background 0.16s ease;
    }

    .studio-table-compact table th,
    .studio-table-compact table td {
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }

    main nav[role="navigation"] {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
    }

    main nav[role="navigation"] > div {
        display: flex;
        gap: 0.35rem;
        align-items: center;
        flex-wrap: wrap;
    }

    main nav[role="navigation"] a,
    main nav[role="navigation"] span {
        border-radius: 9999px;
        border: 1px solid var(--studio-border);
        background: #ffffff;
        color: #0f172a;
        box-shadow: var(--studio-shadow-soft);
    }

    main form {
        color: inherit;
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    main select,
    main textarea {
        border-radius: var(--studio-radius) !important;
        border: 1px solid var(--studio-border-strong) !important;
        background: #ffffff !important;
        color: #0f172a !important;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
    main select:focus,
    main textarea:focus {
        border-color: #334155 !important;
        box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.08) !important;
    }

    main label {
        color: #334155;
        font-weight: 700;
    }

    main .text-gray-500,
    main .text-slate-500 {
        color: var(--studio-muted) !important;
    }

    main .text-gray-600,
    main .text-slate-600 {
        color: #475569 !important;
    }

    main .text-gray-700,
    main .text-slate-700 {
        color: #334155 !important;
    }

    main .text-gray-900,
    main .text-slate-900,
    main .text-slate-950 {
        color: #020617 !important;
    }

    main .bg-white {
        background: #ffffff !important;
    }

    main .border-gray-200,
    main .border-slate-200 {
        border-color: var(--studio-border) !important;
    }

    .studio-section-title {
        font-size: 1.1rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #020617;
    }

    .studio-section-subtitle {
        margin-top: 0.35rem;
        color: var(--studio-muted);
        font-size: 0.925rem;
    }

    .studio-page-grid {
        display: grid;
        gap: 1.5rem;
    }

    .studio-action-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: rgba(255, 255, 255, 0.9);
        box-shadow: var(--studio-shadow-soft);
        padding: 0.85rem;
    }

    .studio-action-bar__group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
    }

    .studio-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: #ffffff;
        color: #334155;
        box-shadow: var(--studio-shadow-soft);
        padding: 0.7rem 0.95rem;
        font-size: 0.875rem;
        font-weight: 750;
    }

    .studio-action-btn:hover {
        border-color: var(--studio-border-strong);
        color: #020617;
        transform: translateY(-1px);
    }

    .studio-action-btn--primary {
        border-color: #0f172a;
        background: #0f172a;
        color: #ffffff;
    }

    .studio-action-btn--primary:hover {
        background: #020617;
        color: #ffffff;
    }

    .studio-page-hero {
        display: grid;
        gap: 1rem;
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
        box-shadow: var(--studio-shadow-soft);
        padding: 1.5rem;
    }

    .studio-page-hero__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .studio-page-hero__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .studio-metric-grid {
        display: grid;
        gap: 1rem;
    }

    .studio-metric-card {
        position: relative;
        overflow: hidden;
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
        padding: 1.25rem;
    }

    .studio-metric-card__label {
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--studio-muted);
    }

    .studio-metric-card__value {
        margin-top: 0.5rem;
        font-size: 1.75rem;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #020617;
    }

    .studio-metric-card__meta {
        margin-top: 0.4rem;
        font-size: 0.8rem;
        color: var(--studio-muted);
    }

    .studio-filter-panel {
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
        padding: 1rem;
    }

    .studio-form-section {
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
        padding: 1.25rem;
    }

    .studio-form-section + .studio-form-section {
        margin-top: 1rem;
    }

    .studio-form-section__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .studio-form-section__title {
        font-size: 1rem;
        font-weight: 800;
        color: #020617;
    }

    .studio-form-section__subtitle {
        margin-top: 0.25rem;
        color: var(--studio-muted);
        font-size: 0.875rem;
    }

    .studio-form-grid {
        display: grid;
        gap: 1rem;
    }

    .studio-timeline {
        display: grid;
        gap: 0.9rem;
    }

    .studio-timeline__item {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0.9rem;
        align-items: start;
    }

    .studio-timeline__dot {
        width: 0.85rem;
        height: 0.85rem;
        margin-top: 0.25rem;
        border-radius: 9999px;
        background: #cbd5e1;
        box-shadow: 0 0 0 5px rgba(226, 232, 240, 0.45);
    }

    .studio-timeline__item.is-active .studio-timeline__dot {
        background: #0f172a;
        box-shadow: 0 0 0 5px rgba(15, 23, 42, 0.12);
    }

    .studio-timeline__item.is-complete .studio-timeline__dot {
        background: #16a34a;
        box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14);
    }

    .studio-timeline__card {
        border-radius: 14px;
        border: 1px solid var(--studio-border);
        background: #ffffff;
        padding: 0.9rem 1rem;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-callout {
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(51, 65, 85, 0.98));
        color: #ffffff;
        padding: 1rem 1.25rem;
        box-shadow: var(--studio-shadow);
    }

    .studio-callout--warning {
        background: linear-gradient(135deg, #f59e0b, #f97316);
    }

    .studio-callout--danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
    }

    .studio-callout--success {
        background: linear-gradient(135deg, #16a34a, #15803d);
    }

    .studio-soft-note {
        border-radius: 14px;
        border: 1px dashed var(--studio-border-strong);
        background: #f8fafc;
        padding: 0.9rem 1rem;
        color: var(--studio-muted);
        font-size: 0.9rem;
    }

    .studio-inline-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
    }

    .studio-responsive-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .studio-empty-state {
        display: grid;
        justify-items: center;
        gap: 0.9rem;
        max-width: 32rem;
        margin-inline: auto;
        padding: 2.25rem 1rem;
        text-align: center;
    }

    .studio-empty-state__icon {
        display: inline-flex;
        height: 3.5rem;
        width: 3.5rem;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        border: 1px solid var(--studio-border);
        background: linear-gradient(135deg, #ffffff, #f8fafc);
        color: #0f172a;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-empty-state__icon svg {
        height: 1.5rem;
        width: 1.5rem;
    }

    .studio-empty-state__title {
        font-size: 1rem;
        font-weight: 850;
        color: #020617;
    }

    .studio-empty-state__description {
        margin-top: 0.35rem;
        color: var(--studio-muted);
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .studio-empty-state__action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--studio-radius);
        background: #0f172a;
        color: #ffffff;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        font-weight: 800;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-empty-state__action:hover {
        background: #020617;
        transform: translateY(-1px);
    }

    .studio-skeleton {
        position: relative;
        overflow: hidden;
        border-radius: 0.75rem;
        background: #e2e8f0;
    }

    .studio-skeleton::after {
        content: '';
        position: absolute;
        inset: 0;
        transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.72), transparent);
        animation: studio-shimmer 1.25s infinite;
    }

    .studio-skeleton-card {
        display: grid;
        gap: 0.75rem;
        border-radius: var(--studio-radius);
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
        padding: 1.25rem;
    }

    .studio-loading-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    }

    #studio-loading-shell {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: none;
        background: rgba(248, 250, 252, 0.88);
        backdrop-filter: blur(12px);
        padding: 6rem 1.5rem;
    }

    body.is-studio-loading #studio-loading-shell {
        display: block;
    }

    .studio-mobile-label {
        display: none;
    }

    @media (max-width: 1023px) {
        .studio-sidebar {
            position: static;
            width: 100%;
        }

        .studio-topbar__inner {
            min-height: auto;
        }

        .studio-page-title {
            font-size: 1.45rem;
        }
    }

    @media (max-width: 767px) {
        .studio-action-bar {
            align-items: stretch;
        }

        .studio-action-bar__group,
        .studio-action-btn {
            width: 100%;
        }

        .studio-action-btn {
            justify-content: center;
        }

        .studio-mobile-stack table,
        .studio-mobile-stack thead,
        .studio-mobile-stack tbody,
        .studio-mobile-stack tr,
        .studio-mobile-stack th,
        .studio-mobile-stack td {
            display: block;
        }

        .studio-mobile-stack thead {
            display: none;
        }

        .studio-mobile-stack tbody tr {
            margin: 0.75rem;
            border: 1px solid var(--studio-border);
            border-radius: var(--studio-radius);
            background: #ffffff !important;
            box-shadow: var(--studio-shadow-soft);
            overflow: hidden;
        }

        .studio-mobile-stack table td {
            display: grid;
            grid-template-columns: minmax(7rem, 0.38fr) 1fr;
            gap: 0.8rem;
            border-bottom: 1px solid var(--studio-border);
            padding: 0.85rem 1rem;
        }

        .studio-mobile-stack table td:last-child {
            border-bottom: 0;
        }

        .studio-mobile-stack table td[colspan] {
            display: block;
        }

        .studio-mobile-label {
            display: block;
            color: var(--studio-muted);
            font-size: 0.72rem;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
    }
</style>

<style>
    /* Phase 62 global design bridge: Studio legacy classes mapped to shared tokens. */
    :root {
        --studio-bg: var(--zc-ds-color-ivory-100);
        --studio-surface: var(--zc-ds-color-surface);
        --studio-surface-soft: var(--zc-ds-color-surface-muted);
        --studio-border: var(--zc-ds-color-border);
        --studio-border-strong: var(--zc-ds-color-border-strong);
        --studio-text: var(--zc-ds-color-charcoal-950);
        --studio-muted: var(--zc-ds-color-muted);
        --studio-primary: var(--zc-ds-color-indigo-950);
        --studio-accent: var(--zc-ds-color-gold-600);
        --studio-success: var(--zc-ds-color-success);
        --studio-warning: var(--zc-ds-color-warning);
        --studio-danger: var(--zc-ds-color-danger);
        --studio-info: var(--zc-ds-color-info);
        --studio-radius: var(--zc-ds-radius-md);
        --studio-radius-lg: var(--zc-ds-radius-xl);
        --studio-shadow: var(--zc-ds-shadow-md);
        --studio-shadow-soft: var(--zc-ds-shadow-sm);
    }

    body.studio-shell {
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.12), transparent 28%),
            linear-gradient(180deg, var(--zc-ds-color-ivory-50) 0%, var(--zc-ds-color-ivory-100) 100%);
        color: var(--studio-text);
        letter-spacing: var(--zc-ds-letter-normal);
    }

    .studio-page-title {
        color: var(--zc-ds-color-charcoal-950);
        font-size: clamp(1.7rem, 2.4vw, 2.4rem);
        font-weight: 950;
        letter-spacing: var(--zc-ds-letter-normal);
        line-height: var(--zc-ds-line-tight);
    }

    .studio-section-title {
        color: var(--zc-ds-color-charcoal-950);
        font-weight: 930;
        letter-spacing: var(--zc-ds-letter-normal);
    }

    .studio-page-subtitle,
    .studio-section-subtitle,
    .studio-breadcrumbs {
        color: var(--zc-ds-color-muted);
        line-height: var(--zc-ds-line-body);
    }

    .studio-card,
    .studio-panel,
    .studio-form-panel,
    .studio-form-section,
    .studio-stat-card,
    .studio-summary-card,
    .studio-module-card,
    .studio-metric-card,
    .studio-page-hero,
    .studio-table-shell,
    .studio-skeleton-card,
    .studio-empty-state,
    .studio-os-kpi,
    .studio-os-health-card,
    .studio-os-focus-card,
    .studio-productivity-card {
        border-color: var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-xl) !important;
        background-color: var(--zc-ds-color-surface);
        box-shadow: var(--zc-ds-shadow-sm);
    }

    .studio-card:hover,
    .studio-module-card:hover,
    .studio-productivity-card:hover,
    .studio-os-kpi:hover {
        border-color: rgba(199, 154, 59, 0.55);
        box-shadow: var(--zc-ds-shadow-md);
    }

    .studio-command-button,
    .studio-action-btn,
    .studio-btn,
    .studio-dropdown__action,
    main button[type="submit"] {
        border-radius: var(--zc-ds-radius-pill) !important;
        font-weight: 850;
        letter-spacing: var(--zc-ds-letter-normal);
        min-height: 2.55rem;
    }

    .studio-command-button--primary,
    .studio-action-btn--primary,
    main button[type="submit"],
    main a.rounded-xl.bg-slate-950 {
        background: linear-gradient(135deg, var(--zc-ds-color-indigo-950), var(--zc-ds-color-indigo-800)) !important;
        color: var(--zc-ds-color-ivory-50) !important;
        box-shadow: var(--zc-ds-shadow-md);
    }

    .studio-command-button:not(.studio-command-button--primary),
    .studio-btn--secondary,
    .studio-action-btn:not(.studio-action-btn--primary),
    main a.rounded-xl.border,
    main button.rounded-xl.border {
        border-color: var(--zc-ds-color-border-strong) !important;
        background: var(--zc-ds-color-surface) !important;
        color: var(--zc-ds-color-indigo-950) !important;
        box-shadow: var(--zc-ds-shadow-sm);
    }

    .studio-icon-button,
    .studio-mini-action {
        border-radius: var(--zc-ds-radius-pill);
    }

    .studio-badge,
    .studio-command-token {
        border-radius: var(--zc-ds-radius-pill);
        font-size: var(--zc-ds-font-size-badge);
        font-weight: 850;
        letter-spacing: var(--zc-ds-letter-normal);
    }

    .studio-badge--neutral { background: #f3f4f6; border-color: #e5e7eb; color: #374151; }
    .studio-badge--success { background: var(--zc-ds-color-success-soft); border-color: #bddfbd; color: var(--zc-ds-color-success); }
    .studio-badge--warning { background: var(--zc-ds-color-warning-soft); border-color: #edd99d; color: var(--zc-ds-color-warning); }
    .studio-badge--danger { background: var(--zc-ds-color-danger-soft); border-color: #e7b6aa; color: var(--zc-ds-color-danger); }
    .studio-badge--info { background: var(--zc-ds-color-info-soft); border-color: #c4d0ec; color: var(--zc-ds-color-info); }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    main select,
    main textarea,
    .studio-command-bar,
    .studio-command-palette__search {
        border-color: var(--zc-ds-color-border-strong) !important;
        border-radius: var(--zc-ds-radius-md);
        background: var(--zc-ds-color-surface) !important;
        color: var(--zc-ds-color-charcoal-950) !important;
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
    main select:focus,
    main textarea:focus,
    .studio-command-bar:focus-within,
    .studio-command-palette__search:focus-within {
        border-color: var(--zc-ds-color-gold-600) !important;
        box-shadow: var(--zc-ds-shadow-focus) !important;
    }

    main table {
        font-size: var(--zc-ds-font-size-table);
    }

    main thead th,
    .studio-table-shell thead th {
        background: var(--zc-ds-color-surface-muted);
        color: var(--zc-ds-color-muted);
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: var(--zc-ds-letter-wide);
        text-transform: uppercase;
    }

    main tbody tr:hover,
    .studio-table-shell tbody tr:hover {
        background: rgba(243, 227, 184, 0.24);
    }

    .studio-empty-state {
        border-style: dashed;
        background: linear-gradient(180deg, var(--zc-ds-color-ivory-50), var(--zc-ds-color-ivory-100));
    }

    .studio-skeleton,
    .studio-skeleton-card {
        background: linear-gradient(90deg, #ede4d5, #fffaf0, #ede4d5);
    }
</style>

<style>
    /* Phase 41 Commerce OS foundation */
    .studio-nav-subheading {
        margin: 0.85rem 0.35rem 0.35rem;
        display: flex;
        align-items: center;
        gap: 0.55rem;
        color: rgba(203, 213, 225, 0.74);
        font-size: 0.66rem;
        font-weight: 950;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .studio-nav-subheading::after {
        content: '';
        height: 1px;
        flex: 1 1 auto;
        background: rgba(203, 213, 225, 0.14);
    }

    .studio-nav-link--child {
        margin-left: 0.55rem;
        padding-left: 0.8rem;
    }

    .studio-dropdown--right {
        left: auto;
        right: 0;
    }

    .studio-notification-center > summary,
    .studio-quick-actions > summary,
    .studio-profile-menu > summary,
    .studio-menu-toggle > summary {
        list-style: none;
    }

    .studio-notification-center > summary::-webkit-details-marker,
    .studio-quick-actions > summary::-webkit-details-marker,
    .studio-profile-menu > summary::-webkit-details-marker,
    .studio-menu-toggle > summary::-webkit-details-marker {
        display: none;
    }

    .studio-notification-panel {
        width: min(24rem, calc(100vw - 2rem));
    }

    .studio-notification-item,
    .studio-command-palette__item {
        display: grid;
        gap: 0.28rem;
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.82);
        background: #ffffff;
        padding: 0.9rem 1rem;
        text-decoration: none;
        transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease, background 160ms ease;
    }

    .studio-notification-item:hover,
    .studio-notification-item:focus-visible,
    .studio-command-palette__item:hover,
    .studio-command-palette__item:focus-visible {
        border-color: rgba(199, 154, 59, 0.5);
        background: #fffaf0;
        box-shadow: 0 18px 44px -34px rgba(15, 23, 42, 0.52);
        transform: translateY(-1px);
        outline: none;
    }

    .studio-notification-item strong,
    .studio-command-palette__item strong {
        color: #0f172a;
        font-size: 0.9rem;
        font-weight: 950;
    }

    .studio-notification-item span,
    .studio-command-palette__item span {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 650;
        line-height: 1.45;
    }

    .studio-command-palette[hidden] {
        display: none;
    }

    .studio-command-palette {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: grid;
        place-items: start center;
        padding: 8vh 1rem 1rem;
    }

    .studio-command-palette__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.46);
        backdrop-filter: blur(10px);
    }

    .studio-command-palette__dialog {
        position: relative;
        z-index: 1;
        width: min(44rem, 100%);
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.92);
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.18), transparent 34%),
            #fffdf7;
        box-shadow: 0 32px 90px -42px rgba(15, 23, 42, 0.75);
    }

    .studio-command-palette__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.82);
        padding: 1.15rem 1.25rem;
    }

    .studio-command-palette__title {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 950;
    }

    .studio-command-palette__header p {
        margin-top: 0.25rem;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 650;
    }

    .studio-command-palette__search {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.82);
        padding: 1rem 1.25rem;
    }

    .studio-command-palette__search input {
        width: 100%;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        color: #0f172a;
        font-size: 0.98rem;
        font-weight: 750;
        outline: none;
        padding: 0 !important;
    }

    .studio-command-palette__search span:not(.sr-only) {
        flex: none;
        border-radius: 9999px;
        background: #eef2f7;
        color: #475569;
        padding: 0.35rem 0.65rem;
        font-size: 0.7rem;
        font-weight: 950;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .studio-command-palette__list {
        display: grid;
        max-height: min(26rem, 52vh);
        gap: 0.65rem;
        overflow-y: auto;
        padding: 1rem;
    }

    .studio-command-palette__empty {
        border-radius: 18px;
        border: 1px dashed rgba(148, 163, 184, 0.8);
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 750;
        padding: 1.25rem;
        text-align: center;
    }

    .studio-notification-count {
        min-width: 1.35rem;
        border-radius: 9999px;
        background: #b85f42;
        color: #fffdf7;
        display: inline-grid;
        font-size: 0.68rem;
        font-weight: 950;
        line-height: 1;
        padding: 0.28rem 0.42rem;
        place-items: center;
    }

    .studio-notification-count:empty,
    .studio-notification-count[data-empty="true"] {
        display: none;
    }

    .studio-mini-action {
        border: 1px solid rgba(203, 213, 225, 0.9);
        border-radius: 9999px;
        background: #fffdf7;
        color: #1d2147;
        cursor: pointer;
        flex: none;
        font-size: 0.68rem;
        font-weight: 950;
        letter-spacing: 0.08em;
        padding: 0.42rem 0.7rem;
        text-transform: uppercase;
        transition: border-color 160ms ease, background 160ms ease, color 160ms ease, transform 160ms ease;
    }

    .studio-mini-action:hover,
    .studio-mini-action:focus-visible {
        border-color: rgba(199, 154, 59, 0.62);
        background: #fff7df;
        color: #0f172a;
        outline: none;
        transform: translateY(-1px);
    }

    .studio-notification-item {
        position: relative;
    }

    .studio-notification-item a {
        display: grid;
        gap: 0.28rem;
        text-decoration: none;
    }

    .studio-notification-item.is-unread {
        border-color: rgba(184, 95, 66, 0.45);
        box-shadow: inset 4px 0 0 rgba(184, 95, 66, 0.72);
    }

    .studio-notification-item[data-notification-severity="critical"],
    .studio-notification-item[data-notification-severity="danger"] {
        background: #fff6f2;
    }

    .studio-notification-item__meta {
        display: flex !important;
        justify-content: space-between;
        gap: 0.75rem;
        text-transform: uppercase;
    }

    .studio-notification-item__toggle {
        justify-self: start;
        border: 0;
        background: transparent;
        color: #9a6b1f;
        cursor: pointer;
        font-size: 0.7rem;
        font-weight: 900;
        padding: 0;
    }

    .studio-command-palette__header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .studio-command-section {
        display: grid;
        gap: 0.65rem;
    }

    .studio-command-section__title {
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 950;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .studio-command-token-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .studio-command-token {
        align-items: center;
        border: 1px solid rgba(199, 154, 59, 0.32);
        border-radius: 9999px;
        background: rgba(243, 227, 184, 0.32);
        color: #1d2147;
        display: inline-flex;
        gap: 0.45rem;
        max-width: 100%;
        padding: 0.42rem 0.7rem;
        text-decoration: none;
    }

    button.studio-command-token {
        cursor: pointer;
        font: inherit;
    }

    .studio-command-token.is-active {
        border-color: rgba(29, 33, 71, 0.5);
        background: #1d2147;
        color: #fffdf7;
    }

    .studio-command-token strong {
        font-size: 0.76rem;
        font-weight: 950;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .studio-command-token button {
        border: 0;
        background: transparent;
        color: #8a3928;
        cursor: pointer;
        font-weight: 950;
        padding: 0;
    }

    .studio-productivity-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
    }

    .studio-productivity-card {
        border: 1px solid rgba(226, 232, 240, 0.86);
        border-radius: 18px;
        background: linear-gradient(180deg, #fffdf7, #fbf7ec);
        padding: 1rem;
        text-decoration: none;
        transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }

    .studio-productivity-card:hover,
    .studio-productivity-card:focus-visible {
        border-color: rgba(199, 154, 59, 0.55);
        box-shadow: 0 18px 44px -34px rgba(15, 23, 42, 0.52);
        outline: none;
        transform: translateY(-1px);
    }

    .studio-productivity-card span {
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .studio-productivity-card strong {
        color: #0f172a;
        display: block;
        font-size: 1.65rem;
        font-weight: 950;
        margin-top: 0.35rem;
    }

    .studio-productivity-card p {
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 650;
        line-height: 1.5;
        margin-top: 0.35rem;
    }

    .studio-activity-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .studio-activity-filter.is-active {
        border-color: rgba(29, 33, 71, 0.5);
        background: #1d2147;
        color: #fffdf7;
    }

    .studio-activity-list {
        display: grid;
        gap: 0.65rem;
    }

    .studio-activity-item {
        border: 1px solid rgba(226, 232, 240, 0.82);
        border-radius: 16px;
        background: #fff;
        display: grid;
        gap: 0.25rem;
        padding: 0.85rem 1rem;
        text-decoration: none;
    }

    .studio-activity-item:hover,
    .studio-activity-item:focus-visible {
        border-color: rgba(199, 154, 59, 0.48);
        outline: none;
    }

    .studio-activity-item__meta {
        color: #64748b;
        display: flex;
        font-size: 0.72rem;
        font-weight: 850;
        gap: 0.5rem;
        justify-content: space-between;
        text-transform: uppercase;
    }

    .studio-activity-item strong {
        color: #0f172a;
        font-size: 0.9rem;
        font-weight: 950;
    }

    .studio-activity-item p {
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 650;
    }

    .studio-bulk-modal[hidden] {
        display: none;
    }

    .studio-bulk-modal {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: grid;
        place-items: center;
        padding: 1rem;
    }

    .studio-bulk-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(10px);
    }

    .studio-bulk-modal__dialog {
        position: relative;
        z-index: 1;
        width: min(40rem, 100%);
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.92);
        border-radius: 26px;
        background: #fffdf7;
        box-shadow: 0 32px 90px -42px rgba(15, 23, 42, 0.75);
    }

    .studio-bulk-modal__body {
        padding: 1rem 1.25rem;
    }

    .studio-bulk-modal__footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        border-top: 1px solid rgba(226, 232, 240, 0.82);
        padding: 1rem 1.25rem;
    }

    .studio-bulk-modal__value {
        text-transform: capitalize;
    }

    body.is-command-palette-open {
        overflow: hidden;
    }

    .studio-commerce-os {
        display: grid;
        gap: 1.25rem;
    }

    .studio-os-hero {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1.25rem;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 28px;
        background:
            radial-gradient(circle at 86% 12%, rgba(199, 154, 59, 0.24), transparent 30%),
            radial-gradient(circle at 12% 20%, rgba(184, 95, 66, 0.12), transparent 34%),
            linear-gradient(135deg, #1d2147 0%, #111827 58%, #2f1f46 100%);
        box-shadow: 0 26px 72px -42px rgba(15, 23, 42, 0.74);
        color: #ffffff;
        padding: clamp(1.25rem, 3vw, 2rem);
    }

    .studio-os-hero h1 {
        margin-top: 0.8rem;
        max-width: 48rem;
        color: #ffffff;
        font-size: clamp(2rem, 4vw, 3.55rem);
        font-weight: 950;
        letter-spacing: -0.055em;
        line-height: 0.98;
    }

    .studio-os-hero p {
        margin-top: 0.9rem;
        max-width: 46rem;
        color: rgba(255, 255, 255, 0.74);
        font-size: 1rem;
        font-weight: 650;
        line-height: 1.65;
    }

    .studio-os-hero__actions {
        display: flex;
        flex: none;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.65rem;
    }

    .studio-os-kpi-grid {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    }

    .studio-os-kpi {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.88);
        border-radius: 20px;
        background: #ffffff;
        padding: 1rem;
        box-shadow: 0 18px 46px -38px rgba(15, 23, 42, 0.5);
        text-decoration: none;
        transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
    }

    .studio-os-kpi::after {
        content: '';
        position: absolute;
        right: -2.4rem;
        top: -2.4rem;
        width: 6rem;
        height: 6rem;
        border-radius: 9999px;
        background: rgba(37, 99, 235, 0.08);
    }

    .studio-os-kpi--success::after {
        background: rgba(21, 128, 61, 0.12);
    }

    .studio-os-kpi--warning::after {
        background: rgba(199, 154, 59, 0.18);
    }

    .studio-os-kpi--danger::after {
        background: rgba(184, 95, 66, 0.16);
    }

    .studio-os-kpi:hover,
    .studio-os-kpi:focus-visible {
        border-color: rgba(199, 154, 59, 0.52);
        box-shadow: 0 22px 58px -38px rgba(15, 23, 42, 0.64);
        transform: translateY(-2px);
        outline: none;
    }

    .studio-os-kpi span,
    .studio-os-health-card span {
        display: block;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 950;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .studio-os-kpi strong {
        display: block;
        margin-top: 0.55rem;
        color: #0f172a;
        font-size: 1.65rem;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .studio-os-kpi small,
    .studio-os-health-card small {
        display: block;
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 650;
        line-height: 1.45;
    }

    .studio-os-section {
        display: grid;
        gap: 1.1rem;
    }

    .studio-os-section__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .studio-os-operation-grid {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
    }

    .studio-os-operation-card {
        display: grid;
        gap: 0.45rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.08), transparent 32%),
            #ffffff;
        padding: 1rem;
        text-decoration: none;
        transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
    }

    .studio-os-operation-card:hover,
    .studio-os-operation-card:focus-visible {
        border-color: rgba(29, 33, 71, 0.24);
        box-shadow: 0 18px 48px -38px rgba(15, 23, 42, 0.55);
        transform: translateY(-2px);
        outline: none;
    }

    .studio-os-operation-card span {
        width: fit-content;
        border-radius: 9999px;
        background: #f6edd9;
        color: #1d2147;
        padding: 0.28rem 0.58rem;
        font-size: 0.72rem;
        font-weight: 950;
    }

    .studio-os-operation-card strong {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 950;
    }

    .studio-os-operation-card p,
    .studio-os-work-item p {
        color: #64748b;
        font-size: 0.84rem;
        font-weight: 650;
        line-height: 1.55;
    }

    .studio-os-two-column {
        display: grid;
        gap: 1.25rem;
    }

    .studio-os-work-list {
        display: grid;
        gap: 0.75rem;
    }

    .studio-os-work-item {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        background: #ffffff;
        padding: 0.9rem;
        text-decoration: none;
        transition: background 160ms ease, border-color 160ms ease, transform 160ms ease;
    }

    .studio-os-work-item:hover,
    .studio-os-work-item:focus-visible {
        border-color: rgba(199, 154, 59, 0.52);
        background: #fffaf0;
        transform: translateY(-1px);
        outline: none;
    }

    .studio-os-work-item strong {
        display: block;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 950;
    }

    .studio-os-health-grid {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    }

    .studio-os-focus-widget {
        position: relative;
        overflow: hidden;
    }

    .studio-os-focus-widget::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: 0.28rem;
        background: linear-gradient(180deg, #1d2147, #c79a3b);
    }

    .studio-os-focus-grid {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    }

    .studio-os-focus-card {
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.1), transparent 34%),
            #ffffff;
        padding: 1rem;
        box-shadow: 0 16px 44px -38px rgba(15, 23, 42, 0.46);
    }

    .studio-os-focus-card span {
        display: block;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 950;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .studio-os-focus-card strong {
        display: block;
        margin-top: 0.45rem;
        color: #0f172a;
        font-size: clamp(1.65rem, 4vw, 2.4rem);
        font-weight: 950;
        letter-spacing: -0.055em;
        line-height: 1;
    }

    .studio-os-health-grid--four {
        grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    }

    .studio-os-health-card {
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 18px;
        background: #f8fafc;
        padding: 1rem;
    }

    .studio-os-health-card strong {
        display: block;
        margin-top: 0.45rem;
        color: #0f172a;
        font-size: 1.3rem;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    @media (min-width: 1024px) {
        .studio-os-two-column {
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
        }
    }

    @media (max-width: 1023px) {
        .studio-os-hero {
            display: grid;
        }

        .studio-os-hero__actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .studio-topbar__inner {
            align-items: flex-start;
        }

        .studio-notification-placeholder {
            min-height: 2.8rem;
            padding: 0.75rem 0.8rem;
        }

        .studio-command-palette {
            align-items: stretch;
            padding-top: 4vh;
        }

        .studio-command-palette__dialog {
            border-radius: 22px;
        }

        .studio-command-palette__header,
        .studio-command-palette__search {
            padding-inline: 1rem;
        }

        .studio-os-hero,
        .studio-os-section {
            border-radius: 22px;
        }

        .studio-os-kpi-grid,
        .studio-os-operation-grid,
        .studio-os-health-grid,
        .studio-os-focus-grid {
            grid-template-columns: 1fr;
        }

        .studio-os-work-item {
            display: grid;
        }
    }
</style>

<style>
    .studio-order-command,
    .studio-order360 {
        display: grid;
        gap: 1.25rem;
    }

    .studio-command-hero,
    .studio-order360-hero {
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 30%),
            linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92));
    }

    .studio-command-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
        gap: 0.8rem;
    }

    .studio-command-kpi {
        position: relative;
        overflow: hidden;
        min-height: 7rem;
        border: 1px solid var(--studio-border);
        border-radius: 18px;
        background: #ffffff;
        padding: 1rem;
        box-shadow: 0 18px 48px -40px rgba(15, 23, 42, 0.62);
    }

    .studio-command-kpi::after {
        content: '';
        position: absolute;
        inset: auto 0 0;
        height: 3px;
        background: #94a3b8;
    }

    .studio-command-kpi--success::after {
        background: var(--studio-success);
    }

    .studio-command-kpi--warning::after {
        background: var(--studio-warning);
    }

    .studio-command-kpi--danger::after {
        background: var(--studio-danger);
    }

    .studio-command-kpi--info::after {
        background: var(--studio-info);
    }

    .studio-command-kpi__label {
        color: var(--studio-muted);
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .studio-command-kpi__value {
        margin-top: 0.8rem;
        color: #020617;
        font-size: clamp(1.55rem, 3vw, 2.25rem);
        font-weight: 950;
        line-height: 1;
        letter-spacing: -0.04em;
    }

    .studio-status-tabs {
        display: flex;
        gap: 0.55rem;
        overflow-x: auto;
        padding-bottom: 0.15rem;
    }

    .studio-status-tab {
        display: inline-flex;
        min-height: 2.75rem;
        flex: 0 0 auto;
        align-items: center;
        gap: 0.55rem;
        border: 1px solid var(--studio-border);
        border-radius: 9999px;
        background: #ffffff;
        padding: 0.6rem 0.9rem;
        color: #475569;
        font-size: 0.85rem;
        font-weight: 850;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-status-tab span {
        display: inline-flex;
        min-width: 1.55rem;
        justify-content: center;
        border-radius: 9999px;
        background: #f1f5f9;
        padding: 0.18rem 0.45rem;
        color: #0f172a;
        font-size: 0.72rem;
    }

    .studio-status-tab.is-active {
        border-color: #0f172a;
        background: #0f172a;
        color: #ffffff;
    }

    .studio-status-tab.is-active span {
        background: rgba(255, 255, 255, 0.14);
        color: #ffffff;
    }

    .studio-status-tab--readonly {
        opacity: 0.72;
    }

    .studio-command-filter {
        background: rgba(255, 255, 255, 0.9);
    }

    .studio-field-label {
        display: block;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .studio-command-button {
        display: inline-flex;
        min-height: 2.8rem;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        border: 1px solid var(--studio-border);
        border-radius: 14px;
        background: #ffffff;
        padding: 0.7rem 1rem;
        color: #334155;
        font-size: 0.9rem;
        font-weight: 850;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-command-button:hover,
    .studio-command-button:focus-visible {
        border-color: var(--studio-border-strong);
        background: #f8fafc;
        color: #020617;
    }

    .studio-command-button--primary {
        border-color: #0f172a;
        background: #0f172a;
        color: #ffffff;
    }

    .studio-command-button--primary:hover,
    .studio-command-button--primary:focus-visible {
        background: #1e293b;
        color: #ffffff;
    }

    .studio-command-table {
        min-width: 84rem;
        border-collapse: separate;
        border-spacing: 0;
    }

    .studio-command-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: rgba(248, 250, 252, 0.98);
        padding: 1rem;
        color: #475569;
        text-align: left;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .studio-command-table td {
        border-top: 1px solid var(--studio-border);
        padding: 1rem;
        vertical-align: top;
    }

    .studio-command-table tbody tr:nth-child(even) {
        background: #fbfdff;
    }

    .studio-command-table tbody tr:hover {
        background: #f8fafc;
    }

    .studio-command-customer,
    .studio-command-product,
    .studio-quick-panel__header {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .studio-command-avatar {
        display: inline-flex;
        width: 2.5rem;
        height: 2.5rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: linear-gradient(135deg, #0f172a, #334155);
        color: #ffffff;
        font-size: 0.8rem;
        font-weight: 950;
        letter-spacing: 0.02em;
        box-shadow: 0 14px 32px -24px rgba(15, 23, 42, 0.78);
    }

    .studio-command-product__image {
        display: inline-flex;
        width: 3rem;
        height: 3rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid var(--studio-border);
        border-radius: 14px;
        background: #f8fafc;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 900;
    }

    .studio-command-product__image img,
    .studio-order-product-card__image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .studio-mini-action,
    .studio-icon-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--studio-border);
        border-radius: 9999px;
        background: #ffffff;
        color: #334155;
        font-size: 0.72rem;
        font-weight: 900;
        line-height: 1;
    }

    .studio-mini-action {
        min-height: 1.65rem;
        padding: 0.35rem 0.55rem;
    }

    .studio-icon-button {
        width: 2rem;
        height: 2rem;
        cursor: pointer;
    }

    .studio-command-popover,
    .studio-action-menu {
        position: relative;
        display: inline-flex;
    }

    .studio-command-popover > summary,
    .studio-action-menu > summary {
        list-style: none;
    }

    .studio-command-popover > summary::-webkit-details-marker,
    .studio-action-menu > summary::-webkit-details-marker {
        display: none;
    }

    .studio-command-popover__panel,
    .studio-action-menu__panel {
        position: absolute;
        right: 0;
        top: calc(100% + 0.5rem);
        z-index: 25;
        width: min(22rem, 82vw);
        border: 1px solid var(--studio-border);
        border-radius: 18px;
        background: #ffffff;
        padding: 1rem;
        text-align: left;
        box-shadow: 0 24px 70px -38px rgba(15, 23, 42, 0.42);
    }

    .studio-command-popover__panel--wide {
        width: min(30rem, 88vw);
    }

    .studio-action-menu__panel {
        display: grid;
        gap: 0.25rem;
        width: 14rem;
        padding: 0.5rem;
    }

    .studio-action-menu__panel a,
    .studio-action-menu__panel button {
        width: 100%;
        border-radius: 12px;
        padding: 0.65rem 0.75rem;
        text-align: left;
        color: #334155;
        font-size: 0.86rem;
        font-weight: 800;
    }

    .studio-action-menu__panel a:hover,
    .studio-action-menu__panel button:hover {
        background: #f8fafc;
        color: #020617;
    }

    .studio-quick-panel__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .studio-quick-panel__grid div {
        border-radius: 14px;
        background: #f8fafc;
        padding: 0.75rem;
    }

    .studio-quick-panel__grid dt {
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .studio-quick-panel__grid dd {
        margin-top: 0.35rem;
        color: #0f172a;
        font-size: 0.92rem;
        font-weight: 900;
    }

    .studio-order360-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
    }

    .studio-order360-card,
    .studio-intel-card {
        border-radius: 18px;
        box-shadow: 0 18px 48px -40px rgba(15, 23, 42, 0.56);
    }

    .studio-intel-card {
        border: 1px solid var(--studio-border);
        background: #ffffff;
        padding: 1.5rem;
    }

    .studio-intel-card--risk {
        background:
            radial-gradient(circle at top right, rgba(220, 38, 38, 0.08), transparent 34%),
            #ffffff;
    }

    .studio-intel-card--courier {
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 34%),
            #ffffff;
    }

    .studio-command-timeline {
        display: grid;
        gap: 0.75rem;
    }

    .studio-command-timeline__step {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border: 1px solid var(--studio-border);
        border-radius: 16px;
        background: #ffffff;
        padding: 0.9rem;
    }

    .studio-command-timeline__dot {
        display: inline-flex;
        width: 0.9rem;
        height: 0.9rem;
        flex: 0 0 auto;
        border: 2px solid #cbd5e1;
        border-radius: 9999px;
        background: #ffffff;
    }

    .studio-command-timeline__step.is-complete .studio-command-timeline__dot,
    .studio-command-timeline__step.is-active .studio-command-timeline__dot {
        border-color: var(--studio-success);
        background: var(--studio-success);
    }

    .studio-command-timeline__step.is-active {
        border-color: rgba(37, 99, 235, 0.34);
        background: #eff6ff;
    }

    .studio-order-product-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid var(--studio-border);
        border-radius: 18px;
        background: #ffffff;
        padding: 1rem;
    }

    .studio-order-product-card__image {
        display: inline-flex;
        width: 4.5rem;
        height: 4.5rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 16px;
        background: #f8fafc;
        color: #64748b;
        font-weight: 900;
    }

    .studio-finance-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-radius: 14px;
        background: #f8fafc;
        padding: 0.8rem 0.9rem;
    }

    .studio-finance-row span {
        color: #64748b;
        font-weight: 700;
    }

    .studio-finance-row strong {
        color: #0f172a;
        font-weight: 950;
    }

    .studio-finance-row--total {
        background: #0f172a;
    }

    .studio-finance-row--total span,
    .studio-finance-row--total strong {
        color: #ffffff;
    }

    .studio-mobile-order-actions {
        position: sticky;
        bottom: 0;
        z-index: 20;
        display: none;
        gap: 0.6rem;
        border-top: 1px solid rgba(226, 232, 240, 0.92);
        background: rgba(255, 255, 255, 0.92);
        padding: 0.75rem;
        backdrop-filter: blur(16px);
    }

    @media (min-width: 1024px) {
        .studio-command-timeline {
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .studio-command-kpi-grid,
        .studio-metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .studio-command-table {
            min-width: 0;
        }

        .studio-command-table thead {
            display: none;
        }

        .studio-command-table,
        .studio-command-table tbody,
        .studio-command-table tr,
        .studio-command-table td {
            display: block;
            width: 100%;
        }

        .studio-command-table tr {
            margin: 0.85rem;
            overflow: visible;
            border: 1px solid var(--studio-border);
            border-radius: 18px;
            background: #ffffff;
            box-shadow: var(--studio-shadow-soft);
        }

        .studio-command-table td {
            display: grid;
            grid-template-columns: 7.5rem minmax(0, 1fr);
            gap: 0.8rem;
            border-top: 1px solid #f1f5f9;
            padding: 0.85rem;
        }

        .studio-command-table td:first-child {
            border-top: 0;
        }

        .studio-command-table td::before {
            content: attr(data-label);
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .studio-order360-actions,
        .studio-mobile-order-actions {
            display: flex;
        }

        .studio-mobile-order-actions .studio-command-button {
            flex: 1;
        }

        .studio-order-product-card {
            align-items: flex-start;
        }

        .studio-command-popover__panel,
        .studio-action-menu__panel {
            position: fixed;
            inset: auto 1rem 1rem;
            width: auto;
        }
    }
</style>

<style>
    /* Phase 40A handmade luxury brand foundation */
    :root {
        --brand-indigo: #1d2147;
        --brand-indigo-strong: #111536;
        --brand-gold: #c79a3b;
        --brand-gold-soft: #f3e3b8;
        --brand-terracotta: #b85f42;
        --brand-ivory: #fbf7ec;
        --brand-ivory-deep: #f3ead8;
        --brand-charcoal: #27221f;
        --brand-muted: #786e62;
        --studio-bg: var(--brand-ivory);
        --studio-surface: #fffdf7;
        --studio-surface-soft: #f8efe0;
        --studio-border: #eadcc6;
        --studio-border-strong: #d8c29d;
        --studio-text: var(--brand-charcoal);
        --studio-muted: var(--brand-muted);
        --studio-primary: var(--brand-indigo);
        --studio-accent: var(--brand-gold);
        --studio-radius: 18px;
        --studio-radius-lg: 24px;
        --studio-shadow: 0 24px 62px -42px rgba(29, 33, 71, 0.38);
        --studio-shadow-soft: 0 14px 38px -30px rgba(39, 34, 31, 0.28);
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(199, 154, 59, 0.14), transparent 28%),
            linear-gradient(180deg, #fbf7ec 0%, #f6eddd 100%);
        color: var(--brand-charcoal);
        font-feature-settings: "kern";
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background-image:
            linear-gradient(90deg, rgba(184, 95, 66, 0.055) 50%, transparent 50%),
            linear-gradient(rgba(199, 154, 59, 0.05) 50%, transparent 50%);
        background-size: 18px 1px, 1px 18px;
        opacity: 0.42;
        mask-image: linear-gradient(180deg, #000, transparent 70%);
    }

    .studio-sidebar {
        background:
            linear-gradient(180deg, rgba(17, 21, 54, 0.98), rgba(29, 33, 71, 0.98)),
            radial-gradient(circle at top left, rgba(199, 154, 59, 0.22), transparent 30%);
        border-right-color: rgba(199, 154, 59, 0.18) !important;
    }

    .studio-sidebar__brand {
        border-bottom-color: rgba(199, 154, 59, 0.18);
    }

    .studio-brand-mark,
    .studio-profile-avatar {
        background: linear-gradient(135deg, var(--brand-gold), #f2d78c);
        color: var(--brand-indigo-strong);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.45), 0 18px 36px -22px rgba(0, 0, 0, 0.42);
    }

    .studio-nav-group > summary {
        color: rgba(243, 227, 184, 0.72);
    }

    .studio-nav-link {
        color: rgba(255, 253, 247, 0.82);
    }

    .studio-nav-link:hover {
        background: rgba(199, 154, 59, 0.12);
        color: #fffdf7;
    }

    .studio-nav-link.is-active,
    .studio-nav-link[aria-current="page"] {
        background: linear-gradient(135deg, #fffdf7, #f4e6c9);
        color: var(--brand-indigo-strong);
        box-shadow: 0 18px 42px -26px rgba(0, 0, 0, 0.46);
    }

    .studio-nav-link__icon {
        background: rgba(243, 227, 184, 0.12);
    }

    .studio-topbar {
        border-bottom-color: rgba(216, 194, 157, 0.86);
        background: rgba(255, 253, 247, 0.84);
    }

    .studio-page-title,
    .studio-kpi-value,
    .studio-status-widget__value,
    .studio-chart-placeholder__title,
    .studio-dropdown__name,
    .studio-form-section__title {
        color: var(--brand-charcoal);
        letter-spacing: -0.035em;
    }

    .studio-page-subtitle,
    .studio-kpi-sub,
    .studio-status-widget__meta,
    .studio-form-section__subtitle,
    .studio-breadcrumbs,
    .studio-chart-placeholder__label {
        color: var(--brand-muted);
    }

    .studio-card,
    .studio-panel,
    .studio-form-panel,
    .studio-stat-card,
    .studio-summary-card,
    .studio-module-card,
    .studio-metric-card,
    .studio-form-section,
    .studio-page-hero,
    .studio-status-widget,
    .studio-filter-panel,
    .studio-action-bar,
    .studio-table-shell,
    main .overflow-x-auto {
        border-color: var(--studio-border) !important;
        background: rgba(255, 253, 247, 0.94) !important;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-card::before,
    .studio-form-section::before,
    .studio-page-hero::before {
        content: '';
        display: block;
        height: 1px;
        margin: -0.15rem 0 0.9rem;
        background-image: repeating-linear-gradient(90deg, rgba(199, 154, 59, 0.55) 0 7px, transparent 7px 14px);
        opacity: 0.56;
    }

    .studio-page-hero {
        background:
            linear-gradient(135deg, rgba(255, 253, 247, 0.96), rgba(246, 237, 221, 0.96)),
            radial-gradient(circle at top right, rgba(184, 95, 66, 0.12), transparent 36%) !important;
    }

    .studio-action-btn,
    main button[type="submit"],
    main a.rounded-xl.bg-slate-950,
    .studio-dropdown__action {
        border-radius: 9999px !important;
        background: linear-gradient(135deg, var(--brand-indigo), var(--brand-indigo-strong)) !important;
        color: #fffdf7 !important;
        box-shadow: 0 18px 38px -28px rgba(29, 33, 71, 0.58);
    }

    .studio-action-btn:not(.studio-action-btn--primary),
    main a.rounded-xl.border,
    main button.rounded-xl.border {
        border-color: var(--studio-border-strong) !important;
        background: rgba(255, 253, 247, 0.9) !important;
        color: var(--brand-indigo) !important;
    }

    .studio-badge {
        border: 1px solid rgba(199, 154, 59, 0.28);
        background: rgba(243, 227, 184, 0.42);
        color: var(--brand-indigo);
        letter-spacing: 0.04em;
    }

    .studio-badge--success {
        border-color: #b8d8a8;
        background: #eef7e5;
        color: #355f2f;
    }

    .studio-badge--warning {
        border-color: #ead7a7;
        background: #fff2ce;
        color: #7a5614;
    }

    .studio-badge--danger {
        border-color: #e7b6aa;
        background: #fae5df;
        color: #8a3928;
    }

    .studio-badge--info {
        border-color: #c4c6df;
        background: #eceefc;
        color: var(--brand-indigo);
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    main select,
    main textarea,
    .studio-command-bar {
        border-color: var(--studio-border-strong) !important;
        background: #fffdf7 !important;
        color: var(--brand-charcoal) !important;
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
    main select:focus,
    main textarea:focus,
    .studio-command-bar:focus-within {
        border-color: var(--brand-gold) !important;
        box-shadow: 0 0 0 4px rgba(199, 154, 59, 0.18) !important;
    }

    main thead th {
        background: rgba(246, 237, 221, 0.98);
        color: var(--brand-muted);
    }

    main tbody tr:nth-child(even) {
        background: rgba(251, 247, 236, 0.7);
    }

    main tbody tr:hover {
        background: rgba(243, 227, 184, 0.32);
    }

    .studio-chart-placeholder {
        border-color: rgba(199, 154, 59, 0.45);
        background:
            linear-gradient(180deg, rgba(255, 253, 247, 0.96), rgba(248, 239, 224, 0.98)),
            radial-gradient(circle at top right, rgba(184, 95, 66, 0.12), transparent 36%);
    }

    .studio-chart-placeholder__bar {
        background: linear-gradient(90deg, var(--brand-indigo), rgba(29, 33, 71, 0.46));
    }

    .studio-chart-placeholder__bar:nth-child(2n) {
        background: linear-gradient(90deg, var(--brand-gold), rgba(199, 154, 59, 0.36));
    }

    .studio-chart-placeholder__bar:nth-child(3n) {
        background: linear-gradient(90deg, var(--brand-terracotta), rgba(184, 95, 66, 0.3));
    }

    {{--
        Phase 3F-fix: #studio-loading-shell/.studio-loading-brand/
        .studio-loading-mark are each used exactly once in the whole app
        (the one full-screen "Preparing the craft table" overlay in
        layouts/studio.blade.php) — safe to edit directly rather than add
        a competing override, since nothing else on any page shares these
        selectors. Was cream/ivory with navy text (var(--brand-indigo)) —
        a storefront-era look that never got carried over to the deep-ink
        + gold admin theme in any of the earlier dark-mode passes, because
        this overlay is normally only visible for a brief instant on a
        real page navigation and had never been looked at directly.
    --}}
    #studio-loading-shell {
        background:
            radial-gradient(circle at top left, rgba(212, 180, 131, 0.16), transparent 30%),
            rgba(13, 15, 19, 0.94);
        backdrop-filter: blur(16px);
    }

    .studio-loading-brand {
        display: grid;
        justify-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        color: #e9ebf0;
        text-align: center;
    }

    .studio-loading-mark {
        width: 4rem;
        height: 4rem;
        border-radius: 9999px;
        border: 1px solid rgba(212, 180, 131, 0.4);
        background:
            radial-gradient(circle, rgba(212, 180, 131, 0.22), transparent 56%),
            #171b26;
        box-shadow: 0 20px 50px -20px rgba(0, 0, 0, 0.7);
        position: relative;
    }

    .studio-loading-mark::before {
        content: '';
        position: absolute;
        inset: 1rem;
        border-top: 2px dashed #d08770;
        border-bottom: 2px dashed #d4b483;
        transform: rotate(-18deg);
    }

    .studio-skeleton-card,
    .studio-skeleton {
        background: linear-gradient(90deg, #fffaf0, #f0e2c7, #fffaf0);
    }
</style>

<style>
    /* Phase 39.5 premium studio overrides */
    :root {
        --studio-bg: #f4f7fb;
        --studio-surface: #ffffff;
        --studio-surface-soft: #f8fafc;
        --studio-border: #dbe2ea;
        --studio-border-strong: #c6d0dc;
        --studio-text: #0f172a;
        --studio-muted: #64748b;
        --studio-primary: #0f172a;
        --studio-accent: #2563eb;
        --studio-success: #15803d;
        --studio-warning: #b45309;
        --studio-danger: #b91c1c;
        --studio-info: #0369a1;
        --studio-radius: 16px;
        --studio-radius-lg: 20px;
        --studio-shadow: 0 18px 48px -36px rgba(15, 23, 42, 0.42);
        --studio-shadow-soft: 0 8px 24px -20px rgba(15, 23, 42, 0.3);
    }

    .studio-shell {
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 30%),
            linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
    }

    .studio-sidebar {
        box-shadow: 12px 0 36px -24px rgba(15, 23, 42, 0.5);
    }

    .studio-sidebar__brand {
        background: linear-gradient(180deg, rgba(2, 6, 23, 0.94), rgba(15, 23, 42, 1));
    }

    .studio-sidebar .space-y-4 {
        padding-bottom: 0.5rem;
    }

    .studio-nav-group {
        border-radius: 18px;
        margin: 0 0.5rem;
        background: rgba(15, 23, 42, 0.18);
        border: 1px solid rgba(148, 163, 184, 0.08);
    }

    .studio-nav-group > summary {
        padding-inline: 1rem;
        color: #cbd5e1;
    }

    .studio-nav-group > summary span {
        letter-spacing: 0.18em;
    }

    .studio-nav-link {
        border: 1px solid transparent;
    }

    .studio-nav-link.is-active,
    .studio-nav-link[aria-current="page"] {
        position: relative;
        border-color: rgba(15, 23, 42, 0.08);
    }

    .studio-nav-link.is-active::before,
    .studio-nav-link[aria-current="page"]::before {
        content: '';
        position: absolute;
        left: 0.35rem;
        top: 50%;
        width: 0.35rem;
        height: 0.9rem;
        border-radius: 9999px;
        background: #0f172a;
        transform: translateY(-50%);
    }

    .studio-topbar {
        border-bottom: 1px solid rgba(226, 232, 240, 0.92);
        background: rgba(255, 255, 255, 0.78);
        box-shadow: 0 10px 30px -28px rgba(15, 23, 42, 0.28);
    }

    .studio-topbar__inner {
        min-height: 5.75rem;
    }

    .studio-command-bar {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        width: 100%;
        max-width: 38rem;
        border-radius: 9999px;
        border: 1px solid rgba(203, 213, 225, 0.88);
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 12px 30px -26px rgba(15, 23, 42, 0.4);
        padding: 0.85rem 1rem 0.85rem 1.1rem;
    }

    .studio-command-bar svg {
        width: 1rem;
        height: 1rem;
        color: #94a3b8;
        flex: none;
    }

    .studio-command-bar input {
        width: 100%;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        padding: 0 !important;
        font-size: 0.95rem;
    }

    .studio-command-bar input::placeholder {
        color: #94a3b8;
        font-weight: 600;
    }

    .studio-command-bar__hint {
        flex: none;
        border-radius: 9999px;
        background: #eef2f7;
        color: #475569;
        padding: 0.35rem 0.6rem;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.12em;
    }

    .studio-notification-placeholder {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        border: 1px solid rgba(203, 213, 225, 0.88);
        background: rgba(255, 255, 255, 0.92);
        padding: 0.85rem 1rem;
        color: #334155;
        font-size: 0.875rem;
        font-weight: 800;
        box-shadow: 0 12px 30px -26px rgba(15, 23, 42, 0.36);
    }

    .studio-notification-placeholder svg {
        color: #64748b;
    }

    .studio-profile-menu > summary {
        padding: 0.6rem 0.75rem;
        border-color: rgba(203, 213, 225, 0.88);
    }

    .studio-profile-avatar {
        width: 2.8rem;
        height: 2.8rem;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12), 0 14px 28px -18px rgba(15, 23, 42, 0.75);
    }

    .studio-dropdown {
        width: 20rem;
        border-radius: 22px;
        border-color: rgba(203, 213, 225, 0.96);
        padding: 1rem;
    }

    .studio-dropdown__action {
        border-radius: 16px;
        background: linear-gradient(135deg, #0f172a, #334155);
        box-shadow: var(--studio-shadow);
    }

    .studio-card,
    .studio-panel,
    .studio-form-panel,
    .studio-stat-card,
    .studio-summary-card,
    .studio-module-card,
    .studio-metric-card,
    .studio-form-section,
    .studio-page-hero {
        border-radius: 18px !important;
    }

    .studio-page-grid {
        gap: 1.25rem;
    }

    .studio-filter-panel,
    .studio-action-bar {
        border-radius: 18px;
    }

    .studio-dashboard-grid,
    .studio-dashboard-grid--wide {
        display: grid;
        gap: 1rem;
    }

    .studio-dashboard-grid {
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    }

    .studio-dashboard-grid--wide {
        grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));
    }

    .studio-chart-placeholder {
        min-height: 16rem;
        border-radius: 18px;
        border: 1px dashed var(--studio-border-strong);
        background:
            linear-gradient(180deg, rgba(255,255,255,0.92), rgba(248,250,252,0.98)),
            radial-gradient(circle at top right, rgba(37,99,235,0.08), transparent 35%);
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-chart-placeholder__label {
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--studio-muted);
    }

    .studio-chart-placeholder__title {
        margin-top: 0.55rem;
        font-size: 1rem;
        font-weight: 800;
        color: #020617;
    }

    .studio-chart-placeholder__body {
        margin-top: 1rem;
        display: grid;
        gap: 0.75rem;
    }

    .studio-chart-placeholder__bar {
        height: 0.8rem;
        border-radius: 9999px;
        background: linear-gradient(90deg, #cbd5e1, #94a3b8);
    }

    .studio-chart-placeholder__bar:nth-child(2n) {
        background: linear-gradient(90deg, #dbeafe, #60a5fa);
    }

    .studio-chart-placeholder__bar:nth-child(3n) {
        background: linear-gradient(90deg, #dcfce7, #22c55e);
    }

    .studio-status-widgets {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    }

    .studio-status-widget {
        border-radius: 18px;
        border: 1px solid var(--studio-border);
        background: #ffffff;
        box-shadow: var(--studio-shadow-soft);
        padding: 1rem;
    }

    .studio-status-widget__label {
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--studio-muted);
    }

    .studio-status-widget__value {
        margin-top: 0.45rem;
        font-size: 1.6rem;
        font-weight: 850;
        letter-spacing: -0.04em;
        color: #020617;
    }

    .studio-status-widget__meta {
        margin-top: 0.35rem;
        font-size: 0.82rem;
        color: var(--studio-muted);
    }

    .studio-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .studio-toolbar__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    .studio-badge {
        border-radius: 9999px !important;
        font-weight: 800;
    }

    .studio-skeleton-card {
        border-radius: 18px;
    }

    .studio-empty-state {
        max-width: 26rem;
    }

    main .overflow-x-auto,
    .studio-responsive-scroll,
    .studio-table-shell {
        border-radius: 18px;
        border: 1px solid var(--studio-border);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: var(--studio-shadow-soft);
    }

    main table {
        border-spacing: 0;
    }

    main thead th {
        background: rgba(248, 250, 252, 0.96);
        backdrop-filter: blur(8px);
    }

    main tbody tr:hover {
        background: #f3f7ff;
    }

    .studio-mobile-stack table td {
        border-bottom: 1px solid var(--studio-border);
    }
</style>

<style>
    .studio-customer-command,
    .studio-customer360 {
        display: grid;
        gap: 1.25rem;
    }

    .studio-customer360 {
        scroll-behavior: smooth;
    }

    .studio-crm-hero,
    .studio-command-hero {
        overflow: visible;
    }

    .studio-crm-profile-heading {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        gap: 1rem;
    }

    .studio-crm-avatar {
        display: inline-flex;
        width: 4.4rem;
        height: 4.4rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.24), transparent 34%),
            linear-gradient(135deg, var(--brand-indigo, #1d2147), #334155);
        color: #ffffff;
        font-size: 1.25rem;
        font-weight: 950;
        letter-spacing: 0.02em;
        box-shadow: 0 24px 64px -36px rgba(15, 23, 42, 0.7);
    }

    .studio-crm-score-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
        gap: 0.9rem;
    }

    .studio-crm-card {
        border: 1px solid var(--studio-border);
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.06), transparent 30%),
            #ffffff;
        box-shadow: 0 18px 48px -40px rgba(15, 23, 42, 0.58);
    }

    .studio-crm-table {
        min-width: 92rem;
    }

    .studio-crm-badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .studio-crm-lifecycle {
        display: grid;
        gap: 0.8rem;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    }

    .studio-crm-lifecycle__step {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border: 1px solid var(--studio-border);
        border-radius: 18px;
        background: #ffffff;
        padding: 1rem;
        color: #64748b;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-crm-lifecycle__step span {
        display: inline-flex;
        width: 0.9rem;
        height: 0.9rem;
        flex: 0 0 auto;
        border: 2px solid #cbd5e1;
        border-radius: 9999px;
        background: #ffffff;
        box-shadow: 0 0 0 5px rgba(226, 232, 240, 0.6);
    }

    .studio-crm-lifecycle__step strong {
        color: inherit;
        font-size: 0.85rem;
        font-weight: 900;
    }

    .studio-crm-lifecycle__step.is-active {
        border-color: rgba(199, 154, 59, 0.58);
        background: #fffaf0;
        color: #0f172a;
    }

    .studio-crm-lifecycle__step.is-active span {
        border-color: var(--brand-gold, #c79a3b);
        background: var(--brand-gold, #c79a3b);
        box-shadow: 0 0 0 5px rgba(199, 154, 59, 0.18);
    }

    .studio-crm-nav {
        position: sticky;
        top: 1rem;
        z-index: 12;
        display: flex;
        gap: 0.55rem;
        overflow-x: auto;
        border: 1px solid var(--studio-border);
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.86);
        padding: 0.45rem;
        box-shadow: var(--studio-shadow-soft);
        backdrop-filter: blur(16px);
    }

    .studio-crm-nav a {
        display: inline-flex;
        min-height: 2.35rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        padding: 0.55rem 0.8rem;
        color: #475569;
        font-size: 0.82rem;
        font-weight: 900;
    }

    .studio-crm-nav a:hover,
    .studio-crm-nav a:focus-visible {
        background: #0f172a;
        color: #ffffff;
    }

    .studio-crm-two-column {
        display: grid;
        gap: 1.25rem;
    }

    .studio-crm-mini-card,
    .studio-crm-panel,
    .studio-crm-insight-grid > div {
        border: 1px solid var(--studio-border);
        border-radius: 16px;
        background: #f8fafc;
        padding: 1rem;
    }

    .studio-crm-mini-card span,
    .studio-crm-panel h3,
    .studio-crm-insight-grid span {
        display: block;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .studio-crm-mini-card strong,
    .studio-crm-panel strong,
    .studio-crm-insight-grid strong {
        display: block;
        margin-top: 0.5rem;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 950;
    }

    .studio-crm-panel strong {
        font-size: 2rem;
        letter-spacing: -0.05em;
    }

    .studio-crm-panel p {
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.86rem;
        font-weight: 650;
    }

    .studio-crm-insight-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        gap: 0.85rem;
    }

    .studio-crm-feed-card {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        border: 1px solid var(--studio-border);
        border-radius: 16px;
        background: #ffffff;
        padding: 1rem;
        box-shadow: var(--studio-shadow-soft);
    }

    .studio-crm-feed-card strong {
        display: block;
        margin-top: 0.55rem;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 950;
    }

    .studio-crm-feed-card p {
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.86rem;
        line-height: 1.55;
    }

    .studio-crm-feed-card time {
        flex: 0 0 auto;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-align: right;
        text-transform: uppercase;
    }

    .studio-command-popover__panel,
    .studio-action-menu__panel {
        color: #0f172a;
    }

    @media (min-width: 1024px) {
        .studio-crm-two-column {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }
    }

    @media (max-width: 767px) {
        .studio-crm-profile-heading {
            display: grid;
        }

        .studio-crm-avatar {
            width: 3.7rem;
            height: 3.7rem;
            border-radius: 18px;
        }

        .studio-crm-score-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .studio-crm-feed-card {
            display: grid;
        }

        .studio-crm-feed-card time {
            text-align: left;
        }
    }
</style>

<style>
    /* Phase 2C admin re-tokenization. This block previously pinned the
       ivory palette with !important on body.studio-shell — since it sits
       later in the cascade than the old slate/ivory layers above but
       earlier than Phase 68A's dark body rule below (which does NOT use
       !important), the ivory !important silently beat Phase 68A's dark
       background on every page. That was the root cause of the light
       "main content area" behind an otherwise-dark sidebar/cards. Now
       carries the actual spec §2.2 deep-ink + gold tokens instead, so
       every component reading var(--studio-*) gets the dark values by
       default — not a selector-scoped override. Storefront is untouched:
       these are Studio-only --studio-* tokens, never the shared
       --zc-ds-color-ivory-* tokens partials/global-design-system.blade.php
       also feeds to the storefront. */
    :root {
        --studio-bg: #0d0f13;
        --studio-surface: #131620;
        --studio-surface-soft: #171b26;
        --studio-border: rgba(212, 180, 131, 0.12);
        --studio-border-strong: rgba(212, 180, 131, 0.24);
        --studio-text: #e9ebf0;
        --studio-muted: #a3a9ba;
        --studio-primary: #e9ebf0;
        --studio-accent: #d4b483;
        --studio-success: #5fa578;
        --studio-warning: #d4b483;
        --studio-danger: #d08770;
        --studio-info: #7aa2c9;
        --studio-radius: var(--zc-ds-radius-md);
        --studio-radius-lg: var(--zc-ds-radius-xl);
        --studio-shadow: 0 24px 60px -36px rgba(0, 0, 0, 0.6);
        --studio-shadow-soft: 0 12px 32px -22px rgba(0, 0, 0, 0.5);
    }

    body.studio-shell {
        background:
            radial-gradient(circle at top left, rgba(212, 180, 131, 0.1), transparent 28rem),
            radial-gradient(circle at 85% 12%, rgba(122, 162, 201, 0.06), transparent 24rem),
            linear-gradient(180deg, #0d0f13 0%, #101319 46%, #0d0f13 100%) !important;
        color: #e9ebf0;
    }

    .studio-sidebar {
        background:
            radial-gradient(circle at 18% 4%, rgba(199, 154, 59, 0.22), transparent 15rem),
            linear-gradient(180deg, #111536 0%, var(--zc-ds-color-indigo-950) 48%, #141833 100%) !important;
        border-right-color: rgba(199, 154, 59, 0.22) !important;
        box-shadow: 18px 0 52px -36px rgba(17, 21, 54, 0.9) !important;
    }

    .studio-sidebar::before {
        content: '';
        display: block;
        height: 3px;
        width: 100%;
        background-image: repeating-linear-gradient(90deg, var(--zc-ds-color-gold-600) 0 9px, transparent 9px 18px);
        opacity: 0.82;
    }

    .studio-sidebar__brand {
        border-bottom-color: rgba(199, 154, 59, 0.2) !important;
        background:
            linear-gradient(180deg, rgba(255, 253, 247, 0.055), rgba(255, 253, 247, 0)),
            transparent !important;
    }

    .studio-brand-mark,
    .studio-profile-avatar {
        background:
            radial-gradient(circle at 30% 25%, rgba(255, 253, 247, 0.55), transparent 30%),
            linear-gradient(135deg, #f1d78a, var(--zc-ds-color-gold-600) 58%, #9f6f1d) !important;
        color: #10142f !important;
        box-shadow:
            inset 0 0 0 1px rgba(255, 253, 247, 0.46),
            0 18px 34px -22px rgba(0, 0, 0, 0.55) !important;
    }

    .studio-nav-group {
        margin-inline: 0.45rem;
        border: 1px solid rgba(255, 253, 247, 0.08) !important;
        border-radius: var(--zc-ds-radius-lg) !important;
        background: rgba(255, 253, 247, 0.045) !important;
    }

    .studio-nav-group > summary {
        min-height: 2.55rem;
        color: rgba(243, 227, 184, 0.82) !important;
        font-size: 0.72rem;
        font-weight: 950;
        letter-spacing: 0.16em;
    }

    .studio-nav-link {
        min-height: 2.75rem;
        border: 1px solid transparent !important;
        border-radius: 14px !important;
        color: rgba(255, 253, 247, 0.84) !important;
        font-weight: 760;
    }

    .studio-nav-link__icon {
        background: rgba(255, 253, 247, 0.08) !important;
        color: rgba(243, 227, 184, 0.92) !important;
    }

    .studio-nav-link:hover,
    .studio-nav-link:focus-visible {
        border-color: rgba(199, 154, 59, 0.28) !important;
        background: rgba(199, 154, 59, 0.13) !important;
        color: #fffdf7 !important;
        transform: translateX(2px);
    }

    .studio-nav-link.is-active,
    .studio-nav-link[aria-current="page"] {
        border-color: rgba(199, 154, 59, 0.35) !important;
        background: linear-gradient(135deg, #fffdf7 0%, #f6eddc 100%) !important;
        color: #111536 !important;
        box-shadow: 0 22px 44px -30px rgba(0, 0, 0, 0.62) !important;
    }

    .studio-nav-link.is-active .studio-nav-link__icon,
    .studio-nav-link[aria-current="page"] .studio-nav-link__icon {
        background: rgba(199, 154, 59, 0.22) !important;
        color: #111536 !important;
    }

    .studio-topbar {
        position: sticky;
        top: 0;
        z-index: var(--zc-ds-z-sticky);
        border-bottom: 1px solid rgba(212, 198, 170, 0.82) !important;
        background: rgba(255, 253, 247, 0.86) !important;
        box-shadow: 0 18px 42px -36px rgba(29, 33, 71, 0.35) !important;
        backdrop-filter: blur(22px);
    }

    .studio-topbar__inner {
        max-width: 96rem !important;
        min-height: 5.7rem;
    }

    .studio-page-title,
    .studio-section-title,
    .studio-form-section__title,
    .studio-dropdown__name,
    .studio-command-palette__title {
        color: var(--zc-ds-color-charcoal-950) !important;
        letter-spacing: var(--zc-ds-letter-normal) !important;
    }

    .studio-page-subtitle,
    .studio-section-subtitle,
    .studio-form-section__subtitle,
    .studio-dropdown__meta,
    .studio-breadcrumbs {
        color: var(--zc-ds-color-muted) !important;
    }

    .studio-command-bar,
    .studio-notification-placeholder,
    .studio-profile-menu > summary,
    .studio-menu-toggle > summary,
    .studio-dropdown,
    .studio-command-palette__dialog,
    .studio-bulk-modal__dialog,
    .studio-command-popover__panel,
    .studio-action-menu__panel {
        border-color: rgba(212, 198, 170, 0.9) !important;
        background: rgba(255, 253, 247, 0.96) !important;
        color: var(--zc-ds-color-indigo-950) !important;
        box-shadow: var(--zc-ds-shadow-md) !important;
    }

    .studio-dropdown,
    .studio-command-palette__dialog,
    .studio-bulk-modal__dialog,
    .studio-command-popover__panel,
    .studio-action-menu__panel {
        border-radius: var(--zc-ds-radius-xl) !important;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.1), transparent 15rem),
            rgba(255, 253, 247, 0.98) !important;
        backdrop-filter: blur(18px);
    }

    .studio-command-palette__backdrop,
    .studio-bulk-modal__backdrop {
        background: rgba(17, 21, 54, 0.46) !important;
        backdrop-filter: blur(8px);
    }

    .studio-command-palette__search,
    .studio-command-palette__item,
    .studio-notification-item,
    .studio-productivity-card,
    .studio-command-token {
        border-color: var(--zc-ds-color-border) !important;
        background: rgba(255, 253, 247, 0.92) !important;
    }

    .studio-command-palette__item:hover,
    .studio-command-palette__item:focus-visible,
    .studio-notification-item:hover,
    .studio-productivity-card:hover,
    .studio-command-token:hover {
        border-color: rgba(199, 154, 59, 0.62) !important;
        background: #fffaf0 !important;
        transform: translateY(-1px);
    }

    .studio-card,
    .studio-panel,
    .studio-form-panel,
    .studio-form-section,
    .studio-page-hero,
    .studio-filter-panel,
    .studio-action-bar,
    .studio-table-shell,
    .studio-summary-card,
    .studio-module-card,
    .studio-stat-card,
    .studio-metric-card,
    .studio-status-widget,
    .studio-command-kpi,
    .studio-os-section,
    .studio-os-focus-widget,
    .studio-crm-card,
    .studio-crm-panel,
    .studio-crm-mini-card,
    .studio-timeline__card,
    .studio-order360-card,
    main .overflow-x-auto {
        border-color: var(--zc-ds-color-border) !important;
        border-radius: var(--zc-ds-radius-xl) !important;
        background:
            linear-gradient(180deg, rgba(255, 253, 247, 0.98), rgba(255, 250, 240, 0.94)) !important;
        box-shadow: var(--zc-ds-shadow-sm) !important;
    }

    .studio-card:hover,
    .studio-panel:hover,
    .studio-metric-card:hover,
    .studio-stat-card:hover,
    .studio-command-kpi:hover,
    .studio-crm-card:hover,
    .studio-order360-card:hover {
        border-color: rgba(199, 154, 59, 0.45) !important;
        box-shadow: var(--zc-ds-shadow-md) !important;
        transform: translateY(-1px);
    }

    .studio-page-hero,
    .studio-command-hero,
    .studio-os-hero,
    .studio-crm-hero,
    .studio-order360-hero {
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.2), transparent 22rem),
            radial-gradient(circle at 12% 90%, rgba(184, 95, 66, 0.12), transparent 20rem),
            linear-gradient(135deg, #fffdf7 0%, #f8f0de 100%) !important;
    }

    .studio-command-button,
    .studio-action-btn,
    .studio-dropdown__action,
    main button[type="submit"],
    main a.rounded-xl.bg-slate-950 {
        border-color: rgba(17, 21, 54, 0.1) !important;
        border-radius: var(--zc-ds-radius-pill) !important;
        background: linear-gradient(135deg, var(--zc-ds-color-indigo-950), var(--zc-ds-color-indigo-900)) !important;
        color: var(--zc-ds-color-ivory-50) !important;
        box-shadow: 0 18px 36px -28px rgba(29, 33, 71, 0.62) !important;
    }

    .studio-command-button:not(.studio-command-button--primary),
    .studio-action-btn:not(.studio-action-btn--primary),
    .studio-mini-action,
    .studio-icon-button,
    main a.rounded-xl.border,
    main button.rounded-xl.border {
        border-color: var(--zc-ds-color-border-strong) !important;
        background: rgba(255, 253, 247, 0.96) !important;
        color: var(--zc-ds-color-indigo-950) !important;
    }

    .studio-command-button:hover,
    .studio-action-btn:hover,
    .studio-mini-action:hover,
    .studio-icon-button:hover,
    main button[type="submit"]:hover,
    main a.rounded-xl:hover {
        border-color: rgba(199, 154, 59, 0.72) !important;
        box-shadow: var(--zc-ds-shadow-md) !important;
        transform: translateY(-1px);
    }

    .studio-command-button:disabled,
    .studio-action-btn:disabled,
    main button:disabled {
        cursor: not-allowed;
        opacity: var(--zc-ds-opacity-disabled);
        transform: none !important;
    }

    .studio-badge {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-height: 1.85rem;
        border-radius: var(--zc-ds-radius-pill) !important;
        font-size: var(--zc-ds-font-size-badge);
        font-weight: 900;
        letter-spacing: 0.04em;
        padding: 0.42rem 0.72rem;
        text-transform: uppercase;
    }

    .studio-badge--neutral {
        border-color: var(--zc-ds-color-border-strong) !important;
        background: rgba(246, 237, 220, 0.78) !important;
        color: var(--zc-ds-color-indigo-950) !important;
    }

    .studio-badge--success {
        border-color: #bddfbd !important;
        background: var(--zc-ds-color-success-soft) !important;
        color: var(--zc-ds-color-success) !important;
    }

    .studio-badge--warning {
        border-color: #edd99d !important;
        background: var(--zc-ds-color-warning-soft) !important;
        color: var(--zc-ds-color-warning) !important;
    }

    .studio-badge--danger {
        border-color: #e7b6aa !important;
        background: var(--zc-ds-color-danger-soft) !important;
        color: var(--zc-ds-color-danger) !important;
    }

    .studio-badge--info {
        border-color: #c4d0ec !important;
        background: var(--zc-ds-color-info-soft) !important;
        color: var(--zc-ds-color-info) !important;
    }

    .studio-status-tabs {
        gap: 0.55rem;
    }

    .studio-status-tab {
        min-height: 2.55rem;
        border: 1px solid var(--zc-ds-color-border) !important;
        border-radius: var(--zc-ds-radius-pill) !important;
        background: rgba(255, 253, 247, 0.9) !important;
        color: var(--zc-ds-color-indigo-950) !important;
        font-weight: 850;
    }

    .studio-status-tab.is-active {
        border-color: rgba(199, 154, 59, 0.62) !important;
        background: linear-gradient(135deg, var(--zc-ds-color-indigo-950), var(--zc-ds-color-indigo-900)) !important;
        color: var(--zc-ds-color-ivory-50) !important;
    }

    .studio-table-shell,
    .studio-responsive-scroll,
    main .overflow-x-auto {
        overflow-x: auto;
        scrollbar-color: rgba(199, 154, 59, 0.6) rgba(246, 237, 220, 0.82);
    }

    main table,
    .studio-command-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        color: var(--zc-ds-color-charcoal-950);
        font-size: var(--zc-ds-font-size-table);
    }

    main thead th,
    .studio-command-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        border-bottom: 1px solid var(--zc-ds-color-border-strong) !important;
        background: linear-gradient(180deg, rgba(255, 250, 240, 0.98), rgba(246, 237, 220, 0.96)) !important;
        color: var(--zc-ds-color-muted) !important;
        font-size: 0.72rem;
        font-weight: 950;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    main tbody td,
    .studio-command-table td {
        border-top: 1px solid rgba(229, 222, 208, 0.72) !important;
        color: var(--zc-ds-color-charcoal-950);
        vertical-align: top;
    }

    main tbody tr,
    .studio-command-table tbody tr {
        transition:
            background var(--zc-ds-transition-base),
            box-shadow var(--zc-ds-transition-base),
            transform var(--zc-ds-transition-base);
    }

    main tbody tr:nth-child(even),
    .studio-command-table tbody tr:nth-child(even) {
        background: rgba(255, 250, 240, 0.46) !important;
    }

    main tbody tr:hover,
    .studio-command-table tbody tr:hover {
        background: rgba(243, 227, 184, 0.28) !important;
    }

    .studio-field-label,
    .studio-form-label,
    main label {
        color: var(--zc-ds-color-indigo-950);
        font-weight: 850;
        letter-spacing: var(--zc-ds-letter-normal);
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    main select,
    main textarea,
    .studio-form-control {
        min-height: 2.8rem;
        padding: 0.65rem 0.9rem;
        border-color: var(--zc-ds-color-border-strong) !important;
        border-radius: var(--zc-ds-radius-sm) !important;
        background: rgba(255, 253, 247, 0.98) !important;
        color: var(--zc-ds-color-charcoal-950) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    main textarea,
    textarea.studio-form-control {
        min-height: 6.5rem;
        line-height: 1.55;
        padding: 0.75rem 0.9rem;
    }

    main input::placeholder,
    main textarea::placeholder {
        color: var(--zc-ds-color-subtle);
    }

    main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
    main select:focus,
    main textarea:focus,
    .studio-form-control:focus {
        border-color: var(--zc-ds-color-gold-600) !important;
        box-shadow: var(--zc-ds-shadow-focus) !important;
    }

    main input[type="checkbox"],
    main input[type="radio"] {
        accent-color: var(--zc-ds-color-indigo-950);
    }

    .studio-callout,
    .studio-command-palette__empty,
    .studio-empty-state {
        border: 1px solid rgba(199, 154, 59, 0.26) !important;
        border-radius: var(--zc-ds-radius-xl) !important;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.12), transparent 14rem),
            rgba(255, 253, 247, 0.96) !important;
        color: var(--zc-ds-color-indigo-950) !important;
        box-shadow: var(--zc-ds-shadow-sm);
    }

    .studio-callout--danger {
        border-color: rgba(159, 61, 46, 0.28) !important;
        background: var(--zc-ds-color-danger-soft) !important;
        color: var(--zc-ds-color-danger) !important;
    }

    .studio-empty-state__icon {
        border: 1px solid rgba(199, 154, 59, 0.34);
        background: rgba(243, 227, 184, 0.42);
        color: var(--zc-ds-color-indigo-950);
    }

    .studio-timeline,
    .studio-command-timeline {
        gap: 0.9rem;
    }

    .studio-timeline__dot,
    .studio-command-timeline__dot {
        border: 2px solid var(--zc-ds-color-gold-600) !important;
        background: var(--zc-ds-color-indigo-950) !important;
        box-shadow: 0 0 0 5px rgba(199, 154, 59, 0.18);
    }

    .studio-order-product-card,
    .studio-crm-feed-card,
    .studio-notification-item,
    .studio-command-customer,
    .studio-command-product {
        border-radius: var(--zc-ds-radius-lg);
    }

    .studio-order-product-card {
        border: 1px solid var(--zc-ds-color-border);
        background: rgba(255, 253, 247, 0.9);
        box-shadow: var(--zc-ds-shadow-xs);
    }

    .studio-command-avatar,
    .studio-crm-avatar,
    .studio-command-product__image,
    .studio-order-product-card__image {
        background:
            radial-gradient(circle at 35% 25%, rgba(255, 253, 247, 0.32), transparent 34%),
            linear-gradient(135deg, var(--zc-ds-color-indigo-950), #343963) !important;
        color: var(--zc-ds-color-ivory-50) !important;
        box-shadow: 0 18px 36px -26px rgba(29, 33, 71, 0.55);
    }

    .studio-mini-action,
    .studio-icon-button,
    .studio-notification-item__toggle {
        min-height: 2.25rem;
        border-radius: var(--zc-ds-radius-pill) !important;
        font-weight: 850;
    }

    .studio-mini-action:hover,
    .studio-icon-button:hover,
    .studio-notification-item__toggle:hover {
        border-color: rgba(199, 154, 59, 0.62) !important;
        background: rgba(243, 227, 184, 0.42) !important;
    }

    .studio-skeleton,
    .studio-skeleton-card {
        background: linear-gradient(90deg, #fffaf0, #f1e4c8, #fffaf0) !important;
        background-size: 220% 100%;
    }

    body.studio-shell :is(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
        outline: 2px solid var(--zc-ds-color-gold-600) !important;
        outline-offset: 3px;
        box-shadow: var(--zc-ds-shadow-focus) !important;
    }

    @media (prefers-reduced-motion: no-preference) {
        .studio-card,
        .studio-panel,
        .studio-command-kpi,
        .studio-productivity-card,
        .studio-command-button,
        .studio-action-btn,
        .studio-nav-link,
        .studio-status-tab,
        .studio-command-palette__item {
            transition:
                transform var(--zc-ds-transition-base),
                border-color var(--zc-ds-transition-base),
                background var(--zc-ds-transition-base),
                box-shadow var(--zc-ds-transition-base),
                color var(--zc-ds-transition-base);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        body.studio-shell *,
        body.studio-shell *::before,
        body.studio-shell *::after {
            animation-duration: 1ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: 1ms !important;
        }
    }

    @media (max-width: 1023px) {
        .studio-page-hero__meta,
        .studio-os-hero,
        .studio-order360-actions,
        .studio-inline-actions {
            align-items: stretch;
            display: grid;
        }

        .studio-command-button,
        .studio-action-btn {
            justify-content: center;
            width: 100%;
        }
    }

    @media (max-width: 767px) {
        .studio-topbar__inner {
            gap: 0.75rem;
            min-height: auto;
        }

        .studio-page-title {
            font-size: 1.35rem;
        }

        .studio-page-subtitle {
            display: none;
        }

        .studio-status-tabs,
        .studio-command-token-list {
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: 0.15rem;
        }
    }

    /*
     * Phase 65D final Studio polish.
     * This layer intentionally normalizes older Blade pages without changing
     * their routes, forms, actions, or data contracts.
     */
    .studio-65d-page,
    .studio-65d-command-center,
    main > .container.mx-auto,
    main > .mx-auto.max-w-7xl {
        display: grid;
        gap: var(--zc-ds-space-6);
        max-width: 92rem;
        margin-inline: auto;
        padding-inline: clamp(1rem, 2vw, 1.75rem) !important;
        padding-block: clamp(1rem, 2vw, 1.75rem) !important;
    }

    main > .container.mx-auto > :is(.mb-8, .flex.justify-between.items-center.mb-8):first-child,
    main > .mx-auto.max-w-7xl > .mb-8:first-child,
    .studio-65d-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(199, 154, 59, 0.22);
        border-radius: var(--zc-ds-radius-xl);
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.16), transparent 15rem),
            linear-gradient(135deg, rgba(255, 253, 247, 0.98), rgba(246, 237, 220, 0.78));
        box-shadow: var(--zc-ds-shadow-sm);
        padding: clamp(1.25rem, 3vw, 2rem);
    }

    main > .container.mx-auto > :is(.mb-8, .flex.justify-between.items-center.mb-8):first-child h1,
    main > .mx-auto.max-w-7xl > .mb-8:first-child h1,
    .studio-65d-hero h1,
    .studio-65d-hero-title {
        color: var(--zc-ds-color-indigo-950);
        font-size: clamp(1.65rem, 2.4vw, 2.35rem);
        font-weight: 950;
        letter-spacing: var(--zc-ds-letter-tight);
        line-height: 1.05;
    }

    .studio-65d-command-center .studio-page-hero,
    .studio-65d-command-center .studio-action-bar {
        position: relative;
        overflow: hidden;
    }

    .studio-65d-command-center .studio-page-hero::after,
    .studio-65d-command-center .studio-action-bar::after {
        content: "";
        position: absolute;
        inset: auto -4rem -5.5rem auto;
        width: 15rem;
        height: 15rem;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(199, 154, 59, 0.16), transparent 68%);
        pointer-events: none;
    }

    .studio-65d-command-center .studio-command-kpi,
    .studio-65d-command-center .studio-metric-card,
    .studio-65d-command-center .studio-stat-card,
    .studio-65d-card {
        isolation: isolate;
        position: relative;
        overflow: hidden;
    }

    .studio-65d-command-center .studio-command-kpi:hover,
    .studio-65d-command-center .studio-metric-card:hover,
    .studio-65d-command-center .studio-stat-card:hover,
    .studio-65d-card:hover {
        transform: translateY(-2px);
        border-color: rgba(199, 154, 59, 0.42) !important;
        box-shadow: var(--zc-ds-shadow-md);
    }

    .studio-65d-table-card,
    .studio-65d-media-grid {
        overflow: hidden;
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-xl);
        background: rgba(255, 253, 247, 0.95);
        box-shadow: var(--zc-ds-shadow-sm);
    }

    .studio-65d-table-card .overflow-x-auto,
    .studio-65d-table-card .studio-responsive-scroll {
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .studio-65d-card-grid {
        display: grid;
        gap: var(--zc-ds-space-4);
    }

    @media (min-width: 640px) {
        .studio-65d-card-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .studio-65d-card-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .studio-65d-mini-stat {
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-lg);
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.12), transparent 9rem),
            rgba(255, 253, 247, 0.94);
        box-shadow: var(--zc-ds-shadow-xs);
        padding: 1rem;
    }

    .studio-65d-mini-stat span {
        display: block;
        color: var(--zc-ds-color-muted);
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }

    .studio-65d-mini-stat strong {
        display: block;
        margin-top: 0.45rem;
        color: var(--zc-ds-color-indigo-950);
        font-size: clamp(1.45rem, 2vw, 2rem);
        font-weight: 950;
        letter-spacing: var(--zc-ds-letter-tight);
    }

    .studio-65d-link,
    main a.text-blue-600,
    main button.text-red-600,
    main button.text-blue-600 {
        display: inline-flex;
        min-height: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: var(--zc-ds-radius-pill);
        color: var(--zc-ds-color-indigo-950) !important;
        font-size: 0.78rem;
        font-weight: 900;
        letter-spacing: 0.03em;
        text-decoration: none !important;
    }

    main a.text-blue-600:hover,
    main button.text-red-600:hover,
    main button.text-blue-600:hover,
    .studio-65d-link:hover {
        /* Was --zc-ds-color-gold-700 (3.59-3.84:1 — fails WCAG AA 4.5:1
           as text). Gold stays the brand accent for borders/fills/
           dividers; it doesn't get used as a text color anymore.
           terracotta-700 is an existing design-system token, visually
           distinct from the resting indigo-950 state (so hover is still
           a clear signal), and passes at 5.59:1 (ivory-100) / 5.98:1
           (white) — see docs/accessibility-contrast-audit.md. */
        color: var(--zc-ds-color-terracotta-700) !important;
    }

    main :is(button, a).bg-blue-600,
    main :is(button, a).bg-slate-800,
    main :is(button, a).bg-gray-600 {
        border-radius: var(--zc-ds-radius-pill) !important;
        background: linear-gradient(135deg, var(--zc-ds-color-indigo-950), var(--zc-ds-color-indigo-900)) !important;
        box-shadow: var(--zc-ds-shadow-sm);
        font-weight: 900;
    }

    .studio-65d-file-preview {
        display: inline-flex;
        width: 4rem;
        height: 4rem;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-md);
        background: rgba(246, 237, 220, 0.5);
        box-shadow: var(--zc-ds-shadow-xs);
    }

    .studio-65d-file-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .studio-65d-empty-cell {
        padding: 2rem !important;
    }

    .studio-65d-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.55rem;
    }

    @media (max-width: 767px) {
        main > .container.mx-auto > :is(.mb-8, .flex.justify-between.items-center.mb-8):first-child,
        main > .mx-auto.max-w-7xl > .mb-8:first-child,
        .studio-65d-hero {
            display: grid;
            gap: 1rem;
        }

        .studio-65d-actions,
        .studio-65d-actions > *,
        main > .container.mx-auto > :first-child :is(a, button, form),
        main > .mx-auto.max-w-7xl > :first-child :is(a, button, form) {
            width: 100%;
        }

        .studio-65d-actions :is(a, button),
        main > .container.mx-auto > :first-child :is(a, button),
        main > .mx-auto.max-w-7xl > :first-child :is(a, button) {
            justify-content: center;
        }
    }

    /*
     * Phase 68A reference shell rebuild.
     * Scoped to the Studio shell hooks added in layouts/studio.blade.php and
     * sidebar-navigation.blade.php. This is intentionally structural UI polish,
     * not a global utility override or business workflow change.
     */
    body.studio-reference-layout {
        --studio-reference-bg: #0d0f13;
        --studio-reference-rail: #0a0c11;
        --studio-reference-panel: rgba(19, 22, 32, 0.94);
        --studio-reference-panel-strong: rgba(23, 27, 38, 0.96);
        --studio-reference-panel-soft: rgba(23, 27, 38, 0.72);
        --studio-reference-border: rgba(212, 180, 131, 0.12);
        --studio-reference-border-gold: rgba(212, 180, 131, 0.28);
        --studio-reference-text: #e9ebf0;
        --studio-reference-muted: #a3a9ba;
        --studio-reference-subtle: rgba(163, 169, 186, 0.72);
        --studio-reference-gold: #d4b483;
        --studio-reference-gold-bright: #e6c9a0;
        --studio-reference-maroon: #8f3445;
        --studio-reference-terracotta: #d08770;
        --studio-reference-green: #5fa578;
        --studio-reference-blue: #7aa2c9;
        background:
            radial-gradient(circle at 18% -8%, rgba(212, 180, 131, 0.1), transparent 23rem),
            radial-gradient(circle at 92% 8%, rgba(122, 162, 201, 0.06), transparent 24rem),
            linear-gradient(180deg, #0d0f13 0%, #101319 46%, #0d0f13 100%) !important;
        color: var(--studio-reference-text);
    }

    .studio-reference-shell {
        min-height: 100vh;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
            linear-gradient(45deg, rgba(199, 154, 59, 0.035) 1px, transparent 1px);
        background-size: 26px 26px, 34px 34px;
    }

    .studio-reference-workspace,
    .studio-reference-main {
        position: relative;
    }

    .studio-reference-main {
        max-width: 96rem !important;
    }

    .studio-reference-sidebar {
        border-color: rgba(255, 255, 255, 0.08) !important;
        background:
            radial-gradient(circle at 20% 0%, rgba(199, 154, 59, 0.12), transparent 13rem),
            linear-gradient(180deg, rgba(2, 3, 7, 0.99), rgba(4, 6, 13, 0.99)) !important;
        box-shadow: 24px 0 70px -50px rgba(0, 0, 0, 0.95);
    }

    .studio-reference-sidebar::after {
        content: "";
        position: fixed;
        inset: 0 auto 0 0;
        width: 268px;
        pointer-events: none;
        background-image:
            linear-gradient(135deg, rgba(199, 154, 59, 0.09) 1px, transparent 1px),
            linear-gradient(45deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
        background-size: 18px 18px, 30px 30px;
        mask-image: linear-gradient(180deg, black, rgba(0, 0, 0, 0.34));
    }

    .studio-reference-sidebar .studio-sidebar__brand {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 1rem;
        border-bottom-color: rgba(199, 154, 59, 0.18) !important;
        padding: 1.2rem 1rem 1rem;
    }

    .studio-sidebar__eyebrow {
        color: rgba(199, 154, 59, 0.78);
        font-size: 0.62rem;
        font-weight: 950;
        letter-spacing: 0.18em;
        line-height: 1;
        text-transform: uppercase;
    }

    .studio-reference-sidebar .studio-brand-mark {
        width: 2.65rem;
        height: 2.65rem;
        border: 1px solid rgba(199, 154, 59, 0.45);
        background:
            radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.3), transparent 32%),
            linear-gradient(135deg, #c79a3b, #806020) !important;
        color: #070913 !important;
        box-shadow: 0 18px 38px -26px rgba(199, 154, 59, 0.72);
    }

    .studio-sidebar__status,
    .studio-live-status-pill {
        display: inline-flex;
        width: fit-content;
        align-items: center;
        gap: 0.45rem;
        border: 1px solid rgba(34, 197, 94, 0.24);
        border-radius: 999px;
        background: rgba(34, 197, 94, 0.09);
        color: #86efac;
        font-size: 0.68rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        padding: 0.35rem 0.6rem;
        text-transform: uppercase;
    }

    .studio-sidebar__status span,
    .studio-live-status-pill span {
        width: 0.42rem;
        height: 0.42rem;
        border-radius: 999px;
        background: var(--studio-reference-green);
        box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.13);
    }

    .studio-reference-sidebar .studio-sidebar__nav {
        position: relative;
        z-index: 1;
        padding-top: 0.85rem;
    }

    .studio-reference-nav-group {
        border: 1px solid transparent;
        border-radius: 1rem;
    }

    .studio-reference-nav-group > .studio-nav-group__summary {
        min-height: 2rem;
        padding: 0 0.65rem;
        color: rgba(199, 154, 59, 0.78) !important;
        font-size: 0.65rem;
        font-weight: 950;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .studio-reference-nav-group > .studio-nav-group__summary svg {
        color: rgba(203, 213, 225, 0.38);
    }

    .studio-reference-nav-group[open] > .studio-nav-group__summary svg {
        transform: rotate(90deg);
    }

    .studio-reference-nav-group .studio-nav-group__items {
        padding-inline: 0.25rem !important;
    }

    .studio-reference-layout .studio-nav-subheading {
        margin: 0.55rem 0 0.25rem;
        color: rgba(203, 213, 225, 0.48) !important;
        font-size: 0.62rem;
        letter-spacing: 0.13em;
    }

    .studio-reference-layout .studio-nav-link {
        min-height: 2.38rem;
        border-radius: 0.8rem !important;
        color: rgba(226, 232, 240, 0.78) !important;
        font-size: 0.82rem;
        font-weight: 820;
    }

    .studio-reference-layout .studio-nav-link--child {
        padding-left: 0.75rem;
    }

    .studio-reference-layout .studio-nav-link__icon {
        width: 1.8rem;
        height: 1.8rem;
        background: rgba(255, 255, 255, 0.055) !important;
        color: rgba(199, 154, 59, 0.82) !important;
    }

    .studio-reference-layout .studio-nav-link:hover,
    .studio-reference-layout .studio-nav-link:focus-visible {
        border-color: rgba(199, 154, 59, 0.28) !important;
        background: rgba(199, 154, 59, 0.11) !important;
        color: #fffaf0 !important;
        transform: translateX(3px);
    }

    .studio-reference-layout .studio-nav-link.is-active,
    .studio-reference-layout .studio-nav-link[aria-current="page"] {
        border-color: rgba(199, 154, 59, 0.44) !important;
        background:
            linear-gradient(135deg, rgba(199, 154, 59, 0.21), rgba(17, 24, 39, 0.94)) !important;
        color: #fffaf0 !important;
        box-shadow: 0 16px 38px -30px rgba(199, 154, 59, 0.85) !important;
    }

    .studio-reference-layout .studio-nav-link.is-active .studio-nav-link__icon,
    .studio-reference-layout .studio-nav-link[aria-current="page"] .studio-nav-link__icon {
        background: rgba(199, 154, 59, 0.18) !important;
        color: #f8d889 !important;
    }

    .studio-sidebar__footer {
        position: relative;
        z-index: 1;
        background: linear-gradient(180deg, transparent, rgba(255, 255, 255, 0.025));
    }

    .studio-sidebar__logout {
        border: 1px solid rgba(199, 154, 59, 0.22) !important;
        background: rgba(255, 255, 255, 0.07) !important;
        color: rgba(248, 241, 228, 0.88) !important;
    }

    .studio-reference-layout .studio-topbar {
        border-bottom: 1px solid rgba(148, 163, 184, 0.14) !important;
        background:
            linear-gradient(180deg, rgba(6, 9, 16, 0.94), rgba(8, 11, 20, 0.82)) !important;
        box-shadow: 0 18px 52px -42px rgba(0, 0, 0, 0.92) !important;
        backdrop-filter: blur(22px);
    }

    .studio-reference-topbar__inner {
        min-height: 4.9rem;
    }

    .studio-topbar__titleblock {
        max-width: min(32rem, 40vw);
    }

    .studio-reference-layout .studio-breadcrumbs,
    .studio-reference-layout .studio-page-subtitle {
        color: rgba(203, 213, 225, 0.64) !important;
    }

    .studio-reference-layout .studio-page-title {
        color: var(--studio-reference-text) !important;
        font-size: clamp(1.35rem, 2vw, 1.9rem);
        letter-spacing: -0.02em !important;
    }

    .studio-reference-search,
    .studio-reference-pill-button,
    .studio-profile-summary,
    .studio-reference-menu-toggle {
        border-color: rgba(148, 163, 184, 0.16) !important;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.88), rgba(10, 15, 27, 0.94)) !important;
        color: var(--studio-reference-text) !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.045),
            0 18px 44px -36px rgba(0, 0, 0, 0.9) !important;
    }

    .studio-reference-search {
        min-height: 2.85rem;
        border-radius: 999px !important;
        padding-inline: 1rem;
    }

    .studio-reference-search svg,
    .studio-reference-pill-button svg {
        color: rgba(199, 154, 59, 0.85);
    }

    .studio-reference-search .text-slate-400,
    .studio-profile-summary .text-slate-950,
    .studio-profile-summary .text-slate-500 {
        color: var(--studio-reference-muted) !important;
    }

    .studio-reference-layout .studio-command-bar__hint {
        border-color: rgba(199, 154, 59, 0.28) !important;
        background: rgba(199, 154, 59, 0.13) !important;
        color: #f8d889 !important;
    }

    .studio-reference-layout .studio-notification-count {
        background: var(--studio-reference-terracotta) !important;
        color: #fffaf0 !important;
        box-shadow: 0 0 0 4px rgba(184, 95, 66, 0.14);
    }

    .studio-reference-layout .studio-dropdown,
    .studio-reference-layout .studio-command-palette__dialog,
    .studio-reference-layout .studio-bulk-modal__dialog,
    .studio-reference-layout .studio-command-popover__panel,
    .studio-reference-layout .studio-action-menu__panel {
        border-color: rgba(199, 154, 59, 0.18) !important;
        background:
            radial-gradient(circle at top right, rgba(199, 154, 59, 0.12), transparent 18rem),
            linear-gradient(180deg, rgba(15, 21, 35, 0.98), rgba(8, 12, 22, 0.98)) !important;
        color: var(--studio-reference-text) !important;
        box-shadow: 0 34px 90px -56px rgba(0, 0, 0, 0.96) !important;
    }

    .studio-reference-layout .studio-command-palette__backdrop,
    .studio-reference-layout .studio-bulk-modal__backdrop {
        background: rgba(1, 3, 8, 0.74) !important;
        backdrop-filter: blur(12px);
    }

    .studio-reference-command-dialog {
        max-width: min(48rem, calc(100vw - 2rem));
    }

    .studio-reference-layout .studio-command-palette__search,
    .studio-reference-layout .studio-command-palette__item,
    .studio-reference-layout .studio-notification-item,
    .studio-reference-layout .studio-productivity-card,
    .studio-reference-layout .studio-command-token {
        border-color: rgba(148, 163, 184, 0.14) !important;
        background: rgba(15, 23, 42, 0.72) !important;
        color: var(--studio-reference-text) !important;
    }

    .studio-reference-layout .studio-command-palette__item:hover,
    .studio-reference-layout .studio-command-palette__item:focus-visible,
    .studio-reference-layout .studio-notification-item:hover,
    .studio-reference-layout .studio-productivity-card:hover,
    .studio-reference-layout .studio-command-token:hover {
        border-color: rgba(199, 154, 59, 0.4) !important;
        background: rgba(199, 154, 59, 0.09) !important;
        transform: translateY(-1px);
    }

    .studio-reference-layout .studio-dropdown__label,
    .studio-reference-layout .studio-command-section__title {
        color: rgba(199, 154, 59, 0.82) !important;
    }

    .studio-reference-layout .studio-dropdown__name,
    .studio-reference-layout .studio-command-palette__title,
    .studio-reference-layout .studio-command-palette__item strong,
    .studio-reference-layout .studio-notification-item strong {
        color: var(--studio-reference-text) !important;
    }

    .studio-reference-layout .studio-dropdown__meta,
    .studio-reference-layout .studio-command-palette__header p,
    .studio-reference-layout .studio-command-palette__item span,
    .studio-reference-layout .studio-notification-item span {
        color: var(--studio-reference-muted) !important;
    }

    .studio-reference-layout .studio-card,
    .studio-reference-layout .studio-page-hero,
    .studio-reference-layout .studio-table-shell,
    .studio-reference-layout .studio-command-kpi,
    .studio-reference-layout .studio-os-section,
    .studio-reference-layout .studio-os-focus-widget,
    .studio-reference-layout .studio-crm-card,
    .studio-reference-layout .studio-order360-card,
    .studio-reference-layout .studio-panel,
    .studio-reference-layout .studio-form-section,
    .studio-reference-layout .studio-filter-panel,
    .studio-reference-layout .studio-action-bar,
    .studio-reference-layout .studio-summary-card,
    .studio-reference-layout .studio-module-card,
    .studio-reference-layout .studio-stat-card,
    .studio-reference-layout .studio-metric-card,
    .studio-reference-layout .studio-status-widget {
        border-color: rgba(148, 163, 184, 0.15) !important;
        background:
            radial-gradient(circle at 100% 0%, rgba(199, 154, 59, 0.08), transparent 15rem),
            linear-gradient(180deg, var(--studio-reference-panel-strong), var(--studio-reference-panel)) !important;
        color: var(--studio-reference-text) !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.045),
            0 26px 70px -58px rgba(0, 0, 0, 0.92) !important;
    }

    .studio-reference-layout .studio-page-hero,
    .studio-reference-layout .studio-command-hero,
    .studio-reference-layout .studio-os-hero,
    .studio-reference-layout .studio-crm-hero,
    .studio-reference-layout .studio-order360-hero {
        background:
            radial-gradient(circle at 88% 12%, rgba(199, 154, 59, 0.18), transparent 20rem),
            radial-gradient(circle at 8% 88%, rgba(96, 165, 250, 0.1), transparent 18rem),
            linear-gradient(135deg, rgba(18, 25, 41, 0.98), rgba(8, 12, 22, 0.96)) !important;
    }

    .studio-reference-layout .studio-card:hover,
    .studio-reference-layout .studio-command-kpi:hover,
    .studio-reference-layout .studio-metric-card:hover,
    .studio-reference-layout .studio-stat-card:hover {
        border-color: rgba(199, 154, 59, 0.32) !important;
        transform: translateY(-2px);
    }

    .studio-reference-layout .studio-section-title,
    .studio-reference-layout .studio-form-section__title,
    .studio-reference-layout .studio-card h1,
    .studio-reference-layout .studio-card h2,
    .studio-reference-layout .studio-card h3,
    .studio-reference-layout .studio-card strong,
    .studio-reference-layout .studio-command-kpi__value,
    .studio-reference-layout .studio-metric-card strong {
        color: var(--studio-reference-text) !important;
    }

    .studio-reference-layout .studio-section-subtitle,
    .studio-reference-layout .studio-form-section__subtitle,
    .studio-reference-layout .studio-command-kpi__label,
    .studio-reference-layout .studio-metric-card__label {
        color: var(--studio-reference-muted) !important;
    }

    .studio-reference-layout .studio-command-kpi,
    .studio-reference-layout .studio-metric-card,
    .studio-reference-layout .studio-stat-card {
        position: relative;
        overflow: hidden;
    }

    .studio-reference-layout .studio-command-kpi::after,
    .studio-reference-layout .studio-metric-card::after,
    .studio-reference-layout .studio-stat-card::after {
        content: "";
        position: absolute;
        right: 1rem;
        top: 1rem;
        width: 0.48rem;
        height: 0.48rem;
        border-radius: 999px;
        background: var(--studio-reference-gold);
        box-shadow: 0 0 0 6px rgba(199, 154, 59, 0.12);
    }

    .studio-reference-layout .studio-command-kpi--success::after {
        background: var(--studio-reference-green);
        box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.12);
    }

    .studio-reference-layout .studio-command-kpi--danger::after {
        background: var(--studio-reference-terracotta);
        box-shadow: 0 0 0 6px rgba(184, 95, 66, 0.12);
    }

    .studio-reference-layout .studio-command-kpi--info::after {
        background: var(--studio-reference-blue);
        box-shadow: 0 0 0 6px rgba(96, 165, 250, 0.12);
    }

    .studio-reference-layout .studio-badge {
        border-color: rgba(148, 163, 184, 0.16) !important;
        background: rgba(15, 23, 42, 0.72) !important;
        color: rgba(226, 232, 240, 0.84) !important;
    }

    .studio-reference-layout .studio-badge--success {
        border-color: rgba(34, 197, 94, 0.28) !important;
        background: rgba(34, 197, 94, 0.13) !important;
        color: #86efac !important;
    }

    .studio-reference-layout .studio-badge--warning {
        border-color: rgba(199, 154, 59, 0.34) !important;
        background: rgba(199, 154, 59, 0.13) !important;
        color: #f8d889 !important;
    }

    .studio-reference-layout .studio-badge--danger {
        border-color: rgba(184, 95, 66, 0.34) !important;
        background: rgba(184, 95, 66, 0.13) !important;
        color: #f4b8a4 !important;
    }

    .studio-reference-layout .studio-badge--info {
        border-color: rgba(96, 165, 250, 0.3) !important;
        background: rgba(96, 165, 250, 0.13) !important;
        color: #bfdbfe !important;
    }

    .studio-reference-layout .studio-command-table {
        color: var(--studio-reference-text) !important;
    }

    .studio-reference-layout .studio-command-table th {
        border-bottom-color: rgba(199, 154, 59, 0.16) !important;
        background: linear-gradient(180deg, rgba(19, 26, 42, 0.98), rgba(12, 17, 29, 0.98)) !important;
        color: rgba(203, 213, 225, 0.72) !important;
    }

    .studio-reference-layout .studio-command-table td {
        border-top-color: rgba(148, 163, 184, 0.12) !important;
        color: rgba(241, 245, 249, 0.88) !important;
    }

    .studio-reference-layout .studio-command-table tbody tr:nth-child(even) {
        background: rgba(255, 255, 255, 0.018) !important;
    }

    .studio-reference-layout .studio-command-table tbody tr:hover {
        background: rgba(199, 154, 59, 0.075) !important;
    }

    .studio-reference-layout .studio-form-label,
    .studio-reference-layout .studio-field-label {
        color: rgba(241, 245, 249, 0.9) !important;
    }

    .studio-reference-layout .studio-form-control,
    .studio-reference-layout .studio-command-palette__search input {
        border-color: rgba(148, 163, 184, 0.16) !important;
        background: rgba(6, 10, 19, 0.92) !important;
        color: var(--studio-reference-text) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035) !important;
    }

    .studio-reference-layout .studio-form-control:focus,
    .studio-reference-layout .studio-command-palette__search:focus-within {
        border-color: rgba(199, 154, 59, 0.62) !important;
        box-shadow: 0 0 0 4px rgba(199, 154, 59, 0.14) !important;
    }

    .studio-reference-layout .studio-command-button,
    .studio-reference-layout .studio-action-btn,
    .studio-reference-layout .studio-mini-action,
    .studio-reference-layout .studio-icon-button,
    .studio-reference-layout .studio-dropdown__action {
        border-color: rgba(199, 154, 59, 0.24) !important;
        background: rgba(15, 23, 42, 0.8) !important;
        color: var(--studio-reference-text) !important;
    }

    /*
     * This gradient used to blend gold into rgba(29, 33, 71, .95) — a
     * dark indigo/navy left over from an earlier palette — which read
     * as a purple button rather than the deep-ink + gold system. Pure
     * gold gradient with dark ink text (the button needs its own
     * color here since the shared rule above sets light text, correct
     * for a dark button fill but wrong once the fill itself is gold).
     */
    .studio-reference-layout .studio-command-button--primary,
    .studio-reference-layout .studio-action-btn--primary,
    .studio-reference-layout .studio-dropdown__action {
        background: linear-gradient(135deg, #e6c9a0, #d4b483) !important;
        border-color: transparent !important;
        color: #0d0f13 !important;
        font-weight: 700;
    }

    .studio-reference-layout .studio-command-button--primary:hover,
    .studio-reference-layout .studio-action-btn--primary:hover,
    .studio-reference-layout .studio-dropdown__action:hover {
        background: linear-gradient(135deg, #f0d9b5, #e0bd8f) !important;
    }

    .studio-reference-layout :is(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
        outline: 2px solid rgba(199, 154, 59, 0.9) !important;
        outline-offset: 3px;
        box-shadow: 0 0 0 4px rgba(199, 154, 59, 0.14) !important;
    }

    @media (prefers-reduced-motion: no-preference) {
        .studio-sidebar__status span,
        .studio-live-status-pill span,
        .studio-reference-layout .studio-command-kpi::after,
        .studio-reference-layout .studio-metric-card::after,
        .studio-reference-layout .studio-stat-card::after {
            animation: studio-reference-pulse 2.4s ease-in-out infinite;
        }

        .studio-reference-layout .studio-nav-link,
        .studio-reference-layout .studio-card,
        .studio-reference-layout .studio-command-kpi,
        .studio-reference-layout .studio-command-palette__item,
        .studio-reference-layout .studio-notification-item,
        .studio-reference-search,
        .studio-reference-pill-button {
            transition:
                transform 180ms ease,
                border-color 180ms ease,
                background 180ms ease,
                box-shadow 180ms ease,
                color 180ms ease;
        }
    }

    @keyframes studio-reference-pulse {
        0%, 100% {
            opacity: 0.72;
            transform: scale(0.92);
        }
        50% {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (max-width: 1023px) {
        .studio-reference-topbar__inner {
            min-height: 4.6rem;
        }

        .studio-topbar__titleblock {
            max-width: none;
        }

        .studio-reference-mobile-nav {
            background:
                radial-gradient(circle at top right, rgba(199, 154, 59, 0.12), transparent 14rem),
                linear-gradient(180deg, rgba(15, 21, 35, 0.99), rgba(8, 12, 22, 0.99)) !important;
        }
    }

    @media (max-width: 767px) {
        .studio-reference-main {
            padding-inline: 0.9rem !important;
        }

        .studio-reference-topbar__inner {
            gap: 0.75rem;
        }

        .studio-live-status-pill {
            display: none;
        }

        .studio-reference-command-dialog,
        .studio-bulk-modal__dialog {
            width: calc(100vw - 1rem);
            max-height: calc(100vh - 1rem);
        }
    }
</style>

<style>
    /*
     * Phase 2C: (1) three stray components Phase 68A's dark selector
     * list never reached (neutral badge, empty state, loading skeleton
     * all still painted a light/ivory background), and (2) premium
     * polish for the top command bar per spec §2.2 — search, quick
     * actions, signals, owner chip. True EOF, so it's the final word
     * over every earlier phase.
     */
    .studio-reference-layout .studio-badge--neutral {
        border-color: rgba(212, 180, 131, 0.18) !important;
        background: rgba(212, 180, 131, 0.1) !important;
        color: var(--studio-reference-text) !important;
    }

    .studio-reference-layout .studio-empty-state {
        background: linear-gradient(180deg, rgba(23, 27, 38, 0.6), rgba(19, 22, 32, 0.92)) !important;
        border-color: rgba(212, 180, 131, 0.14) !important;
    }

    .studio-reference-layout .studio-skeleton,
    .studio-reference-layout .studio-skeleton-card {
        background: linear-gradient(90deg, #171b26, #1d2230, #171b26) !important;
    }

    /* Top bar: elevated, distinct surface with a gold hairline, not a
       flat strip blending into the page. */
    .studio-reference-layout .studio-topbar {
        border-bottom: 1px solid rgba(212, 180, 131, 0.12) !important;
        background: linear-gradient(180deg, rgba(19, 22, 32, 0.92), rgba(13, 15, 19, 0.86)) !important;
        box-shadow: 0 18px 46px -38px rgba(0, 0, 0, 0.85) !important;
    }

    /* Search: pill, ink-850 fill, gold hairline that brightens on
       interaction, gold icon, keycap-styled Ctrl K hint. */
    .studio-reference-layout .studio-reference-search {
        border-color: rgba(212, 180, 131, 0.16) !important;
        background: #171b26 !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            0 14px 34px -28px rgba(0, 0, 0, 0.85) !important;
        transition: border-color 160ms ease, box-shadow 160ms ease;
    }

    .studio-reference-layout .studio-reference-search:hover,
    .studio-reference-layout .studio-reference-search:focus-visible {
        border-color: rgba(212, 180, 131, 0.4) !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            0 0 0 3px rgba(212, 180, 131, 0.12) !important;
    }

    .studio-reference-layout .studio-reference-search svg {
        color: #d4b483 !important;
    }

    .studio-reference-layout .studio-reference-search .text-slate-400 {
        color: #a3a9ba !important;
        font-weight: 600 !important;
    }

    .studio-reference-layout .studio-command-bar__hint {
        border: 1px solid rgba(212, 180, 131, 0.3) !important;
        background: rgba(212, 180, 131, 0.12) !important;
        color: #e6c9a0 !important;
        font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace !important;
        font-size: 0.7rem !important;
        box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
    }

    /* Quick Actions / Signals: ghost buttons — gold hairline, gold icon,
       near-transparent fill, glow on hover rather than a solid fill. */
    .studio-reference-layout .studio-reference-pill-button {
        border-color: rgba(212, 180, 131, 0.18) !important;
        background: rgba(212, 180, 131, 0.04) !important;
        box-shadow: none !important;
        transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }

    .studio-reference-layout .studio-reference-pill-button:hover,
    .studio-reference-layout .studio-reference-pill-button:focus-visible {
        border-color: rgba(212, 180, 131, 0.5) !important;
        background: rgba(212, 180, 131, 0.1) !important;
        box-shadow: 0 0 0 4px rgba(212, 180, 131, 0.1) !important;
        transform: translateY(-1px);
    }

    .studio-reference-layout .studio-reference-pill-button svg {
        color: #d4b483 !important;
    }

    /* Signals count badge: a token color with real depth, not a flat
       pill fill. */
    .studio-reference-layout .studio-notification-count {
        background: #d08770 !important;
        color: #0d0f13 !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.35),
            0 0 0 4px rgba(208, 135, 112, 0.16) !important;
    }

    /* Owner chip: gold ring on the avatar, hover lift on the whole
       control — a premium account switcher, not a plain link. */
    .studio-reference-layout .studio-profile-summary {
        transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
    }

    .studio-reference-layout .studio-profile-summary:hover,
    .studio-reference-layout .studio-profile-summary:focus-visible {
        border-color: rgba(212, 180, 131, 0.45) !important;
        transform: translateY(-1px);
        box-shadow: 0 12px 30px -22px rgba(0, 0, 0, 0.7), 0 0 0 3px rgba(212, 180, 131, 0.08) !important;
    }

    .studio-reference-layout .studio-profile-avatar {
        box-shadow:
            0 0 0 2px #0d0f13,
            0 0 0 3px rgba(212, 180, 131, 0.55),
            0 18px 34px -22px rgba(0, 0, 0, 0.55) !important;
    }
</style>

<style>
    /*
     * Phase 2C, part 2: a systematic cross-check of every studio-*
     * class actually rendered on Dashboard/Settings/Orders/Products/
     * Customers against every background rule in this file turned up
     * five more live !important-or-unbeaten light patches Phase 68A's
     * selector list never reached: plain form fields (only
     * .studio-form-control was covered, not bare `main input`/`select`/
     * `textarea`, which most filter/search fields on Orders and
     * Settings actually are), the mobile responsive table-row
     * fallback, dropdown/action-menu item hover states, the quick
     * panel grid, and the status filter tabs used on Orders. True EOF.
     */
    .studio-reference-layout main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    .studio-reference-layout main select,
    .studio-reference-layout main textarea,
    .studio-reference-layout .studio-form-control {
        background: #171b26 !important;
        border-color: rgba(212, 180, 131, 0.16) !important;
        color: #e9ebf0 !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03) !important;
    }

    .studio-reference-layout main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
    .studio-reference-layout main select:focus,
    .studio-reference-layout main textarea:focus,
    .studio-reference-layout .studio-form-control:focus {
        border-color: rgba(212, 180, 131, 0.62) !important;
        box-shadow: 0 0 0 4px rgba(212, 180, 131, 0.14) !important;
    }

    .studio-reference-layout main input::placeholder,
    .studio-reference-layout main textarea::placeholder {
        color: #a3a9ba !important;
    }

    .studio-reference-layout .studio-mobile-stack tbody tr {
        background: var(--studio-surface) !important;
    }

    .studio-reference-layout .studio-action-menu__panel a:hover,
    .studio-reference-layout .studio-action-menu__panel button:hover,
    .studio-reference-layout .studio-command-popover__panel a:hover,
    .studio-reference-layout .studio-command-popover__panel button:hover {
        background: rgba(212, 180, 131, 0.1) !important;
        color: var(--studio-text) !important;
    }

    .studio-reference-layout .studio-quick-panel__grid div {
        background: var(--studio-surface-soft) !important;
        color: var(--studio-text) !important;
    }

    .studio-reference-layout .studio-status-tab {
        background: var(--studio-surface) !important;
        border-color: var(--studio-border) !important;
        color: var(--studio-text) !important;
    }

    .studio-reference-layout .studio-status-tab span {
        background: rgba(212, 180, 131, 0.14) !important;
        color: var(--studio-text) !important;
    }

    .studio-reference-layout .studio-status-tab.is-active {
        border-color: rgba(212, 180, 131, 0.5) !important;
        background: linear-gradient(135deg, rgba(212, 180, 131, 0.24), rgba(19, 22, 32, 0.94)) !important;
        color: var(--studio-text) !important;
    }

    .studio-reference-layout .studio-status-tab.is-active span {
        background: rgba(255, 255, 255, 0.14) !important;
        color: var(--studio-text) !important;
    }

    .studio-reference-layout .studio-command-filter {
        background: var(--studio-surface-soft) !important;
    }

    .studio-reference-layout .studio-command-table tr {
        background: var(--studio-surface) !important;
    }

    .studio-reference-layout .studio-responsive-scroll {
        background: var(--studio-surface) !important;
    }
</style>

<style>
    /*
     * Phase 2D: three specific bugs found on visual review of Phase 2C.
     *
     * 1) Search field wrapped onto ~5 lines. Its label span had no
     *    white-space control and sat in a `flex-1` flex item, which
     *    defaults to `min-width: auto` — it can't shrink below its
     *    content's natural (unwrapped) width, so a long placeholder
     *    forces the item, and the whole button, to wrap and grow tall.
     *    Fixed with a fixed-height pill, min-width:0 + nowrap +
     *    ellipsis on the label, and a shortened placeholder (moved to
     *    the Blade view itself).
     *
     * 2) Owner chip rendered with a white background. An older rule,
     *    `.studio-command-bar, .studio-notification-placeholder,
     *    .studio-profile-menu > summary, ... { background: ivory
     *    !important }`, has specificity (0,1,1) — one class plus the
     *    `summary` element from the child-combinator — which beats the
     *    single-class (0,1,0) `.studio-profile-summary` rule Phase 68A
     *    used, even though Phase 68A's rule is !important and comes
     *    later in the file. Specificity wins over source order when
     *    both sides are !important. Fixed by targeting the same
     *    higher-specificity `.studio-profile-menu > summary` shape
     *    directly, at !important, so it can no longer be re-beaten by
     *    that older rule.
     *
     * 3) Top bar height: purely a consequence of (1) — once the search
     *    field stopped wrapping, the bar's height is governed by
     *    min-height alone. Trimmed slightly for a more standard
     *    command-bar feel.
     */
    .studio-reference-layout .studio-reference-search {
        display: flex !important;
        align-items: center !important;
        height: 2.5rem !important;
        min-height: 2.5rem !important;
        max-height: 2.5rem !important;
        min-width: 280px;
        max-width: 420px;
        width: 100%;
        gap: 0.6rem;
        padding-inline: 0.9rem !important;
        overflow: hidden;
    }

    .studio-reference-layout .studio-reference-search span.flex-1,
    .studio-reference-layout .studio-reference-search .text-slate-400 {
        display: block;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .studio-reference-layout .studio-reference-search svg,
    .studio-reference-layout .studio-reference-search .studio-command-bar__hint {
        flex: none;
    }

    .studio-reference-layout .studio-reference-topbar__inner {
        min-height: 4.25rem !important;
    }

    .studio-reference-layout .studio-profile-summary,
    .studio-reference-layout .studio-profile-menu > summary {
        background: #171b26 !important;
        border: 1px solid rgba(212, 180, 131, 0.16) !important;
        color: #e9ebf0 !important;
        box-shadow: none !important;
    }

    .studio-reference-layout .studio-profile-summary:hover,
    .studio-reference-layout .studio-profile-menu > summary:hover,
    .studio-reference-layout .studio-profile-summary:focus-visible,
    .studio-reference-layout .studio-profile-menu > summary:focus-visible {
        border-color: rgba(212, 180, 131, 0.45) !important;
        transform: translateY(-1px);
        box-shadow: 0 12px 30px -22px rgba(0, 0, 0, 0.7), 0 0 0 3px rgba(212, 180, 131, 0.08) !important;
    }

    .studio-reference-layout .studio-profile-summary .text-slate-950 {
        color: #e9ebf0 !important;
    }

    .studio-reference-layout .studio-profile-summary .text-slate-500 {
        color: #a3a9ba !important;
    }

    .studio-reference-layout .studio-profile-summary svg {
        color: #6d7488 !important;
    }
</style>

<style>
    /*
     * Phase 2E: compact command-bar dropdowns. Replaced the old
     * full-screen "Global Search" modal (backdrop, centered dialog,
     * Pin Page / Pin Search chrome) with a small anchored autocomplete
     * panel under the search field. Quick Actions, Signals, and the
     * owner menu already used native <details>/<summary> dropdowns —
     * they keep that mechanism, this block just makes all four share
     * one visual language: same `.studio-dropdown` panel (Phase 68A's
     * existing ink-850ish + gold-hairline + shadow treatment), same
     * compact row padding/hover, same section-title style.
     */
    .studio-reference-layout .studio-search {
        position: relative;
    }

    .studio-reference-layout .studio-search__input {
        flex: 1;
        min-width: 0;
        background: transparent !important;
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        color: #e9ebf0 !important;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0;
    }

    .studio-reference-layout .studio-search__input::placeholder {
        color: #a3a9ba;
        font-weight: 600;
    }

    /* Guard against the [hidden] attribute being beaten by a class
       `display` rule of equal-or-higher specificity — only switch to
       grid once the element is actually not hidden. */
    .studio-reference-layout .studio-search__dropdown {
        left: 0;
        right: auto;
        top: calc(100% + 0.5rem);
        width: 100%;
        min-width: 320px;
        max-width: 480px;
        max-height: 22rem;
        overflow-y: auto;
        padding: 0.6rem;
        gap: 0.2rem;
    }

    .studio-reference-layout .studio-search__dropdown:not([hidden]) {
        display: grid;
    }

    .studio-reference-layout .studio-dropdown--compact {
        width: 16rem;
        padding: 0.75rem;
    }

    .studio-reference-layout .zc-cmdbar-section-title {
        padding: 0.5rem 0.5rem 0.25rem;
        font-size: 0.64rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6d7488 !important;
    }

    .studio-reference-layout .zc-cmdbar-row,
    .studio-reference-layout .studio-dropdown--compact .studio-command-palette__item {
        padding: 0.55rem 0.65rem !important;
        border-radius: 10px !important;
        border-color: transparent !important;
        background: transparent !important;
        gap: 0.1rem !important;
    }

    .studio-reference-layout .zc-cmdbar-row:hover,
    .studio-reference-layout .zc-cmdbar-row.is-active,
    .studio-reference-layout .zc-cmdbar-row:focus-visible,
    .studio-reference-layout .studio-dropdown--compact .studio-command-palette__item:hover,
    .studio-reference-layout .studio-dropdown--compact .studio-command-palette__item:focus-visible {
        background: #1c2130 !important;
        border-color: transparent !important;
        transform: none !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .studio-reference-layout .zc-cmdbar-row strong,
    .studio-reference-layout .studio-dropdown--compact .studio-command-palette__item strong {
        font-size: 0.84rem !important;
    }

    .studio-reference-layout .zc-cmdbar-row span,
    .studio-reference-layout .studio-dropdown--compact .studio-command-palette__item span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        font-size: 0.72rem !important;
    }

    /* Signals: capped, scrollable list; a small dot toggle instead of
       a "Mark unread" text button. */
    .studio-reference-layout .studio-notification-panel {
        width: 22rem;
    }

    .studio-reference-layout .studio-notification-panel__list {
        max-height: 22rem;
        overflow-y: auto;
        padding-right: 0.15rem;
    }

    .studio-reference-layout .studio-notification-item {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        padding: 0.6rem 0.7rem !important;
    }

    .studio-reference-layout .studio-notification-item a {
        display: grid;
        gap: 0.15rem;
        min-width: 0;
        flex: 1;
        text-decoration: none;
    }

    .studio-reference-layout .studio-notification-item strong {
        font-size: 0.82rem !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .studio-reference-layout .studio-notification-item__toggle {
        flex: none;
        width: 1.4rem;
        height: 1.4rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        font-size: 0.5rem;
        line-height: 1;
        color: rgba(212, 180, 131, 0.32) !important;
        background: transparent !important;
    }

    .studio-reference-layout .studio-notification-item.is-unread .studio-notification-item__toggle {
        color: #d4b483 !important;
    }

    .studio-reference-layout .studio-notification-item__toggle:hover {
        background: rgba(212, 180, 131, 0.12) !important;
    }

    /* Owner chip: rotate the chevron on open, gold-outline chips for
       role/workspace instead of the flat neutral/info badge tokens. */
    .studio-reference-layout .studio-profile-chevron {
        transition: transform 160ms ease;
    }

    .studio-reference-layout .studio-profile-menu[open] .studio-profile-chevron {
        transform: rotate(180deg);
    }

    .studio-reference-layout .studio-badge--gold-outline {
        background: transparent !important;
        border: 1px solid rgba(212, 180, 131, 0.4) !important;
        color: #e6c9a0 !important;
    }
</style>

<style>
    /*
     * Login page fixes (urgent lockout follow-up). The Studio login page
     * was never part of the Phase 2C/2D/2E five-page sweep (Dashboard/
     * Settings/Orders/Products/Customers), so its two light patches
     * survived: .studio-callout still had an older ivory !important
     * override (line ~4009) nothing since overrode, and its email/
     * password <input> fields carry no explicit class at all, which is
     * why *browser autofill* — not this stylesheet — could paint the
     * password field its own light/lavender highlight once a saved
     * credential filled it in; author CSS background-color cannot beat
     * that without the specific -webkit-autofill override below.
     */
    .studio-reference-layout .studio-callout {
        background: var(--studio-surface) !important;
        border: 1px solid var(--studio-border) !important;
        border-left: 3px solid var(--studio-muted) !important;
        color: var(--studio-text) !important;
        box-shadow: none !important;
    }

    .studio-reference-layout .studio-callout--danger {
        border-left-color: #d08770 !important;
        background: rgba(208, 135, 112, 0.09) !important;
        color: #f4b8a4 !important;
    }

    .studio-reference-layout .studio-callout--warning {
        border-left-color: #d4b483 !important;
        background: rgba(212, 180, 131, 0.09) !important;
        color: #e6c9a0 !important;
    }

    .studio-reference-layout .studio-callout--success {
        border-left-color: #5fa578 !important;
        background: rgba(95, 165, 120, 0.09) !important;
        color: #9fd4b0 !important;
    }

    .studio-reference-layout main input[type="checkbox"] {
        accent-color: #d4b483;
    }

    .studio-reference-layout main input:-webkit-autofill,
    .studio-reference-layout main input:-webkit-autofill:hover,
    .studio-reference-layout main input:-webkit-autofill:focus,
    .studio-reference-layout main input:-webkit-autofill:active {
        -webkit-text-fill-color: #e9ebf0 !important;
        -webkit-box-shadow: 0 0 0 1000px #171b26 inset !important;
        box-shadow: 0 0 0 1000px #171b26 inset !important;
        caret-color: #e9ebf0;
        transition: background-color 5000s ease-in-out 0s;
    }

    .studio-reference-layout main input:autofill {
        -webkit-text-fill-color: #e9ebf0;
        box-shadow: 0 0 0 1000px #171b26 inset;
    }
</style>

<style>
    /* ============================================================
       LIGHT WORKSPACE THEME  (2026-07 — user request)
       ------------------------------------------------------------
       The sidebar stays dark; the entire content column (top bar +
       <main>) becomes a light / white canvas. Every rule is scoped
       under .studio-reference-workspace, which wraps the top bar and
       <main> and is a *sibling* of the sidebar — so the sidebar is
       never touched. Both studio variable families are re-pointed to
       light values on the workspace, so var()-based components AND
       the custom .zc-* pages flip automatically; the rest of this
       block re-lights the handful of surfaces that hardcode dark
       colors. Appended at true EOF so it wins every source-order tie.
       ============================================================ */

    /* 1 — re-point both variable families to light for the content subtree */
    .studio-reference-layout .studio-reference-workspace {
        --studio-bg: #eef1f6;
        --studio-surface: #ffffff;
        --studio-surface-soft: #f4f7fb;
        --studio-border: #e3e8ef;
        --studio-border-strong: #cbd5e1;
        --studio-text: #101828;
        --studio-muted: #667085;
        --studio-primary: #101828;
        --studio-accent: #a9793f;

        --studio-reference-bg: #eef1f6;
        --studio-reference-rail: #e9edf3;
        --studio-reference-panel: #ffffff;
        --studio-reference-panel-strong: #ffffff;
        --studio-reference-panel-soft: #f4f7fb;
        --studio-reference-border: #e3e8ef;
        --studio-reference-border-gold: rgba(169, 121, 63, 0.35);
        --studio-reference-text: #101828;
        --studio-reference-muted: #667085;
        --studio-reference-subtle: rgba(102, 112, 133, 0.9);
        --studio-reference-gold: #a9793f;
        --studio-reference-gold-bright: #855d2b;
    }

    /* 2 — light canvas behind the content column */
    .studio-reference-layout .studio-reference-workspace {
        background:
            radial-gradient(circle at 22% -12%, rgba(169, 121, 63, 0.05), transparent 26rem),
            linear-gradient(180deg, #eef1f6 0%, #f4f6fa 42%, #eef1f6 100%) !important;
        color: #101828;
    }

    /* 3 — top bar */
    .studio-reference-workspace .studio-topbar {
        background: rgba(255, 255, 255, 0.86) !important;
        backdrop-filter: saturate(1.2) blur(8px);
        border-bottom: 1px solid #e3e8ef !important;
        box-shadow: 0 10px 30px -26px rgba(16, 24, 40, 0.5) !important;
    }

    /* 4 — headings & muted text */
    .studio-reference-workspace .studio-page-title,
    .studio-reference-workspace .studio-section-title,
    .studio-reference-workspace .studio-form-section__title,
    .studio-reference-workspace .studio-card h1,
    .studio-reference-workspace .studio-card h2,
    .studio-reference-workspace .studio-card h3,
    .studio-reference-workspace .studio-card strong,
    .studio-reference-workspace .studio-dropdown__name,
    .studio-reference-workspace .studio-command-palette__title,
    .studio-reference-workspace .studio-command-kpi__value,
    .studio-reference-workspace .studio-metric-card strong {
        color: #101828 !important;
    }
    .studio-reference-workspace .studio-page-subtitle,
    .studio-reference-workspace .studio-section-subtitle,
    .studio-reference-workspace .studio-form-section__subtitle,
    .studio-reference-workspace .studio-breadcrumbs,
    .studio-reference-workspace .studio-dropdown__meta,
    .studio-reference-workspace .studio-command-kpi__label,
    .studio-reference-workspace .studio-metric-card__label {
        color: #667085 !important;
    }

    /* 5 — surfaces: cards / panels / tables / KPIs */
    .studio-reference-workspace .studio-card,
    .studio-reference-workspace .studio-panel,
    .studio-reference-workspace .studio-form-section,
    .studio-reference-workspace .studio-filter-panel,
    .studio-reference-workspace .studio-action-bar,
    .studio-reference-workspace .studio-table-shell,
    .studio-reference-workspace .studio-summary-card,
    .studio-reference-workspace .studio-module-card,
    .studio-reference-workspace .studio-stat-card,
    .studio-reference-workspace .studio-metric-card,
    .studio-reference-workspace .studio-status-widget,
    .studio-reference-workspace .studio-command-kpi,
    .studio-reference-workspace .studio-crm-card,
    .studio-reference-workspace .studio-order360-card,
    .studio-reference-workspace main .overflow-x-auto {
        background: #ffffff !important;
        border-color: #e3e8ef !important;
        color: #101828 !important;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 12px 32px -28px rgba(16, 24, 40, 0.35) !important;
    }
    .studio-reference-workspace .studio-page-hero,
    .studio-reference-workspace .studio-command-hero,
    .studio-reference-workspace .studio-crm-hero {
        background:
            radial-gradient(circle at 90% 10%, rgba(169, 121, 63, 0.10), transparent 20rem),
            linear-gradient(135deg, #ffffff, #f4f7fb) !important;
        border-color: #e3e8ef !important;
        color: #101828 !important;
    }

    /* 6 — form controls */
    .studio-reference-workspace main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    .studio-reference-workspace main select,
    .studio-reference-workspace main textarea,
    .studio-reference-workspace .studio-form-control {
        background: #ffffff !important;
        border-color: #d3dae5 !important;
        color: #101828 !important;
        box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.04) !important;
    }
    .studio-reference-workspace main input::placeholder,
    .studio-reference-workspace main textarea::placeholder { color: #98a2b3 !important; }
    .studio-reference-workspace main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
    .studio-reference-workspace main select:focus,
    .studio-reference-workspace main textarea:focus,
    .studio-reference-workspace .studio-form-control:focus {
        border-color: #a9793f !important;
        box-shadow: 0 0 0 3px rgba(169, 121, 63, 0.14) !important;
    }
    .studio-reference-workspace main input:-webkit-autofill,
    .studio-reference-workspace main input:-webkit-autofill:hover,
    .studio-reference-workspace main input:-webkit-autofill:focus {
        -webkit-text-fill-color: #101828 !important;
        -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
        box-shadow: 0 0 0 1000px #ffffff inset !important;
        caret-color: #101828;
    }
    .studio-reference-workspace main input[type="checkbox"] { accent-color: #a9793f; }

    /* 7 — top-bar controls: search, quick actions, profile chip */
    .studio-reference-workspace .studio-reference-search,
    .studio-reference-workspace .studio-profile-summary,
    .studio-reference-workspace .studio-profile-menu > summary,
    .studio-reference-workspace .studio-reference-pill-button {
        background: #ffffff !important;
        border-color: #e3e8ef !important;
        color: #101828 !important;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05) !important;
    }
    .studio-reference-workspace .studio-reference-search svg,
    .studio-reference-workspace .studio-reference-pill-button svg { color: #a9793f !important; }
    .studio-reference-workspace .studio-search__input { background: transparent !important; color: #101828 !important; }
    .studio-reference-workspace .studio-search__input::placeholder,
    .studio-reference-workspace .studio-reference-search .text-slate-400 { color: #98a2b3 !important; }
    .studio-reference-workspace .studio-profile-summary .text-slate-950 { color: #101828 !important; }
    .studio-reference-workspace .studio-profile-summary .text-slate-500 { color: #667085 !important; }
    .studio-reference-workspace .studio-profile-summary svg { color: #98a2b3 !important; }
    .studio-reference-workspace .studio-command-bar__hint {
        background: #f4f7fb !important; border-color: #e3e8ef !important; color: #855d2b !important;
        box-shadow: inset 0 -1px 0 rgba(16, 24, 40, 0.06) !important;
    }
    .studio-reference-workspace .studio-reference-search:hover,
    .studio-reference-workspace .studio-reference-search:focus-visible,
    .studio-reference-workspace .studio-reference-pill-button:hover,
    .studio-reference-workspace .studio-reference-pill-button:focus-visible,
    .studio-reference-workspace .studio-profile-summary:hover,
    .studio-reference-workspace .studio-profile-menu > summary:hover {
        border-color: rgba(169, 121, 63, 0.5) !important;
        box-shadow: 0 0 0 3px rgba(169, 121, 63, 0.12) !important;
    }
    .studio-reference-workspace .studio-profile-avatar {
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 3px rgba(169, 121, 63, 0.5) !important;
    }

    /* 8 — dropdowns / palette / action menus / notifications */
    .studio-reference-workspace .studio-dropdown,
    .studio-reference-workspace .studio-command-palette__dialog,
    .studio-reference-workspace .studio-bulk-modal__dialog,
    .studio-reference-workspace .studio-command-popover__panel,
    .studio-reference-workspace .studio-action-menu__panel,
    .studio-reference-workspace .studio-notification-panel {
        background: #ffffff !important;
        border-color: #e3e8ef !important;
        color: #101828 !important;
        box-shadow: 0 24px 60px -30px rgba(16, 24, 40, 0.4) !important;
    }
    .studio-reference-workspace .studio-command-palette__item,
    .studio-reference-workspace .studio-notification-item,
    .studio-reference-workspace .studio-command-token,
    .studio-reference-workspace .studio-productivity-card,
    .studio-reference-workspace .studio-command-palette__search {
        background: #f4f7fb !important; border-color: #e3e8ef !important; color: #101828 !important;
    }
    .studio-reference-workspace .studio-command-palette__item:hover,
    .studio-reference-workspace .studio-notification-item:hover,
    .studio-reference-workspace .studio-command-token:hover,
    .studio-reference-workspace .studio-productivity-card:hover,
    .studio-reference-workspace .zc-cmdbar-row:hover,
    .studio-reference-workspace .zc-cmdbar-row.is-active,
    .studio-reference-workspace .studio-action-menu__panel a:hover,
    .studio-reference-workspace .studio-action-menu__panel button:hover,
    .studio-reference-workspace .studio-command-popover__panel a:hover,
    .studio-reference-workspace .studio-command-popover__panel button:hover,
    .studio-reference-workspace .studio-dropdown--compact .studio-command-palette__item:hover {
        background: rgba(169, 121, 63, 0.10) !important; color: #101828 !important;
    }
    .studio-reference-workspace .zc-cmdbar-section-title,
    .studio-reference-workspace .studio-command-section__title { color: #667085 !important; }

    /* 9 — tables */
    .studio-reference-workspace .studio-responsive-scroll,
    .studio-reference-workspace .studio-command-table tr,
    .studio-reference-workspace .studio-mobile-stack tbody tr,
    .studio-reference-workspace .studio-quick-panel__grid div,
    .studio-reference-workspace .studio-command-filter { background: #ffffff !important; }
    .studio-reference-workspace .studio-command-table th { color: #667085 !important; border-color: #e3e8ef !important; }
    .studio-reference-workspace .studio-command-table td { border-color: #eef1f6 !important; color: #101828 !important; }
    .studio-reference-workspace .studio-command-table tbody tr:nth-child(even) { background: #f8fafc !important; }
    .studio-reference-workspace .studio-command-table tbody tr:hover { background: rgba(169, 121, 63, 0.07) !important; }

    /* 10 — badges */
    .studio-reference-workspace .studio-badge--neutral { background: #f2f4f7 !important; border-color: #e3e8ef !important; color: #475467 !important; }
    .studio-reference-workspace .studio-badge--gold-outline { background: rgba(169, 121, 63, 0.08) !important; border-color: rgba(169, 121, 63, 0.4) !important; color: #855d2b !important; }

    /* 11 — status filter tabs (Orders) */
    .studio-reference-workspace .studio-status-tab { background: #ffffff !important; border-color: #e3e8ef !important; color: #101828 !important; }
    .studio-reference-workspace .studio-status-tab span { background: #f2f4f7 !important; color: #475467 !important; }
    .studio-reference-workspace .studio-status-tab.is-active { border-color: rgba(169, 121, 63, 0.5) !important; background: linear-gradient(135deg, rgba(169, 121, 63, 0.16), #ffffff) !important; color: #101828 !important; }
    .studio-reference-workspace .studio-status-tab.is-active span { background: rgba(169, 121, 63, 0.2) !important; color: #855d2b !important; }

    /* 12 — callouts / empty state / skeleton */
    .studio-reference-workspace .studio-callout { background: #ffffff !important; border-color: #e3e8ef !important; border-left-color: #98a2b3 !important; color: #101828 !important; }
    .studio-reference-workspace .studio-empty-state { background: #f8fafc !important; border-color: #e3e8ef !important; color: #667085 !important; }
    .studio-reference-workspace .studio-skeleton,
    .studio-reference-workspace .studio-skeleton-card { background: linear-gradient(90deg, #eef1f6, #f4f7fb, #eef1f6) !important; }
</style>

<style>
    /* ============================================================
       PREMIUM LIGHT POLISH  (2026-07 — user request)
       ------------------------------------------------------------
       Elevates the light workspace: a richer canvas, animated
       colourful borders, layered depth, button sheen and styled
       scrollbars. Big containers get a slowly-rotating multi-hue
       gradient border; small repeated cards + list rows get a
       cheaper border-colour hue cycle (kept light so it stays
       smooth on modest hardware). Scoped to .studio-reference-
       workspace, so the dark sidebar is never touched. EOF = wins.
       ============================================================ */

    @property --zc-angle { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
    @keyframes zc-spin { to { --zc-angle: 360deg; } }
    @keyframes zc-hue-cycle {
        0%   { border-color: rgba(201, 162, 74, 0.60); }
        20%  { border-color: rgba(107, 143, 214, 0.60); }
        40%  { border-color: rgba(95, 165, 120, 0.60); }
        60%  { border-color: rgba(166, 121, 201, 0.60); }
        80%  { border-color: rgba(217, 138, 154, 0.60); }
        100% { border-color: rgba(201, 162, 74, 0.60); }
    }

    /* Richer premium canvas */
    .studio-reference-layout .studio-reference-workspace {
        --zc-angle: 0deg;
        background:
            radial-gradient(1100px 460px at 10% -10%, rgba(201, 162, 74, 0.07), transparent 60%),
            radial-gradient(1000px 500px at 100% -6%, rgba(107, 143, 214, 0.055), transparent 58%),
            radial-gradient(900px 620px at 52% 118%, rgba(166, 121, 201, 0.045), transparent 62%),
            linear-gradient(180deg, #edf0f6 0%, #f5f7fb 46%, #edf0f6 100%) !important;
    }

    /* Big surfaces — white fill + slowly-rotating multi-hue gradient border + layered depth */
    .studio-reference-workspace .studio-card,
    .studio-reference-workspace .studio-panel,
    .studio-reference-workspace .studio-form-section,
    .studio-reference-workspace .studio-filter-panel,
    .studio-reference-workspace .studio-action-bar,
    .studio-reference-workspace .studio-table-shell,
    .studio-reference-workspace .studio-crm-card,
    .studio-reference-workspace .studio-order360-card,
    .studio-reference-workspace .zc-op-panel,
    .studio-reference-workspace .zc-pf-card {
        border: 1.5px solid transparent !important;
        border-radius: 18px !important;
        background:
            linear-gradient(180deg, #ffffff, #fdfdff) padding-box,
            conic-gradient(from var(--zc-angle), #c9a24a, #6b8fd6, #5fa578, #a679c9, #d98a9a, #c9a24a) border-box !important;
        box-shadow:
            0 1px 2px rgba(16, 24, 40, 0.04),
            0 16px 32px -22px rgba(16, 24, 40, 0.20),
            0 44px 80px -60px rgba(16, 24, 40, 0.22) !important;
        animation: zc-spin 8s linear infinite !important;
        transition: transform .28s cubic-bezier(.2, .7, .2, 1), box-shadow .28s ease !important;
    }
    .studio-reference-workspace .studio-card:hover,
    .studio-reference-workspace .studio-panel:hover,
    .studio-reference-workspace .studio-crm-card:hover,
    .studio-reference-workspace .studio-order360-card:hover,
    .studio-reference-workspace .zc-op-panel:hover,
    .studio-reference-workspace .zc-pf-card:hover {
        transform: translateY(-3px);
        box-shadow:
            0 2px 4px rgba(16, 24, 40, 0.05),
            0 24px 46px -22px rgba(16, 24, 40, 0.26),
            0 56px 96px -60px rgba(16, 24, 40, 0.28) !important;
    }

    /* Small repeated cards — cheaper colourful border-cycle */
    .studio-reference-workspace .studio-summary-card,
    .studio-reference-workspace .studio-module-card,
    .studio-reference-workspace .studio-stat-card,
    .studio-reference-workspace .studio-metric-card,
    .studio-reference-workspace .studio-status-widget,
    .studio-reference-workspace .studio-command-kpi,
    .studio-reference-workspace .zc-op-stat {
        border: 1.4px solid rgba(201, 162, 74, 0.55) !important;
        border-radius: 15px !important;
        background: linear-gradient(180deg, #ffffff, #fdfdff) !important;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 14px 28px -22px rgba(16, 24, 40, 0.20) !important;
        animation: zc-hue-cycle 9s linear infinite !important;
        transition: transform .2s ease, box-shadow .2s ease !important;
    }
    .studio-reference-workspace .studio-stat-card:hover,
    .studio-reference-workspace .studio-metric-card:hover,
    .studio-reference-workspace .studio-command-kpi:hover,
    .studio-reference-workspace .studio-summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(16, 24, 40, 0.05), 0 20px 38px -22px rgba(16, 24, 40, 0.26) !important;
    }

    /* Premium primary buttons — sheen + lift */
    .studio-reference-workspace .studio-command-button--primary,
    .studio-reference-workspace .studio-action-btn--primary {
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 12px 26px -12px rgba(201, 162, 74, 0.6) !important;
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease !important;
    }
    .studio-reference-workspace .studio-command-button--primary:hover,
    .studio-reference-workspace .studio-action-btn--primary:hover {
        transform: translateY(-1px);
        filter: brightness(1.04);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6), 0 18px 34px -12px rgba(201, 162, 74, 0.7) !important;
    }

    /* Premium inputs — rounder, smooth focus */
    .studio-reference-workspace main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    .studio-reference-workspace main select,
    .studio-reference-workspace main textarea,
    .studio-reference-workspace .studio-form-control {
        border-radius: 11px !important;
        transition: border-color .16s ease, box-shadow .16s ease !important;
    }

    /* Premium styled scrollbars in the content area */
    .studio-reference-workspace *::-webkit-scrollbar { width: 10px; height: 10px; }
    .studio-reference-workspace *::-webkit-scrollbar-track { background: transparent; }
    .studio-reference-workspace *::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #cbb27a, #b7c3dc);
        border-radius: 999px; border: 2px solid transparent; background-clip: content-box;
    }
    .studio-reference-workspace *::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #c9a24a, #6b8fd6); background-clip: content-box;
    }

    /* Premium "deep-ink + gold" secondary buttons (Back, Filter, Exchange,
       call/verify, view…) — glossy dark with a gold hairline and hover glow,
       so they read as intentional next to the gold primary buttons. */
    .studio-reference-workspace .studio-command-button:not(.studio-command-button--primary),
    .studio-reference-workspace .studio-action-btn:not(.studio-action-btn--primary),
    .studio-reference-workspace .zc-ol-act-btn--view {
        background: linear-gradient(160deg, #2b3142 0%, #1c2130 60%, #171b27 100%) !important;
        border: 1px solid rgba(201, 162, 74, 0.34) !important;
        color: #f2e7cf !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 10px 24px -14px rgba(16, 24, 40, 0.55) !important;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, filter .16s ease !important;
    }
    .studio-reference-workspace .studio-command-button:not(.studio-command-button--primary):hover,
    .studio-reference-workspace .studio-action-btn:not(.studio-action-btn--primary):hover,
    .studio-reference-workspace .zc-ol-act-btn--view:hover {
        transform: translateY(-1px);
        filter: brightness(1.06);
        border-color: rgba(201, 162, 74, 0.62) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 16px 32px -14px rgba(16, 24, 40, 0.6), 0 0 0 3px rgba(201, 162, 74, 0.12) !important;
    }
    .studio-reference-workspace .studio-command-button:not(.studio-command-button--primary) svg,
    .studio-reference-workspace .studio-action-btn:not(.studio-action-btn--primary) svg {
        color: #d9b877 !important;
    }

    @media (prefers-reduced-motion: reduce) {
        .studio-reference-workspace .studio-card,
        .studio-reference-workspace .studio-panel,
        .studio-reference-workspace .studio-form-section,
        .studio-reference-workspace .studio-filter-panel,
        .studio-reference-workspace .studio-action-bar,
        .studio-reference-workspace .studio-table-shell,
        .studio-reference-workspace .zc-op-panel,
        .studio-reference-workspace .zc-pf-card,
        .studio-reference-workspace .studio-stat-card,
        .studio-reference-workspace .studio-metric-card,
        .studio-reference-workspace .studio-command-kpi,
        .studio-reference-workspace .zc-op-stat { animation: none !important; }
    }
</style>
