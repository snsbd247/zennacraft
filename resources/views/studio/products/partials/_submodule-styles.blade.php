<style>
    .zc-sm-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;}
    .zc-sm-actions{display:flex;gap:0.6rem;flex-wrap:wrap;}
    .zc-sm-tbl{width:100%;border-collapse:separate;border-spacing:0 0.5rem;}
    .zc-sm-tbl th{text-align:left;font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:var(--studio-muted);padding:0.4rem 1rem;white-space:nowrap;}
    .zc-sm-tbl th:last-child{text-align:right;}
    .zc-sm-tbl td{background:var(--studio-surface);padding:0.8rem 1rem;border-top:1px solid var(--studio-border);border-bottom:1px solid var(--studio-border);vertical-align:middle;font-size:0.86rem;color:var(--studio-text);}
    .zc-sm-tbl td:first-child{border-left:1px solid var(--studio-border);border-top-left-radius:12px;border-bottom-left-radius:12px;color:var(--studio-muted);font-weight:700;width:3rem;}
    .zc-sm-tbl td:last-child{border-right:1px solid var(--studio-border);border-top-right-radius:12px;border-bottom-right-radius:12px;text-align:right;}
    .zc-sm-name{font-weight:700;color:var(--studio-text);}
    .zc-sm-pill{display:inline-flex;align-items:center;gap:5px;padding:0.2rem 0.7rem;border-radius:999px;font-size:0.72rem;font-weight:800;}
    .zc-sm-pill--on{background:rgba(52,199,123,0.14);color:#1c8a4e;}
    .zc-sm-pill--off{background:rgba(224,90,74,0.14);color:#c0392b;}
    .zc-sm-act{display:inline-flex;gap:0.4rem;justify-content:flex-end;align-items:center;}
    .zc-sm-act form{margin:0;}
    .zc-sm-btn{display:inline-flex;align-items:center;justify-content:center;width:2.1rem;height:2.1rem;border-radius:9px;border:none;cursor:pointer;color:#fff;transition:filter .15s ease, transform .15s ease;}
    .zc-sm-btn:hover{filter:brightness(1.08);transform:translateY(-1px);}
    .zc-sm-btn svg{width:1rem;height:1rem;}
    .zc-sm-btn--tog{background:#3b6ea5;} .zc-sm-btn--edit{background:#2aa564;} .zc-sm-btn--del{background:#e0483a;} .zc-sm-btn--view{background:#3b6ea5;} .zc-sm-btn--ok{background:#e0a12a;}
    .zc-sm-prod{display:flex;align-items:center;gap:0.7rem;min-width:0;}
    .zc-sm-thumb{width:44px;height:44px;border-radius:9px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);flex:none;display:grid;place-items:center;color:var(--studio-muted);}
    .zc-sm-stars{color:#e0a12a;letter-spacing:1px;font-size:0.95rem;}
    .zc-sm-empty{text-align:center;padding:3rem 1rem;color:var(--studio-muted);}
    .zc-sm-form{max-width:620px;}
    .zc-sm-field{display:grid;gap:0.45rem;margin-bottom:1.1rem;}
    .zc-sm-field > label{font-weight:700;font-size:0.78rem;color:var(--studio-muted);text-transform:uppercase;letter-spacing:0.03em;}
    .zc-sm-filters{display:flex;gap:0.6rem;flex-wrap:wrap;align-items:end;margin-bottom:1.25rem;}
    .zc-sm-filters .zc-sm-field{margin-bottom:0;}
    .zc-sm-pager{display:flex;justify-content:center;gap:0.5rem;margin-top:1.25rem;align-items:center;color:var(--studio-muted);font-size:0.82rem;}
</style>
