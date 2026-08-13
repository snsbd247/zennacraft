<style>
    .zc-op-panel {
        position: relative;
        border-radius: 20px;
        border: 1px solid var(--studio-border);
        background: var(--studio-surface);
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 20px 50px -40px rgba(16, 24, 40, 0.35);
    }

    .zc-op-tbl {
        width: 100%;
        border-collapse: collapse;
    }

    .zc-op-tbl th {
        text-align: left;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.09em;
        color: var(--studio-muted);
        padding: 0.75rem 1.15rem;
        background: rgba(255, 253, 247, 0.025);
        border-bottom: 1px solid var(--studio-border);
        white-space: nowrap;
    }

    .zc-op-tbl td {
        padding: 0.85rem 1.15rem;
        border-bottom: 1px solid var(--studio-border);
        color: var(--studio-text);
        font-size: 0.88rem;
        vertical-align: middle;
    }

    .zc-op-tbl tbody tr:last-child td {
        border-bottom: none;
    }

    .zc-op-tbl tbody tr:hover {
        background: rgba(212, 180, 131, 0.055);
    }

    .zc-op-strong {
        font-weight: 700;
        color: var(--studio-text);
    }

    .zc-op-muted {
        color: var(--studio-muted);
        font-size: 0.78rem;
    }

    .zc-op-empty {
        padding: 2.75rem 1.25rem;
        text-align: center;
        color: var(--studio-muted);
        font-size: 0.9rem;
    }

    .zc-op-toolbar {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        align-items: end;
    }

    .zc-op-field label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--studio-muted);
        margin-bottom: 0.35rem;
    }

    .zc-op-stat-grid {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: repeat(2, 1fr);
    }

    @media (min-width: 640px) {
        .zc-op-stat-grid { grid-template-columns: repeat(4, 1fr); }
    }

    @media (min-width: 1024px) {
        .zc-op-stat-grid { grid-template-columns: repeat(8, 1fr); }
    }

    .zc-op-stat {
        border: 1px solid var(--studio-border);
        border-radius: 14px;
        background: var(--studio-surface-soft);
        padding: 0.75rem 0.85rem;
    }

    .zc-op-stat__label {
        font-size: 0.66rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--studio-muted);
    }

    .zc-op-stat__value {
        margin-top: 0.25rem;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--studio-text);
        font-variant-numeric: tabular-nums;
    }

    .zc-op-bar-track {
        height: 7px;
        border-radius: 999px;
        background: rgba(16, 24, 40, 0.08);
        overflow: hidden;
    }

    .zc-op-bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(135deg, #f8ecc9 0%, #e0bd7d 45%, #a9793f 100%);
    }
</style>
