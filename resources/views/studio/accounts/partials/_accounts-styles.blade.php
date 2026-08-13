@include('studio.products.partials._submodule-styles')
<style>
    /* ---- Accounts shared premium styling ---- */
    .zc-ac-switch{display:flex;gap:0.6rem;margin-bottom:1rem;}
    .zc-ac-switch a{display:inline-flex;align-items:center;gap:6px;padding:0.6rem 1.15rem;border-radius:10px;font-weight:800;font-size:0.85rem;text-decoration:none;border:1px solid transparent;transition:transform .12s,box-shadow .12s;}
    .zc-ac-switch a:hover{transform:translateY(-1px);}
    .zc-ac-switch .is-credit{background:linear-gradient(135deg,#1c8a4e,#22a35c);color:#fff;box-shadow:0 10px 22px -12px rgba(28,138,78,.7);}
    .zc-ac-switch .is-debit{background:linear-gradient(135deg,#c0392b,#e0483d);color:#fff;box-shadow:0 10px 22px -12px rgba(192,57,43,.6);}
    .zc-ac-switch .is-ghost{background:var(--studio-surface-soft);color:var(--studio-muted);border-color:var(--studio-border);}
    .zc-ac-title{text-align:center;font-weight:800;font-size:1.35rem;letter-spacing:.2px;margin:0 0 1.1rem;color:var(--studio-text);}
    .zc-ac-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.7rem;align-items:center;margin-bottom:1.1rem;}
    .zc-ac-filters .grow{grid-column:1/-1;}
    .zc-ac-tools{display:flex;gap:0.5rem;justify-content:flex-end;}
    .zc-ac-tools button,.zc-ac-tools a{display:inline-flex;align-items:center;gap:5px;padding:0.55rem 0.9rem;border-radius:9px;font-weight:700;font-size:0.78rem;border:none;cursor:pointer;text-decoration:none;color:#fff;}
    .zc-ac-tools .t-pdf{background:#b0413a;} .zc-ac-tools .t-csv{background:#1c8a4e;} .zc-ac-tools .t-reset{background:#2f9e6e;}
    .zc-money-c{color:#1c8a4e;font-weight:800;} .zc-money-d{color:#c0392b;font-weight:800;}
    /* balance overview */
    .zc-ac-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem;}
    @media(max-width:900px){.zc-ac-overview{grid-template-columns:1fr;}}
    .zc-ac-col h4{font-size:0.78rem;text-transform:uppercase;letter-spacing:.5px;color:var(--studio-muted);font-weight:800;margin:0 0 0.7rem;}
    .zc-ac-line{display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px dashed var(--studio-border);font-size:0.9rem;}
    .zc-ac-line b{font-weight:800;}
    .zc-ac-line.total{border-bottom:none;border-top:2px solid var(--studio-border);margin-top:0.3rem;padding-top:0.6rem;font-size:1rem;}
    .zc-ac-sumbtn{margin-top:0.9rem;width:100%;text-align:center;padding:0.8rem;border-radius:11px;background:linear-gradient(135deg,#2f9e6e,#37b47c);color:#fff;font-weight:800;letter-spacing:.4px;border:none;}
    .zc-ac-note{padding:0.55rem 0.9rem;border-radius:9px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);font-size:0.82rem;color:var(--studio-muted);margin-bottom:0.7rem;}
    .zc-ac-note b{color:var(--studio-text);}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
    /* account form rows (reuse from other modules) */
    .zc-cf{max-width:820px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:160px 1fr;gap:1.1rem;align-items:start;margin-bottom:1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.86rem;color:var(--studio-text);padding-top:0.6rem;}
    @media(max-width:640px){.zc-cf-row{grid-template-columns:1fr;gap:0.35rem;}.zc-cf-row > label{padding-top:0;}}
    .zc-cf .req{color:#e0483d;font-weight:800;}
</style>
