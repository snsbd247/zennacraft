# WCAG Contrast Audit — Studio & Storefront Design Systems

Computed using the standard WCAG relative-luminance formula (sRGB → linearized → `0.2126R + 0.7152G + 0.0722B`), contrast ratio `(L_lighter + 0.05) / (L_darker + 0.05)`. Thresholds: **AA normal text ≥ 4.5:1**, **AA large text (≥18.66px bold / ≥24px regular) or UI components ≥ 3:1**.

Every color pair below is a combination actually used somewhere in `resources/views/partials/global-design-system.blade.php`, `resources/views/studio/partials/design-system.blade.php`, or `resources/views/storefront/partials/design-system.blade.php` — not a hypothetical. File:line citations point at the CSS rule, not just the token definition.

## Studio

| Combination | Foreground | Background | Ratio | AA Normal | AA Large/UI | Used at |
|---|---|---|---|---|---|---|
| Body text on page background | `#161616` (`--zc-ds-color-charcoal-950`) | `#fbf7ec` (`--zc-ds-color-ivory-100`) | 16.90:1 | PASS | PASS | Base `body.studio-shell` text color |
| Muted text on page background | `#667085` (`--zc-ds-color-muted`) | `#fbf7ec` | 4.65:1 | PASS | PASS | `global-design-system.blade.php:185,191` |
| Muted text on white surface | `#667085` | `#ffffff` (`--zc-ds-color-surface`) | 4.97:1 | PASS | PASS | Card/table surfaces |
| **Subtle text on page background** | `#8a93a3` (`--zc-ds-color-subtle`) | `#fbf7ec` | **2.89:1** | **FAIL** | **FAIL** | `global-design-system.blade.php:198` (`.zc-ds-caption`, `.zc-ds-kicker`) and `studio/partials/design-system.blade.php:3980-3982` (`input::placeholder`, `textarea::placeholder`) |
| Primary button text on button bg (gradient start) | `#fffdf7` (`--zc-ds-color-ivory-50`) | `#1d2147` (`--zc-ds-color-indigo-950`) | 15.17:1 | PASS | PASS | `.studio-command-button--primary` gradient start |
| Primary button text on button bg (gradient end) | `#fffdf7` | `#353a74` (`--zc-ds-color-indigo-800`) | 10.28:1 | PASS | PASS | `.studio-command-button--primary` gradient end |
| **Gold-600 as border on focus** | `#c79a3b` (`--zc-ds-color-gold-600`) | `#fbf7ec` / `#ffffff` | 2.42–2.59:1 | N/A (not text) | N/A (decorative + focus ring, not text) | `studio/partials/design-system.blade.php:1173,3989`, `global-design-system.blade.php:472` — all `border-color`/`border-top-color`, never `color`. Flagged for completeness; not a text-contrast failure since it's never used as text. Focus rings additionally carry `box-shadow: var(--zc-ds-shadow-focus)` alongside the border, so focus visibility doesn't depend on this color alone. |
| **Gold-700 as hover-text color** | `#a87a21` (`--zc-ds-color-gold-700`) | `#fbf7ec` | **3.59:1** | **FAIL** | PASS | `studio/partials/design-system.blade.php:4309-4314` (`.studio-65d-link:hover`, `a.text-blue-600:hover`, `button.text-red-600:hover`, `button.text-blue-600:hover`) |
| Gold-700 as hover-text color | `#a87a21` | `#ffffff` | 3.84:1 | FAIL | PASS | Same rule, white surface context |
| Warning text on warning-soft badge | `#9a6b1f` (`--zc-ds-color-warning`) | `#fff3d2` (`--zc-ds-color-warning-soft`) | 4.23:1 | FAIL (borderline) | PASS | Warning-tone `.studio-badge` |
| Success text on success-soft badge | `#2f7a42` | `#e8f5e8` | 4.68:1 | PASS | PASS | Success-tone `.studio-badge` |
| Danger text on danger-soft badge | `#9f3d2e` | `#fbe5df` | 5.46:1 | PASS | PASS | Danger-tone `.studio-badge` |

## Storefront

| Combination | Foreground | Background | Ratio | AA Normal | AA Large/UI | Used at |
|---|---|---|---|---|---|---|
| Body text on page background | `#241f1b` (`--zc-ref-ink`) | `#f3ead8` (`--zc-ref-muslin`) | 13.65:1 | PASS | PASS | Base `body` text color |
| Muted text on page background | `#74695b` (`--zc-ref-muted`) | `#f3ead8` | 4.49:1 | FAIL (borderline, 0.01 short) | PASS | `--zc-muted` usages throughout |
| Primary button text on button bg | `#ffffff` | `#151936` (`--zc-primary` / `--zc-ref-indigo`) | 17.15:1 | PASS | PASS | `.zc-btn-primary` |
| Secondary button text on white | `#241f1b` (`--zc-text`) | `#ffffff` | 16.32:1 | PASS | PASS | `.zc-btn-secondary` |
| Maroon accent text on page background | `#6f2f2a` (`--zc-ref-maroon`) | `#f3ead8` | 8.30:1 | PASS | PASS | Accent headings/labels |
| **Gold accent as text color** | — | — | — | — | — | **Not found.** Grepped every `color:` declaration referencing a gold token in `storefront/partials/design-system.blade.php`; the only hit is `--zc-ds-color-gold-100` (below), which is a *light* gold used exclusively on a dark background. All other gold references in this file (10 total) are `background:`/`border-color:` gradients, dividers, and rules — decorative only. |
| Light gold hover-text on dark footer background | `#f3e3b8` (`--zc-ds-color-gold-100`) | `#1d2147` (`--zc-ds-color-indigo-950`) | 12.12:1 | PASS | PASS | `storefront/partials/design-system.blade.php:3141-3143` (`.zc-footer-luxury a:hover, :focus-visible`) — passes comfortably because it's light gold on a dark background, not gold on light. |

## Summary of genuine failures

| # | Item | Ratio | Needed | Verdict |
|---|---|---|---|---|
| 1 | Studio `--zc-ds-color-subtle` as caption/kicker/placeholder text | 2.89:1 | 4.5:1 | **Real failure, actual body/caption text** |
| 2 | Studio gold-700 hover-text | 3.59–3.84:1 | 4.5:1 | **Real failure, actual hover-text** |
| 3 | Studio gold-600 as focus border | 2.42–2.59:1 | 3:1 (non-text UI component) | Below 3:1, but never used as text and always paired with a focus box-shadow — flagged, not classified as a text-contrast failure |
| 4 | Studio warning badge text | 4.23:1 | 4.5:1 | Borderline fail (badge text is typically small, so this matters) |
| 5 | Storefront muted text | 4.49:1 | 4.5:1 | Borderline fail, 0.01 short — imperceptible in practice but technically non-compliant |
| 6 | Storefront gold as text | — | — | **Not applicable — gold is never used as text color in the storefront design system** |

Items 1 and 2 are addressed in commit `C5b` (see `PHASE_C_REPORT.md`) per explicit architect decision: the neutral grey (subtle) is corrected to meet 4.5:1; the gold hover-text (item 2) is replaced with a dark, high-contrast token rather than altering the brand gold itself. Items 3–5 are reported here only, left unchanged, pending an explicit decision — none were in the two items the architect authorized for this pass.
