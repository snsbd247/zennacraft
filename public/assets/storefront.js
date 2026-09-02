// Storefront static JS — extracted from resources/views/layouts/app.blade.php
// so it's a cacheable external file instead of being re-sent inline with
// every single page's HTML. The 3 route URLs it needs are injected by the
// layout as window.ZC_ROUTES just before this file loads.
(function(){
    var drawer=document.querySelector('[data-drawer]');
    function open(){drawer&&drawer.classList.add('is-open');document.body.classList.add('zc-no-scroll');}
    function close(){drawer&&drawer.classList.remove('is-open');document.body.classList.remove('zc-no-scroll');}
    document.querySelectorAll('[data-drawer-open]').forEach(function(b){b.addEventListener('click',open);});
    document.querySelectorAll('[data-drawer-close]').forEach(function(b){b.addEventListener('click',close);});

    // ---- Mobile search overlay ----
    var ms=document.querySelector('[data-msearch]');
    if(ms){
        var msInput=ms.querySelector('[data-msearch-input]');
        function msOpen(){ ms.classList.add('is-open'); ms.setAttribute('aria-hidden','false'); setTimeout(function(){ msInput&&msInput.focus(); },120); }
        function msClose(){ ms.classList.remove('is-open'); ms.setAttribute('aria-hidden','true'); }
        document.querySelectorAll('[data-msearch-open]').forEach(function(b){ b.addEventListener('click',function(e){ e.preventDefault(); close(); msOpen(); }); });
        ms.querySelectorAll('[data-msearch-close]').forEach(function(b){ b.addEventListener('click',msClose); });
        ms.querySelector('form').addEventListener('submit', function(){ if(!(msInput&&msInput.value.trim())){ /* let it go to all products */ } });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') msClose(); });
    }
})();

