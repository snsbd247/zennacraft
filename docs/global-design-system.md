# Zenna Craft Global Design System

Phase 62 establishes one reusable UI foundation for both Storefront and Studio. Business features, routes, permissions, controllers, services, models, and database behavior remain unchanged.

## UI Audit Summary

Storefront surfaces audited:

- Homepage, category, product listing, product detail, package selector, cart, checkout, success, invoice, tracking, customer dashboard, customer orders, login, CMS, and landing pages.
- Existing storefront primitives use the `zc-*` namespace and many Tailwind utility combinations.
- Common inconsistencies found: repeated button styles, varied card radii, mixed table styles, multiple badge tones, and duplicated empty/loading patterns.

Studio surfaces audited:

- Dashboard, sidebar, command palette, notification shell, orders, customers, Customer 360, Order 360, recovery, verification, courier, finance, marketing, automation, analytics, reports, reviews, diagnostics, settings, media, CMS, sliders, products, categories, coupons, expenses, security, backup, deployment, and audit.
- Existing Studio primitives use the `studio-*` namespace and several page-specific additions from previous phases.
- Common inconsistencies found: multiple card systems, mixed status badges, legacy gray/blue utility colors, repeated table shells, varied form spacing, and scattered empty-state markup.

The chosen Phase 62 strategy is a compatibility layer:

- Keep all existing pages and components.
- Add shared tokens in `resources/views/partials/global-design-system.blade.php`.
- Include the global foundation from both existing design-system partials.
- Bridge existing `zc-*` and `studio-*` classes onto global tokens.
- Document new reusable classes so future work does not create duplicate UI primitives.

## Files

- Global foundation: `resources/views/partials/global-design-system.blade.php`
- Storefront bridge: `resources/views/storefront/partials/design-system.blade.php`
- Studio bridge: `resources/views/studio/partials/design-system.blade.php`

## Design Tokens

Use the `--zc-ds-*` token namespace for new UI work.

Color tokens:

- `--zc-ds-color-indigo-950`, `--zc-ds-color-indigo-900`, `--zc-ds-color-indigo-800`
- `--zc-ds-color-gold-700`, `--zc-ds-color-gold-600`, `--zc-ds-color-gold-100`
- `--zc-ds-color-terracotta-700`, `--zc-ds-color-terracotta-600`, `--zc-ds-color-terracotta-100`
- `--zc-ds-color-ivory-50`, `--zc-ds-color-ivory-100`, `--zc-ds-color-ivory-200`
- `--zc-ds-color-charcoal-950`, `--zc-ds-color-charcoal-900`
- `--zc-ds-color-success`, `--zc-ds-color-warning`, `--zc-ds-color-danger`, `--zc-ds-color-info`
- `--zc-ds-color-surface`, `--zc-ds-color-surface-soft`, `--zc-ds-color-surface-muted`
- `--zc-ds-color-border`, `--zc-ds-color-border-strong`

Typography tokens:

- `--zc-ds-font-size-display`
- `--zc-ds-font-size-h1`
- `--zc-ds-font-size-h2`
- `--zc-ds-font-size-h3`
- `--zc-ds-font-size-h4`
- `--zc-ds-font-size-body`
- `--zc-ds-font-size-small`
- `--zc-ds-font-size-caption`
- `--zc-ds-font-size-table`
- `--zc-ds-font-size-badge`
- `--zc-ds-line-tight`
- `--zc-ds-line-heading`
- `--zc-ds-line-body`

Spacing tokens:

- `--zc-ds-space-1`
- `--zc-ds-space-2`
- `--zc-ds-space-3`
- `--zc-ds-space-4`
- `--zc-ds-space-5`
- `--zc-ds-space-6`
- `--zc-ds-space-8`
- `--zc-ds-space-10`
- `--zc-ds-space-12`

Radius tokens:

- `--zc-ds-radius-xs`
- `--zc-ds-radius-sm`
- `--zc-ds-radius-md`
- `--zc-ds-radius-lg`
- `--zc-ds-radius-xl`
- `--zc-ds-radius-2xl`
- `--zc-ds-radius-pill`

Shadow and motion tokens:

- `--zc-ds-shadow-xs`
- `--zc-ds-shadow-sm`
- `--zc-ds-shadow-md`
- `--zc-ds-shadow-lg`
- `--zc-ds-shadow-focus`
- `--zc-ds-transition-fast`
- `--zc-ds-transition-base`
- `--zc-ds-transition-slow`

Layer tokens:

- `--zc-ds-z-dropdown`
- `--zc-ds-z-sticky`
- `--zc-ds-z-overlay`
- `--zc-ds-z-modal`

Layout tokens:

- `--zc-ds-container-sm`
- `--zc-ds-container-md`
- `--zc-ds-container-lg`
- `--zc-ds-container-xl`
- `--zc-ds-grid-gap`
- `--zc-ds-grid-min`

## Typography Components

Use these classes for new UI:

