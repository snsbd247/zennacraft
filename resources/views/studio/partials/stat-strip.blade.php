{{-- Shared premium stat strip: include once per page, then render a
     <div class="zc-stat-strip"> of .zc-stat items. Numbers with
     data-countup animate up on load. --}}
@once
@push('studio-styles')
<style>
    .zc-stat-strip{display:grid;grid-template-columns:repeat(2,1fr);gap:0.7rem;margin-bottom:1.1rem;}
    @media(min-width:760px){.zc-stat-strip{grid-template-columns:repeat(4,1fr);}.zc-stat-strip--3{grid-template-columns:repeat(3,1fr);}.zc-stat-strip--2{grid-template-columns:repeat(2,1fr);}}
    .zc-stat{display:flex;align-items:center;gap:0.75rem;padding:0.8rem 0.95rem;border-radius:15px;border:1px solid var(--studio-border);background:var(--studio-surface-soft);text-decoration:none;box-shadow:0 22px 55px -44px rgba(16,24,40,0.5);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;animation:zcStatIn .5s cubic-bezier(.2,.8,.25,1) backwards;}
    a.zc-stat:hover{transform:translateY(-2px);border-color:rgba(212,180,131,0.35);box-shadow:0 26px 60px -40px rgba(16,24,40,0.45);}
    .zc-stat-strip>*:nth-child(1){animation-delay:.04s;}.zc-stat-strip>*:nth-child(2){animation-delay:.10s;}
    .zc-stat-strip>*:nth-child(3){animation-delay:.16s;}.zc-stat-strip>*:nth-child(4){animation-delay:.22s;}
    .zc-stat-strip>*:nth-child(5){animation-delay:.28s;}.zc-stat-strip>*:nth-child(6){animation-delay:.34s;}
    .zc-stat__ic{width:2.5rem;height:2.5rem;border-radius:12px;display:grid;place-items:center;flex:none;}
    .zc-stat__ic svg{width:1.2rem;height:1.2rem;}
    .zc-stat__v{font-size:1.45rem;font-weight:800;line-height:1;color:var(--studio-text);letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
    .zc-stat__l{font-size:0.73rem;font-weight:700;color:var(--studio-muted);margin-top:4px;}
    .zc-stat--blue .zc-stat__ic{background:rgba(59,110,165,0.14);color:#3b6ea5;}
    .zc-stat--green .zc-stat__ic{background:rgba(28,138,78,0.14);color:#1c8a4e;}
    .zc-stat--amber .zc-stat__ic{background:rgba(201,147,15,0.16);color:#a9793f;}
    .zc-stat--red .zc-stat__ic{background:rgba(192,57,43,0.14);color:#c0392b;}
    .zc-stat--gold .zc-stat__ic{background:rgba(212,180,131,0.2);color:#a9793f;}
    .zc-stat--violet .zc-stat__ic{background:rgba(91,69,168,0.15);color:#5b45a8;}
    @keyframes zcStatIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:none;}}
    @media(prefers-reduced-motion:reduce){.zc-stat{animation:none;}}
</style>
@endpush
@push('studio-scripts')
<script>
    (function () {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        document.querySelectorAll('.zc-stat__v[data-countup]').forEach(function (el) {
            var txt = el.textContent.trim();
            if (!/^[0-9,]+$/.test(txt)) return;
            var target = parseInt(txt.replace(/,/g, ''), 10);
            if (!target) return;
            var dur = 800, t0 = null;
            el.textContent = '0';
            function step(ts) {
                if (!t0) t0 = ts;
                var p = Math.min(1, (ts - t0) / dur);
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('en-US');
                if (p < 1) requestAnimationFrame(step); else el.textContent = target.toLocaleString('en-US');
            }
            requestAnimationFrame(step);
        });
    })();
</script>
@endpush
@endonce