// ---- Search autocomplete (AJAX suggestions) ----
(function(){
    var url=window.ZC_ROUTES.searchSuggest;
    var inputs=document.querySelectorAll('[data-suggest]'); if(!inputs.length) return;
    var money=function(v){ return '৳'+Number(v).toLocaleString('en-US',{maximumFractionDigits:0}); };
    var esc=function(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; };
    inputs.forEach(function(input){
        var box=document.createElement('div'); box.className='zc-suggest'; input.parentNode.appendChild(box);
        var t, lastQ='', active=-1, items=[];
        function close(){ box.classList.remove('is-open'); active=-1; }
        function render(res,q){
            items=res||[];
            var form=input.closest('form'); var allUrl=(form?form.getAttribute('action'):'/products')+'?q='+encodeURIComponent(q);
            if(!items.length){ box.innerHTML='<div class="zc-suggest__empty">No products match “'+esc(q)+'”.</div>'; box.classList.add('is-open'); return; }
            var html=items.map(function(p){
                return '<a class="zc-suggest__item" href="'+esc(p.url)+'">'+
                    (p.image?'<img class="zc-suggest__img" src="'+esc(p.image)+'" alt="">':'<span class="zc-suggest__img"></span>')+
                    '<span class="zc-suggest__b"><span class="zc-suggest__nm">'+esc(p.name)+'</span><span class="zc-suggest__pr">'+money(p.price)+'</span></span></a>';
            }).join('');
            html+='<a class="zc-suggest__foot" href="'+esc(allUrl)+'">See all results for “'+esc(q)+'” →</a>';
            box.innerHTML=html; box.classList.add('is-open'); active=-1;
        }
        input.addEventListener('input', function(){
            var q=input.value.trim(); clearTimeout(t);
            if(q.length<2){ close(); return; }
            t=setTimeout(function(){
                lastQ=q;
                fetch(url+'?q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
                    .then(function(r){return r.json();}).then(function(d){ if(input.value.trim()===q) render(d.results,q); }).catch(function(){});
            },220);
        });
        input.addEventListener('keydown', function(e){
            if(!box.classList.contains('is-open')) return;
            var links=box.querySelectorAll('.zc-suggest__item, .zc-suggest__foot');
            if(e.key==='ArrowDown'){ e.preventDefault(); active=Math.min(active+1,links.length-1); }
            else if(e.key==='ArrowUp'){ e.preventDefault(); active=Math.max(active-1,-1); }
            else if(e.key==='Enter'){ if(active>=0&&links[active]){ e.preventDefault(); window.location.href=links[active].href; } return; }
            else if(e.key==='Escape'){ close(); return; }
            else return;
            links.forEach(function(l,i){ l.classList.toggle('is-active',i===active); });
            if(active>=0&&links[active]) links[active].scrollIntoView({block:'nearest'});
        });
        input.addEventListener('focus', function(){ if(input.value.trim().length>=2 && box.innerHTML) box.classList.add('is-open'); });
        document.addEventListener('click', function(e){ if(!box.contains(e.target)&&e.target!==input) close(); });
    });
})();

// ---- Slide-out cart drawer ----
(function(){
    var cd=document.querySelector('[data-cart-drawer]'); if(!cd) return;
    var body=cd.querySelector('[data-cart-body]');
    var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content;
    var drawerUrl=window.ZC_ROUTES.cartDrawer, addUrl=window.ZC_ROUTES.cartAdd;
    var autoTimer=null;

    function positionDropdown(trigger){
        var panel = cd.querySelector('.zc-cartdrawer__panel');
        if (window.matchMedia('(min-width:821px)').matches && trigger && trigger.getBoundingClientRect){
            var r = trigger.getBoundingClientRect();
            panel.style.top = (r.bottom + 10) + 'px';
            panel.style.right = Math.max(12, window.innerWidth - r.right) + 'px';
            panel.style.left = 'auto';
        } else { panel.style.top = ''; panel.style.right = ''; panel.style.left = ''; }
    }
    function openCart(e){ if(e) e.preventDefault(); positionDropdown(e ? e.currentTarget : cartTarget()); cd.classList.add('is-open'); document.body.classList.add('zc-no-scroll'); load(); }
    function closeCart(){ cd.classList.remove('is-open'); document.body.classList.remove('zc-no-scroll'); stopAuto(); }
    function setCount(n){ document.querySelectorAll('.zc-act__badge,.zc-botnav__badge').forEach(function(b){ if(n>0){ b.textContent=n; b.style.display=''; } else { b.style.display='none'; } }); }
    function inject(html){ body.innerHTML=html; initCarousel(); }
    function load(){ fetch(drawerUrl,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.text();}).then(inject); }
    function req(url,method,payload){
        return fetch(url,{method:method,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded'},body:payload||null})
            .then(function(r){return r.json();}).then(function(d){ if(d.html!==undefined){ inject(d.html); setCount(d.count); } });
    }
    document.querySelectorAll('[data-cart-open]').forEach(function(a){ a.addEventListener('click',openCart); });
    cd.querySelectorAll('[data-cart-close]').forEach(function(b){ b.addEventListener('click',closeCart); });

    body.addEventListener('click', function(e){
        var inc=e.target.closest('[data-cd-inc]'), dec=e.target.closest('[data-cd-dec]');
        if(inc||dec){ var it=e.target.closest('.zc-cd-item'); var q=parseInt(it.getAttribute('data-qty'),10)||1; q=inc?q+1:Math.max(1,q-1);
            req(it.getAttribute('data-update'),'PATCH','quantity='+q); return; }
        var rm=e.target.closest('[data-cd-remove]');
        if(rm){ req(rm.getAttribute('data-cd-remove'),'DELETE'); return; }
        var add=e.target.closest('[data-cd-add]');
        if(add){ add.textContent='Added ✓'; req(addUrl,'POST','product_id='+add.getAttribute('data-cd-add')+'&quantity=1'); return; }
        var prev=e.target.closest('[data-cd-prev]'), next=e.target.closest('[data-cd-next]');
        if(prev||next){ var tr=body.querySelector('[data-cd-track]'); if(tr) tr.scrollBy({left:(next?1:-1)*165,behavior:'smooth'}); }
    });

    function initCarousel(){ stopAuto(); var tr=body.querySelector('[data-cd-track]'); if(!tr||tr.children.length<2) return;
        autoTimer=setInterval(function(){ if(!cd.classList.contains('is-open')) return;
            if(tr.scrollLeft+tr.clientWidth>=tr.scrollWidth-4){ tr.scrollTo({left:0,behavior:'smooth'}); }
            else { tr.scrollBy({left:165,behavior:'smooth'}); } }, 2600); }
    function stopAuto(){ if(autoTimer){ clearInterval(autoTimer); autoTimer=null; } }

    // ---- Animated add-to-cart from product cards (fly-to-cart + AJAX) ----
    function cartTarget(){ var t=null; document.querySelectorAll('[data-cart-open]').forEach(function(a){ if(a.offsetParent!==null) t=a; }); return t; }
    function bumpCart(target){ if(!target) return; var b=target.querySelector('.zc-act__badge,.zc-botnav__badge'); if(b){ b.classList.remove('zc-cartbump'); void b.offsetWidth; b.classList.add('zc-cartbump'); } }
    function fly(srcImg, done){
        var target=cartTarget();
        if(!srcImg || !target || !srcImg.getBoundingClientRect){ bumpCart(target); done&&done(); return; }
        var s=srcImg.getBoundingClientRect(), t=target.getBoundingClientRect();
        if(!s.width){ bumpCart(target); done&&done(); return; }
        var clone=srcImg.cloneNode(true); clone.className='zc-fly'; clone.removeAttribute('loading');
        clone.style.left=s.left+'px'; clone.style.top=s.top+'px'; clone.style.width=s.width+'px'; clone.style.height=s.height+'px';
        document.body.appendChild(clone); clone.getBoundingClientRect();
        var tx=(t.left+t.width/2)-(s.left+s.width/2), ty=(t.top+t.height/2)-(s.top+s.height/2);
        clone.style.transform='translate('+tx+'px,'+ty+'px) scale(.12)'; clone.style.opacity='.25';
        var gone=false; function end(){ if(gone) return; gone=true; clone.remove(); bumpCart(target); done&&done(); }
        clone.addEventListener('transitionend', end); setTimeout(end, 900);
    }
    document.addEventListener('submit', function(e){
        var form=e.target.closest('form[data-cart-ajax]'); if(!form) return;
        // "Order Now" (checkout=1) navigates to checkout — leave it alone.
        var co=form.querySelector('[name="checkout"]'); if(co && co.value==='1') return;
        e.preventDefault();
        var card=form.closest('.pcard');
        var img=card ? card.querySelector('.pcard__media img') : (form.querySelector('[data-cart-hero]') || document.querySelector('[data-cart-hero]'));
        var btn=form.querySelector('button[data-checkout="0"]') || form.querySelector('button[type="submit"]');
        if(btn && !btn.dataset.orig) btn.dataset.orig=btn.innerHTML;
        fly(img);
        fetch(form.action,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(new FormData(form)).toString()})
            .then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
            .then(function(res){
                if(!res.ok){ if(btn) btn.innerHTML=btn.dataset.orig; if(res.d && res.d.message) alert(res.d.message); return; }
                var d=res.d; if(d && d.html!==undefined){ inject(d.html); setCount(d.count); }
                if(btn){ btn.classList.add('is-added'); btn.innerHTML='Added ✓'; setTimeout(function(){ btn.classList.remove('is-added'); btn.innerHTML=btn.dataset.orig; }, 1500); }
            })
            .catch(function(){ if(btn) btn.innerHTML=btn.dataset.orig; });
    });
})();

// Premium scroll-reveal for storefront sections. Fail-safe by design:
// only sections that are OFF-screen at load get hidden (.zc-pre) and then
// revealed as they scroll into view — so any JS failure leaves everything
// visible. Skipped entirely without IntersectionObserver or with reduced motion.
(function () {
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var sections = [].slice.call(document.querySelectorAll('.zc-sec'));
    if (!sections.length) return;

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('zc-in'); io.unobserve(e.target); }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

    var vh = window.innerHeight || document.documentElement.clientHeight;
    sections.forEach(function (el) {
        // Already visible (above the fold) → leave it be, no hide/flash.
        if (el.getBoundingClientRect().top < vh * 0.9) return;
        el.classList.add('zc-pre');
        io.observe(el);
    });

    // Safety net: never leave a section stuck hidden.
    setTimeout(function () {
        document.querySelectorAll('.zc-sec.zc-pre:not(.zc-in)').forEach(function (el) { el.classList.add('zc-in'); });
    }, 2600);
})();