- `zc-ds-display`
- `zc-ds-h1`
- `zc-ds-h2`
- `zc-ds-h3`
- `zc-ds-h4`
- `zc-ds-body`
- `zc-ds-small`
- `zc-ds-caption`
- `zc-ds-kicker`
- `zc-ds-button-text`

Rules:

- Do not use negative letter spacing.
- Keep body copy at readable line height.
- Use hero-scale type only for actual heroes.
- Use compact headings inside tables, dashboards, sidebars, and tools.

## Button System

Base class:

- `zc-ds-btn`

Variants:

- `zc-ds-btn--primary`
- `zc-ds-btn--secondary`
- `zc-ds-btn--outline`
- `zc-ds-btn--ghost`
- `zc-ds-btn--danger`
- `zc-ds-btn--success`
- `zc-ds-btn--icon`
- `zc-ds-btn--sm`
- `zc-ds-btn--lg`

Existing compatible classes:

- Storefront: `zc-btn`, `zc-btn-primary`, `zc-btn-secondary`, `zc-btn-ghost`, `zc-btn-danger`
- Studio: `studio-command-button`, `studio-action-btn`, `studio-btn`, `studio-icon-button`

## Card System

Base class:

- `zc-ds-card`

Variants:

- `zc-ds-card--dashboard`
- `zc-ds-card--metric`
- `zc-ds-card--product`
- `zc-ds-card--customer`
- `zc-ds-card--chart`
- `zc-ds-card--finance`
- `zc-ds-card--timeline`
- `zc-ds-card--empty`
- `zc-ds-card--alert`
- `zc-ds-card--interactive`

Existing compatible classes:

- Storefront: `zc-card`, `zc-card-lg`, `zc-product-card`, `zc-cart-drawer`, `zc-cart-line`, `zc-checkout-panel`, `zc-invoice-shell`
- Studio: `studio-card`, `studio-panel`, `studio-form-panel`, `studio-form-section`, `studio-stat-card`, `studio-summary-card`, `studio-module-card`, `studio-table-shell`

## Form System

New reusable classes:

- `zc-ds-field`
- `zc-ds-select`
- `zc-ds-textarea`
- `zc-ds-field-help`
- `zc-ds-field-error`
- `zc-ds-field-success`
- `zc-ds-switch`

Existing input/select/textarea elements in Studio and Storefront are bridged to shared focus, border, radius, and surface tokens.

## Table System

New reusable classes:

- `zc-ds-table-shell`
- `zc-ds-table`
- `zc-ds-table--sticky`

Expected table patterns:

- Header with uppercase compact labels.
- Rows with comfortable vertical rhythm.
- Hover states using soft gold tint.
- Pagination outside the table shell.
- Use horizontal overflow only when dense operational tables require it.

## Badge System

Base class:

- `zc-ds-badge`

Variants:

- `zc-ds-badge--neutral`
- `zc-ds-badge--success`
- `zc-ds-badge--warning`
- `zc-ds-badge--danger`
- `zc-ds-badge--info`
- `zc-ds-badge--priority`

Existing compatible classes:

- Storefront: `zc-badge`, `zc-badge-success`, `zc-badge-warning`, `zc-badge-danger`, `zc-badge-info`
- Studio: `studio-badge`, `studio-badge--neutral`, `studio-badge--success`, `studio-badge--warning`, `studio-badge--danger`, `studio-badge--info`

## Empty States

New reusable classes:

- `zc-ds-empty`
- `zc-ds-empty__icon`

Existing compatible classes:

- Storefront: `zc-empty-state`, `zc-empty-state__icon`, `zc-empty-state__title`, `zc-empty-state__copy`
- Studio: `studio-empty-state`, `studio-empty-state__icon`, `studio-empty-state__title`, `studio-empty-state__description`, `studio-empty-state__action`

Empty states should include:

- Simple placeholder icon or motif.
- Clear message.
- Optional route-safe action button.
- No fake content.

## Loading System

New reusable classes:

- `zc-ds-skeleton`
- `zc-ds-spinner`

Existing compatible classes:

- Storefront: `zc-skeleton`
- Studio: `studio-skeleton`, `studio-skeleton-card`

Use skeletons for cards, tables, dashboards, and product slots. Keep animation subtle and respect reduced-motion preferences.

## Accessibility

The global foundation includes:

- Unified focus-visible outline.
- Reduced-motion support.
- Button/form font inheritance.
- Tokenized contrast for success, warning, danger, and info states.
- Larger pill buttons for touch-friendly actions.

Future UI work must:

- Keep semantic headings.
- Use real labels for form fields.
- Keep keyboard-accessible menus, dialogs, and drawers.
- Avoid text over imagery without adequate contrast.

## Performance

The design system uses:

- Blade and CSS only.
- No React, Vue, or heavy frontend dependency.
- No external design assets.
- Lightweight CSS tokens and class aliases.

## Future Phase Rule

Future UI phases should reuse `--zc-ds-*` tokens and `zc-ds-*` components first. If a new component is required, document it here before using it across pages.
