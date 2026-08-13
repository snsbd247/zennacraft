<style>
    /*
     * Phase 62 global design foundation.
     * This layer is intentionally token-first and page-agnostic. Studio and
     * storefront bridge their existing class names onto these tokens.
     */
    :root {
        --zc-ds-color-indigo-950: #1d2147;
        --zc-ds-color-indigo-900: #272b5f;
        --zc-ds-color-indigo-800: #353a74;
        --zc-ds-color-gold-700: #a87a21;
        --zc-ds-color-gold-600: #c79a3b;
        --zc-ds-color-gold-100: #f3e3b8;
        --zc-ds-color-terracotta-700: #9b4d37;
        --zc-ds-color-terracotta-600: #b85f42;
        --zc-ds-color-terracotta-100: #f7dfd7;
        --zc-ds-color-ivory-50: #fffdf7;
        --zc-ds-color-ivory-100: #fbf7ec;
        --zc-ds-color-ivory-200: #f6eddc;
        --zc-ds-color-charcoal-950: #161616;
        --zc-ds-color-charcoal-900: #232323;
        --zc-ds-color-muted: #667085;
        /* Was #8a93a3 (2.89:1 on --zc-ds-color-ivory-100 — fails WCAG AA
           4.5:1 for the caption/kicker/placeholder text this token is
           used as). Darkened to 4.73:1 on ivory-100 / 5.06:1 on white,
           same hue, while staying as light as the AA floor allows — see
           docs/accessibility-contrast-audit.md. This necessarily sits
           close to --zc-ds-color-muted (4.65:1 itself), narrowing the
           visual distinction between the two "de-emphasized text" tiers;
           that's an inherent consequence of both needing to clear 4.5:1,
           not an oversight. */
        --zc-ds-color-subtle: #686f7b;
        --zc-ds-color-border: #e5ded0;
        --zc-ds-color-border-strong: #d4c6aa;
        --zc-ds-color-surface: #ffffff;
        --zc-ds-color-surface-soft: #fffaf0;
        --zc-ds-color-surface-muted: #f8f5ec;
        --zc-ds-color-success: #2f7a42;
        --zc-ds-color-success-soft: #e8f5e8;
        --zc-ds-color-warning: #9a6b1f;
        --zc-ds-color-warning-soft: #fff3d2;
        --zc-ds-color-danger: #9f3d2e;
        --zc-ds-color-danger-soft: #fbe5df;
        --zc-ds-color-info: #315e9b;
        --zc-ds-color-info-soft: #e8eefc;
        --zc-ds-font-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        --zc-ds-font-serif: Georgia, "Times New Roman", serif;
        --zc-ds-font-size-display: clamp(2.7rem, 6vw, 5.8rem);
        --zc-ds-font-size-h1: clamp(2.1rem, 4vw, 4.3rem);
        --zc-ds-font-size-h2: clamp(1.7rem, 3vw, 3rem);
        --zc-ds-font-size-h3: clamp(1.25rem, 2vw, 1.8rem);
        --zc-ds-font-size-h4: 1.08rem;
        --zc-ds-font-size-body: 1rem;
        --zc-ds-font-size-small: 0.9rem;
        --zc-ds-font-size-caption: 0.78rem;
        --zc-ds-font-size-table: 0.88rem;
        --zc-ds-font-size-badge: 0.74rem;
        --zc-ds-line-tight: 1.05;
        --zc-ds-line-heading: 1.16;
        --zc-ds-line-body: 1.68;
        --zc-ds-letter-normal: 0;
        --zc-ds-letter-wide: 0.08em;
        --zc-ds-space-1: 0.25rem;
        --zc-ds-space-2: 0.5rem;
        --zc-ds-space-3: 0.75rem;
        --zc-ds-space-4: 1rem;
        --zc-ds-space-5: 1.25rem;
        --zc-ds-space-6: 1.5rem;
        --zc-ds-space-8: 2rem;
        --zc-ds-space-10: 2.5rem;
        --zc-ds-space-12: 3rem;
        --zc-ds-radius-xs: 8px;
        --zc-ds-radius-sm: 12px;
        --zc-ds-radius-md: 16px;
        --zc-ds-radius-lg: 18px;
        --zc-ds-radius-xl: 24px;
        --zc-ds-radius-2xl: 30px;
        --zc-ds-radius-pill: 9999px;
        --zc-ds-shadow-xs: 0 1px 2px rgba(22, 22, 22, 0.04);
        --zc-ds-shadow-sm: 0 10px 28px -24px rgba(29, 33, 71, 0.38);
        --zc-ds-shadow-md: 0 20px 48px -34px rgba(29, 33, 71, 0.44);
        --zc-ds-shadow-lg: 0 32px 74px -44px rgba(29, 33, 71, 0.58);
        --zc-ds-shadow-focus: 0 0 0 4px rgba(199, 154, 59, 0.24);
        --zc-ds-opacity-disabled: 0.52;
        --zc-ds-transition-fast: 140ms ease;
        --zc-ds-transition-base: 180ms ease;
        --zc-ds-transition-slow: 260ms ease;
        --zc-ds-z-dropdown: 50;
        --zc-ds-z-sticky: 60;
        --zc-ds-z-overlay: 80;
        --zc-ds-z-modal: 90;
        --zc-ds-container-sm: 48rem;
        --zc-ds-container-md: 64rem;
        --zc-ds-container-lg: 80rem;
        --zc-ds-container-xl: 92rem;
        --zc-ds-grid-gap: clamp(1rem, 2vw, 1.5rem);
        --zc-ds-grid-min: 16rem;
    }

    :where(html) {
        font-family: var(--zc-ds-font-sans);
        text-rendering: optimizeLegibility;
        scroll-behavior: smooth;
    }

    :where(*, *::before, *::after) {
        box-sizing: border-box;
    }

    :where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
        outline: 2px solid var(--zc-ds-color-gold-600);
        outline-offset: 3px;
        box-shadow: var(--zc-ds-shadow-focus);
    }

    :where(button, input, select, textarea) {
        font: inherit;
    }

    :where(button, summary, a) {
        transition:
            color var(--zc-ds-transition-base),
            background var(--zc-ds-transition-base),
            border-color var(--zc-ds-transition-base),
            box-shadow var(--zc-ds-transition-base),
            transform var(--zc-ds-transition-base),
            opacity var(--zc-ds-transition-base);
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 1ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: 1ms !important;
        }
    }

    .zc-ds-container {
        width: min(100% - 2rem, var(--zc-ds-container-lg));
        margin-inline: auto;
    }

    .zc-ds-grid {
        display: grid;
        gap: var(--zc-ds-grid-gap);
        grid-template-columns: repeat(auto-fit, minmax(min(100%, var(--zc-ds-grid-min)), 1fr));
    }

    .zc-ds-display,
    .zc-ds-h1,
    .zc-ds-h2,
    .zc-ds-h3,
    .zc-ds-h4 {
        color: var(--zc-ds-color-charcoal-950);
        letter-spacing: var(--zc-ds-letter-normal);
        margin: 0;
    }

    .zc-ds-display {
        font-size: var(--zc-ds-font-size-display);
        font-weight: 950;
        line-height: var(--zc-ds-line-tight);
    }

    .zc-ds-h1 {
        font-size: var(--zc-ds-font-size-h1);
        font-weight: 950;
        line-height: var(--zc-ds-line-tight);
    }

    .zc-ds-h2 {
        font-size: var(--zc-ds-font-size-h2);
        font-weight: 920;
        line-height: var(--zc-ds-line-heading);
    }

    .zc-ds-h3 {
        font-size: var(--zc-ds-font-size-h3);
        font-weight: 900;
        line-height: var(--zc-ds-line-heading);
    }

    .zc-ds-h4,
    .zc-ds-button-text {
        font-size: var(--zc-ds-font-size-h4);
        font-weight: 850;
        line-height: 1.28;
    }

    .zc-ds-body {
        color: var(--zc-ds-color-muted);
        font-size: var(--zc-ds-font-size-body);
        line-height: var(--zc-ds-line-body);
    }

    .zc-ds-small {
        color: var(--zc-ds-color-muted);
        font-size: var(--zc-ds-font-size-small);
        line-height: 1.55;
    }

    .zc-ds-caption,
    .zc-ds-kicker {
        color: var(--zc-ds-color-subtle);
        font-size: var(--zc-ds-font-size-caption);
        font-weight: 850;
        letter-spacing: var(--zc-ds-letter-wide);
        line-height: 1.35;
        text-transform: uppercase;
    }

    .zc-ds-btn {
        align-items: center;
        border: 1px solid transparent;
        border-radius: var(--zc-ds-radius-pill);
        cursor: pointer;
        display: inline-flex;
        font-size: 0.92rem;
        font-weight: 850;
        gap: 0.55rem;
        justify-content: center;
        line-height: 1;
        min-height: 2.75rem;
        padding: 0.82rem 1.15rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .zc-ds-btn:hover,
    .zc-ds-btn:focus-visible {
        transform: translateY(-1px);
    }

    .zc-ds-btn:disabled,
    .zc-ds-btn[aria-disabled="true"],
    .zc-ds-is-disabled {
        cursor: not-allowed;
        opacity: var(--zc-ds-opacity-disabled);
        pointer-events: none;
    }

    .zc-ds-btn--primary {
        background: linear-gradient(135deg, var(--zc-ds-color-indigo-950), var(--zc-ds-color-indigo-800));
        color: var(--zc-ds-color-ivory-50);
        box-shadow: var(--zc-ds-shadow-md);
    }

    .zc-ds-btn--secondary {
        border-color: var(--zc-ds-color-border-strong);
        background: var(--zc-ds-color-surface);
        color: var(--zc-ds-color-indigo-950);
        box-shadow: var(--zc-ds-shadow-sm);
    }

    .zc-ds-btn--outline {
        border-color: rgba(29, 33, 71, 0.26);
        background: transparent;
        color: var(--zc-ds-color-indigo-950);
    }

    .zc-ds-btn--ghost {
        background: transparent;
        color: var(--zc-ds-color-indigo-950);
    }

    .zc-ds-btn--danger {
        background: var(--zc-ds-color-danger);
        color: #fff;
    }

    .zc-ds-btn--success {
        background: var(--zc-ds-color-success);
        color: #fff;
    }

    .zc-ds-btn--icon {
        min-height: 2.5rem;
        padding: 0;
        width: 2.5rem;
    }

    .zc-ds-btn--sm {
        min-height: 2.2rem;
        padding: 0.6rem 0.85rem;
        font-size: 0.8rem;
    }

    .zc-ds-btn--lg {
        min-height: 3.3rem;
        padding: 1rem 1.4rem;
        font-size: 1rem;
    }

    .zc-ds-card {
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-xl);
        background: var(--zc-ds-color-surface);
        box-shadow: var(--zc-ds-shadow-sm);
    }

    .zc-ds-card--dashboard,
    .zc-ds-card--metric,
    .zc-ds-card--chart,
    .zc-ds-card--finance,
    .zc-ds-card--timeline,
    .zc-ds-card--customer {
        padding: clamp(1rem, 2vw, 1.35rem);
    }

    .zc-ds-card--interactive:hover,
    .zc-ds-card--interactive:focus-within {
        border-color: rgba(199, 154, 59, 0.58);
        box-shadow: var(--zc-ds-shadow-md);
        transform: translateY(-1px);
    }

    .zc-ds-card--alert {
        border-color: rgba(184, 95, 66, 0.34);
        background: linear-gradient(180deg, var(--zc-ds-color-ivory-50), var(--zc-ds-color-terracotta-100));
    }

    .zc-ds-field,
    .zc-ds-select,
    .zc-ds-textarea {
        width: 100%;
        border: 1px solid var(--zc-ds-color-border-strong);
        border-radius: var(--zc-ds-radius-md);
        background: var(--zc-ds-color-surface);
        color: var(--zc-ds-color-charcoal-950);
        min-height: 2.8rem;
        padding: 0.78rem 0.95rem;
    }

    .zc-ds-textarea {
        min-height: 7rem;
        resize: vertical;
    }

    .zc-ds-field-help,
    .zc-ds-field-error,
    .zc-ds-field-success {
        display: block;
        font-size: var(--zc-ds-font-size-caption);
        font-weight: 760;
        margin-top: 0.35rem;
    }

    .zc-ds-field-error {
        color: var(--zc-ds-color-danger);
    }

    .zc-ds-field-success {
        color: var(--zc-ds-color-success);
    }

    .zc-ds-switch {
        align-items: center;
        display: inline-flex;
        gap: 0.65rem;
    }

    .zc-ds-table-shell {
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-xl);
        background: var(--zc-ds-color-surface);
        box-shadow: var(--zc-ds-shadow-sm);
        overflow: hidden;
    }

    .zc-ds-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: var(--zc-ds-font-size-table);
        width: 100%;
    }

    .zc-ds-table th {
        background: var(--zc-ds-color-surface-muted);
        color: var(--zc-ds-color-muted);
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: var(--zc-ds-letter-wide);
        padding: 0.9rem 1rem;
        text-align: left;
        text-transform: uppercase;
    }

    .zc-ds-table td {
        border-top: 1px solid var(--zc-ds-color-border);
        color: var(--zc-ds-color-charcoal-900);
        padding: 0.95rem 1rem;
        vertical-align: top;
    }

    .zc-ds-table tbody tr:hover {
        background: rgba(243, 227, 184, 0.22);
    }

    .zc-ds-table--sticky th {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .zc-ds-badge {
        align-items: center;
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-pill);
        display: inline-flex;
        font-size: var(--zc-ds-font-size-badge);
        font-weight: 850;
        gap: 0.35rem;
        line-height: 1;
        min-height: 1.65rem;
        padding: 0.38rem 0.65rem;
        text-transform: none;
        white-space: nowrap;
    }

    .zc-ds-badge--neutral { background: #f3f4f6; border-color: #e5e7eb; color: #374151; }
    .zc-ds-badge--success { background: var(--zc-ds-color-success-soft); border-color: #bddfbd; color: var(--zc-ds-color-success); }
    .zc-ds-badge--warning { background: var(--zc-ds-color-warning-soft); border-color: #edd99d; color: var(--zc-ds-color-warning); }
    .zc-ds-badge--danger { background: var(--zc-ds-color-danger-soft); border-color: #e7b6aa; color: var(--zc-ds-color-danger); }
    .zc-ds-badge--info { background: var(--zc-ds-color-info-soft); border-color: #c4d0ec; color: var(--zc-ds-color-info); }
    .zc-ds-badge--priority { background: var(--zc-ds-color-gold-100); border-color: rgba(199, 154, 59, 0.38); color: var(--zc-ds-color-indigo-950); }

    .zc-ds-empty {
        align-items: center;
        border: 1px dashed var(--zc-ds-color-border-strong);
        border-radius: var(--zc-ds-radius-xl);
        background: linear-gradient(180deg, var(--zc-ds-color-ivory-50), var(--zc-ds-color-ivory-100));
        display: grid;
        gap: 0.8rem;
        justify-items: center;
        padding: clamp(2rem, 4vw, 3rem);
        text-align: center;
    }

    .zc-ds-empty__icon {
        align-items: center;
        background: var(--zc-ds-color-surface);
        border: 1px solid var(--zc-ds-color-border);
        border-radius: var(--zc-ds-radius-pill);
        box-shadow: var(--zc-ds-shadow-sm);
        color: var(--zc-ds-color-indigo-950);
        display: inline-flex;
        height: 3.4rem;
        justify-content: center;
        width: 3.4rem;
    }

    @keyframes zc-ds-shimmer {
        100% {
            transform: translateX(100%);
        }
    }

    .zc-ds-skeleton {
        border-radius: var(--zc-ds-radius-sm);
        background: linear-gradient(90deg, #ede4d5, #fffaf0, #ede4d5);
        min-height: 0.9rem;
        overflow: hidden;
        position: relative;
    }

    .zc-ds-skeleton::after {
        animation: zc-ds-shimmer 1.3s infinite;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.72), transparent);
        content: '';
        inset: 0;
        position: absolute;
        transform: translateX(-100%);
    }

    .zc-ds-spinner {
        animation: zc-ds-spin 760ms linear infinite;
        border: 2px solid rgba(199, 154, 59, 0.22);
        border-top-color: var(--zc-ds-color-gold-600);
        border-radius: 9999px;
        display: inline-block;
        height: 1.15rem;
        width: 1.15rem;
    }

    @keyframes zc-ds-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