// Announcement bar: scroll only if the messages overflow (otherwise the
// static centered bar would look "doubled" when duplicated for looping).
(function () {
    var m = document.querySelector('[data-marquee]');
    if (!m) return;
    var track = m.querySelector('.zc-marquee__track');
    var group = track.querySelector('.zc-marquee__group');
    function setup() {
        m.classList.remove('is-scrolling');
        track.querySelectorAll('.zc-marquee__group.is-clone').forEach(function (c) { c.remove(); });
        var groupW = group.getBoundingClientRect().width;
        var contW = m.getBoundingClientRect().width;
        if (groupW > contW - 4) {
            var clone = group.cloneNode(true);
            clone.classList.add('is-clone');
            clone.setAttribute('aria-hidden', 'true');
            track.appendChild(clone);
            track.style.setProperty('--marq-dur', Math.max(14, Math.round(groupW / 45)) + 's');
            m.classList.add('is-scrolling');
        }
    }
    setup();
    var t;
    window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(setup, 200); });
})();

// Live countdown / flash-sale bar.
(function () {
    var bar = document.querySelector('[data-countdown]');
    if (!bar) return;
    var target = parseInt(bar.getAttribute('data-countdown'), 10);
    var elD = bar.querySelector('[data-d]'), elH = bar.querySelector('[data-h]'), elM = bar.querySelector('[data-m]'), elS = bar.querySelector('[data-s]');
    function pad(n){ return (n < 10 ? '0' : '') + n; }
    function tick() {
        var diff = target - Date.now();
        if (diff <= 0) { bar.style.display = 'none'; clearInterval(t); return; }
        var s = Math.floor(diff / 1000);
        elD.textContent = pad(Math.floor(s / 86400));
        elH.textContent = pad(Math.floor((s % 86400) / 3600));
        elM.textContent = pad(Math.floor((s % 3600) / 60));
        elS.textContent = pad(s % 60);
    }
    tick();
    var t = setInterval(tick, 1000);
})();

// Welcome popup — shown once per browsing session. Deferred to
// DOMContentLoaded because the popup markup is rendered after this script.
document.addEventListener('DOMContentLoaded', function () {
    var pop = document.querySelector('[data-popup]');
    if (!pop) return;
    var KEY = 'zc_popup_seen';
    try { if (sessionStorage.getItem(KEY)) return; } catch (e) {}

    function close() {
        pop.classList.remove('is-open');
        try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
        setTimeout(function () { pop.setAttribute('hidden', ''); }, 250);
    }
    pop.querySelectorAll('[data-popup-close]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); close(); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pop.classList.contains('is-open')) close(); });
    var link = pop.querySelector('.zc-pop__link');
    if (link) link.addEventListener('click', function () { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} });

    setTimeout(function () { pop.removeAttribute('hidden'); pop.classList.add('is-open'); }, 900);
});

// ---- Homepage hero carousel (resources/views/storefront/home.blade.php) ----
// Guarded on [data-hero] existing, so this is a safe no-op on every other page.
(function () {
    var hero = document.querySelector('[data-hero]');
    if (!hero) return;
    var track = hero.querySelector('[data-hero-track]');
    var slides = track ? track.children : [];
    if (slides.length < 2) return;
    var dots = hero.querySelectorAll('[data-hero-dot]');
    var idx = 0, timer = null;
    function go(i) {
        idx = (i + slides.length) % slides.length;
        track.style.transform = 'translateX(-' + (idx * 100) + '%)';
        dots.forEach(function (d, n) { d.classList.toggle('is-active', n === idx); });
    }
    function start() { timer = setInterval(function () { go(idx + 1); }, 6000); }
    function reset() { clearInterval(timer); start(); }
    var next = hero.querySelector('[data-hero-next]');
    var prev = hero.querySelector('[data-hero-prev]');
    if (next) next.addEventListener('click', function () { go(idx + 1); reset(); });
    if (prev) prev.addEventListener('click', function () { go(idx - 1); reset(); });
    dots.forEach(function (d, n) { d.addEventListener('click', function () { go(n); reset(); }); });
    hero.addEventListener('mouseenter', function () { clearInterval(timer); });
    hero.addEventListener('mouseleave', start);

    // Manual swipe (touch) — change slides by dragging left/right on mobile.
    var startX = null, startY = null;
    track.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; clearInterval(timer); }, { passive: true });
    track.addEventListener('touchend', function (e) {
        if (startX === null) { start(); return; }
        var dx = e.changedTouches[0].clientX - startX;
        var dy = e.changedTouches[0].clientY - startY;
        // Only treat as a swipe if it's clearly horizontal.
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { go(idx + (dx < 0 ? 1 : -1)); }
        startX = startY = null;
        reset();
    }, { passive: true });

    start();
})();

// Floating IMO button: IMO has no web fallback, so on desktop (no IMO app to
// catch the imo:// link) clicking it would otherwise do nothing visible —
// copy the number and show a toast so the click always does *something*.
(function () {
    var fab = document.querySelector('[data-imo-fab-cc]'), toast = document.getElementById('zc-imo-fab-toast');
    if (!fab || !toast) return;
    fab.addEventListener('click', function () {
        var number = fab.getAttribute('data-imo-number');
        if (navigator.clipboard && number) {
            navigator.clipboard.writeText(number).catch(function () {});
        }
        toast.classList.add('is-on');
        setTimeout(function () { toast.classList.remove('is-on'); }, 3000);
    });
})();
