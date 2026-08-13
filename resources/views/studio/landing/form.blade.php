@extends('layouts.studio')
@section('title', ($page->exists ? 'Edit' : 'Add').' Landing Page')
@section('subtitle', 'Landing Page')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-lf{max-width:1000px;margin:0 auto;}
    .zc-lf .studio-form-control{border-radius:12px;transition:border-color .15s,box-shadow .15s;}
    .zc-lf .studio-form-control:focus{border-color:var(--studio-accent);box-shadow:0 0 0 3px color-mix(in srgb, var(--studio-accent) 18%, transparent);outline:none;}
    /* section headers with numbered chips */
    .zc-lf-sec{display:flex;align-items:center;gap:10px;font-weight:800;font-size:0.98rem;margin:1.9rem 0 1.1rem;color:var(--studio-text);}
    .zc-lf-sec span{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:0.8rem;color:#fff;background:linear-gradient(135deg,var(--studio-accent),color-mix(in srgb,var(--studio-accent) 60%,#000));box-shadow:0 6px 14px -6px color-mix(in srgb,var(--studio-accent) 80%,transparent);}
    .zc-lf-row{display:grid;grid-template-columns:170px 1fr;gap:1.2rem;align-items:start;margin-bottom:1.05rem;}
    .zc-lf-row > label{font-weight:700;font-size:0.85rem;padding-top:0.65rem;color:var(--studio-text);}
    .zc-lf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;margin-top:2px;}
    @media(max-width:640px){.zc-lf-row{grid-template-columns:1fr;gap:0.4rem;}.zc-lf-row>label{padding-top:0;}}
    .req{color:#e0483d;font-weight:800;}
    /* premium template picker */
    .zc-tpl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem;}
    .zc-tpl{position:relative;border:2px solid var(--studio-border);border-radius:16px;overflow:hidden;cursor:pointer;background:var(--studio-surface);transition:border-color .2s,transform .15s,box-shadow .2s;}
    .zc-tpl:hover{transform:translateY(-3px);box-shadow:0 18px 38px -20px rgba(0,0,0,.45);}
    .zc-tpl input{position:absolute;opacity:0;pointer-events:none;}
    .zc-tpl.is-active{border-color:var(--studio-accent);box-shadow:0 0 0 4px color-mix(in srgb, var(--studio-accent) 20%, transparent),0 20px 40px -22px rgba(0,0,0,.4);}
    .zc-tpl__check{position:absolute;top:9px;right:9px;width:24px;height:24px;border-radius:50%;background:var(--studio-accent);color:#fff;display:none;place-items:center;z-index:4;box-shadow:0 4px 10px -3px rgba(0,0,0,.4);}
    .zc-tpl.is-active .zc-tpl__check{display:grid;}
    .zc-tpl__prev{position:relative;height:118px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:12px;text-align:center;overflow:hidden;}
    .zc-tpl__prev .h{font-weight:800;line-height:1.1;} .zc-tpl__prev .b{font-size:0.62rem;padding:4px 12px;border-radius:999px;font-weight:800;}
    .zc-tpl__meta{padding:0.8rem 0.9rem;border-top:1px solid var(--studio-border);}
    .zc-tpl__meta b{font-size:0.88rem;} .zc-tpl__meta p{font-size:0.73rem;color:var(--studio-muted);margin-top:3px;line-height:1.4;}
    .p-classic{background:linear-gradient(135deg,#eaf6ee,#cfe9d7);} .p-classic .h{color:#14532d;font-size:0.82rem;} .p-classic .b{background:#1f7a3d;color:#fff;}
    .p-bold{background:linear-gradient(135deg,#0b1220,#1e293b);} .p-bold .h{color:#fff;font-size:1rem;letter-spacing:-.01em;} .p-bold .b{background:linear-gradient(90deg,#f2a20c,#f4b840);color:#3a2600;box-shadow:0 0 18px rgba(242,162,12,.5);}
    .p-minimal{background:#fbfaf7;} .p-minimal .h{color:#111;font-size:0.86rem;font-family:Georgia,serif;font-style:italic;} .p-minimal .b{background:transparent;color:#111;border:1px solid #111;border-radius:0;}
    .p-sales{background:linear-gradient(135deg,#fff2d6,#ffdca6);} .p-sales .h{color:#7a4c00;font-size:0.8rem;} .p-sales .b{background:#e0483d;color:#fff;} .p-sales .r{position:absolute;top:8px;left:8px;background:#e0483d;color:#fff;font-size:0.54rem;font-weight:800;padding:2px 8px;border-radius:5px;z-index:2;}
    .zc-hero-prev{width:190px;height:100px;border-radius:12px;object-fit:cover;border:1px solid var(--studio-border);display:none;box-shadow:0 10px 24px -14px rgba(0,0,0,.4);}
    .zc-hero-prev.show{display:block;}
    .zc-file{display:inline-flex;}
    /* product picker */
    .zc-pp{position:relative;}
    .zc-pp__box{position:relative;}
    .zc-pp__box svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--studio-muted);pointer-events:none;}
    .zc-pp__box input{padding-left:2.5rem;}
    .zc-pp__results{position:absolute;z-index:30;left:0;right:0;top:calc(100% + 6px);background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:12px;box-shadow:0 24px 50px -22px rgba(0,0,0,.45);max-height:320px;overflow-y:auto;display:none;}
    .zc-pp__results.show{display:block;}
    .zc-pp__item{display:flex;align-items:center;gap:12px;padding:0.6rem 0.8rem;cursor:pointer;border-bottom:1px solid var(--studio-border);}
    .zc-pp__item:last-child{border-bottom:none;} .zc-pp__item:hover{background:var(--studio-surface-soft);}
    .zc-pp__thumb{width:44px;height:44px;border-radius:9px;object-fit:cover;background:var(--studio-surface-soft);border:1px solid var(--studio-border);flex:none;display:grid;place-items:center;color:var(--studio-muted);font-size:0.6rem;font-weight:800;}
    .zc-pp__nm{font-weight:700;font-size:0.86rem;line-height:1.2;} .zc-pp__sub{font-size:0.72rem;color:var(--studio-muted);margin-top:2px;}
    .zc-pp__price{margin-left:auto;font-weight:800;color:var(--studio-accent);font-size:0.85rem;white-space:nowrap;}
    .zc-pp__empty{padding:0.9rem;text-align:center;color:var(--studio-muted);font-size:0.82rem;}
    .zc-pp__sel{display:none;align-items:center;gap:12px;margin-top:0.6rem;padding:0.6rem 0.8rem;border:1px solid var(--studio-accent);background:color-mix(in srgb,var(--studio-accent) 8%,transparent);border-radius:12px;}
    .zc-pp__sel.show{display:flex;}
    .zc-pp__x{margin-left:auto;border:none;background:var(--studio-surface-soft);width:28px;height:28px;border-radius:8px;cursor:pointer;color:var(--studio-muted);font-size:1rem;line-height:1;}
    .zc-pp__custom{background:none;border:none;color:var(--studio-accent);font-size:0.78rem;font-weight:700;cursor:pointer;padding:0.4rem 0;margin-top:2px;}
    /* suggested-product chips */
    .zc-sp-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:0.7rem;}
    .zc-sp-chip{display:inline-flex;align-items:center;gap:8px;padding:0.35rem 0.4rem 0.35rem 0.5rem;border:1px solid var(--studio-border);border-radius:11px;background:var(--studio-surface-soft);font-size:0.82rem;font-weight:700;}
    .zc-sp-chip img{width:30px;height:30px;border-radius:7px;object-fit:cover;}
    .zc-sp-chip .ph{width:30px;height:30px;border-radius:7px;background:var(--studio-border);display:grid;place-items:center;font-size:0.55rem;color:var(--studio-muted);}
    .zc-sp-x{border:none;background:transparent;color:var(--studio-muted);cursor:pointer;font-size:1.1rem;line-height:1;padding:0 2px;}
    .zc-sp-x:hover{color:#c0392b;}
    /* sticky publish bar */
    .zc-lf-bar{position:sticky;bottom:-1px;margin:1.6rem -2rem -1.75rem;padding:1rem 2rem;background:color-mix(in srgb,var(--studio-surface) 88%,transparent);backdrop-filter:blur(8px);border-top:1px solid var(--studio-border);display:flex;justify-content:flex-end;gap:0.8rem;border-radius:0 0 var(--radius-lg,16px) var(--radius-lg,16px);}
    .zc-lf-gallery{display:flex;flex-wrap:wrap;gap:8px;margin-top:9px;}
    .zc-lf-gallery img{width:64px;height:64px;object-fit:cover;border-radius:9px;border:1px solid var(--studio-border);}
    .zc-lf-check2{display:inline-flex;align-items:center;gap:0.6rem;font-weight:600;font-size:0.85rem;cursor:pointer;}
    .zc-lf-check2 input{width:1.1rem;height:1.1rem;accent-color:var(--studio-accent);}
</style>@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('landing.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;overflow:visible;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $page->exists ? 'Edit' : 'Add' }} Landing Page</h1>

        <form class="zc-lf" method="POST" enctype="multipart/form-data" action="{{ $page->exists ? route('landing.update', $page) : route('landing.store') }}" style="margin-top:0.5rem;">
            @csrf @if($page->exists) @method('PUT') @endif

            <div class="zc-lf-sec"><span>1</span> Choose a template style</div>
            <div class="zc-tpl-grid">
                @php $curr = old('template', $page->template ?: 'classic'); @endphp
                @foreach (\App\Modules\LandingPage\Models\LandingPage::TEMPLATES as $key => $meta)
                    <label class="zc-tpl {{ $curr === $key ? 'is-active' : '' }}" data-tpl>
                        <input type="radio" name="template" value="{{ $key }}" @checked($curr === $key)>
                        <span class="zc-tpl__check"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
                        <span class="zc-tpl__prev p-{{ $key }}">
                            @if ($key === 'sales')<span class="r">OFFER</span>@endif
                            <span class="h">{{ $meta['label'] }}</span>
                            <span class="b">Order Now</span>
                        </span>
                        <span class="zc-tpl__meta"><b>{{ $meta['label'] }}</b><p>{{ $meta['desc'] }}</p></span>
                    </label>
                @endforeach
            </div>

            <div class="zc-lf-sec"><span>2</span> Page details</div>
            <div class="zc-lf-row"><label>Page Name <span class="req">*</span> <small>internal title</small></label><input name="title" class="studio-form-control" value="{{ old('title', $page->title) }}" placeholder="e.g. Winter Shawl Promo" required></div>
            <div class="zc-lf-row"><label>Link (slug) <small>leave blank to auto-generate</small></label><input name="slug" class="studio-form-control" value="{{ old('slug', $page->slug) }}" placeholder="winter-shawl-promo"></div>
            <div class="zc-lf-row"><label>Status</label><select name="status" class="studio-form-control" style="max-width:16rem;"><option value="active" @selected(old('status',$page->status)==='active')>Active</option><option value="inactive" @selected(old('status',$page->status)==='inactive')>Inactive</option></select></div>

            <div class="zc-lf-sec"><span>3</span> Hero</div>
            <div class="zc-lf-row"><label>Hero Title</label><input name="hero_title" class="studio-form-control" value="{{ old('hero_title', $page->hero_title) }}" placeholder="Big headline shown at the top"></div>
            <div class="zc-lf-row"><label>Hero Subtitle</label><textarea name="hero_subtitle" class="studio-form-control" rows="2" placeholder="supporting line under the headline">{{ old('hero_subtitle', $page->hero_subtitle) }}</textarea></div>
            <div class="zc-lf-row"><label>Hero Image</label>
                <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                    @php $hero = $mediaUrl($page->heroImage ?? null); @endphp
                    <img id="zc-hero-prev" class="zc-hero-prev {{ $hero ? 'show' : '' }}" src="{{ $hero ?: '' }}" alt="">
                    <input type="file" name="hero_image" accept="image/*" class="studio-form-control zc-file" id="zc-hero-img" style="max-width:22rem;">
                </div>
            </div>

            <div class="zc-lf-sec"><span>4</span> Body &amp; order button</div>
            <div class="zc-lf-row"><label>Content <small>HTML supported</small></label><textarea name="content" class="studio-form-control" rows="8" placeholder="Describe the product / offer. Basic HTML like &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;&lt;li&gt; works.">{{ old('content', $page->content) }}</textarea></div>
            <div class="zc-lf-row">
                <label>Button Text</label>
                <div>
                    <input name="cta_text" class="studio-form-control" value="{{ old('cta_text', $page->cta_text) }}" placeholder="e.g. Order Now" style="max-width:22rem;">
                    <div style="font-size:0.75rem;color:var(--studio-muted);margin-top:6px;">Leave the product below empty and this button goes to the <b>cart checkout</b>.</div>
                </div>
            </div>
            <div class="zc-lf-row">
                <label>Buy Now product <small>pick one → button goes straight to its checkout</small></label>
                <div class="zc-pp">
                    <input type="hidden" name="cta_url" id="cta_url" value="{{ old('cta_url', $page->cta_url) }}">
                    <div class="zc-pp__box">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="text" id="cta-search" class="studio-form-control" placeholder="Type product name or SKU…" autocomplete="off">
                        <div class="zc-pp__results" id="cta-results"></div>
                    </div>
                    <div class="zc-pp__sel" id="cta-sel"></div>
                    <button type="button" class="zc-pp__custom" id="cta-custom-toggle">or paste a custom link instead</button>
                    <input type="text" id="cta-custom" class="studio-form-control" style="display:none;margin-top:0.4rem;" placeholder="/products or a full URL">
                </div>
            </div>

            <div class="zc-lf-sec"><span>5</span> Suggested products <span style="width:auto;height:auto;background:none;box-shadow:none;color:var(--studio-muted);font-weight:500;font-size:0.78rem;">— shown on the page with Add to Cart</span></div>
            <div class="zc-lf-row">
                <label>Products <small>search &amp; add several</small></label>
                <div class="zc-pp">
                    <div class="zc-pp__box">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="text" id="sp-search" class="studio-form-control" placeholder="Type product name or SKU to add…" autocomplete="off">
                        <div class="zc-pp__results" id="sp-results"></div>
                    </div>
                    <div id="sp-chips" class="zc-sp-chips">
                        @foreach ($suggested as $s)
                            <span class="zc-sp-chip" data-id="{{ $s['id'] }}">
                                <input type="hidden" name="suggested_products[]" value="{{ $s['id'] }}">
                                @if ($s['thumb'])<img src="{{ $s['thumb'] }}" alt="">@else<span class="ph">IMG</span>@endif
                                {{ $s['name'] }}
                                <button type="button" class="zc-sp-x" aria-label="Remove">&times;</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="zc-lf-sec"><span>6</span> Rich sections <span style="width:auto;height:auto;background:none;box-shadow:none;color:var(--studio-muted);font-weight:500;font-size:0.78rem;">— gallery · video · features · reviews · contact</span></div>
            <div class="zc-lf-row">
                <label>Gallery Images <small>product photos, shown in a slider</small></label>
                <div>
                    <input type="file" name="gallery[]" accept="image/*" multiple class="studio-form-control" style="max-width:26rem;">
                    @if (!empty($galleryImages))
                        <div class="zc-lf-gallery">@foreach ($galleryImages as $g)<img src="{{ $g['url'] }}" alt="">@endforeach</div>
                        <label class="zc-lf-check2" style="margin-top:6px;"><input type="checkbox" name="clear_gallery" value="1"> Remove the current gallery images</label>
                    @endif
                    <div style="font-size:0.72rem;color:var(--studio-muted);margin-top:5px;">New uploads are added to the gallery.</div>
                </div>
            </div>
            <div class="zc-lf-row"><label>Video URL <small>YouTube link</small></label><input name="video_url" class="studio-form-control" value="{{ old('video_url', $page->video_url) }}" placeholder="https://www.youtube.com/watch?v=..."></div>
            <div class="zc-lf-row"><label>Features <small>one per line — shown as a ✓ checklist</small></label><textarea name="features" class="studio-form-control" rows="5" placeholder="100% cotton fabric&#10;Free size chart&#10;Cash on delivery">{{ old('features', $page->features) }}</textarea></div>
            <div class="zc-lf-row">
                <label>Contact buttons <small>call &amp; WhatsApp</small></label>
                <div class="zc-lf-2">
                    <input name="contact_phone" class="studio-form-control" value="{{ old('contact_phone', $page->contact_phone) }}" placeholder="Call number e.g. 018XXXXXXXX">
                    <input name="whatsapp_number" class="studio-form-control" value="{{ old('whatsapp_number', $page->whatsapp_number) }}" placeholder="WhatsApp number">
                </div>
            </div>
            <div class="zc-lf-row"><label>Customer Reviews</label><label class="zc-lf-check2"><input type="checkbox" name="show_reviews" value="1" @checked(old('show_reviews', $page->show_reviews))> Show approved reviews of the selected products</label></div>

            <div class="zc-lf-sec"><span>7</span> SEO <span style="width:auto;height:auto;background:none;box-shadow:none;color:var(--studio-muted);font-weight:500;font-size:0.78rem;">(optional)</span></div>
            <div class="zc-lf-row"><label>Meta Title</label><input name="meta_title" class="studio-form-control" value="{{ old('meta_title', $page->meta_title) }}"></div>
            <div class="zc-lf-row"><label>Meta Description</label><textarea name="meta_description" class="studio-form-control" rows="2">{{ old('meta_description', $page->meta_description) }}</textarea></div>

            <div class="zc-lf-bar">
                <a href="{{ route('landing.index') }}" class="studio-command-button">Cancel</a>
                <button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.4rem;">{{ $page->exists ? 'Update' : 'Publish' }} Landing Page</button>
            </div>
        </form>
    </div>
</div>

@push('studio-scripts')
<script>
    (function(){
        // template picker
        document.querySelectorAll('[data-tpl]').forEach(function(card){
            card.addEventListener('click', function(){
                document.querySelectorAll('[data-tpl]').forEach(function(c){ c.classList.remove('is-active'); });
                card.classList.add('is-active');
                card.querySelector('input').checked = true;
            });
        });
        // hero preview
        var himg=document.getElementById('zc-hero-img'), hprev=document.getElementById('zc-hero-prev');
        if(himg){ himg.addEventListener('change', function(){ var f=himg.files[0]; if(f){ hprev.src=URL.createObjectURL(f); hprev.classList.add('show'); } }); }

        // ---- AJAX product picker for the CTA link ----
        var url=document.getElementById('cta_url'), search=document.getElementById('cta-search'),
            results=document.getElementById('cta-results'), sel=document.getElementById('cta-sel'),
            customToggle=document.getElementById('cta-custom-toggle'), custom=document.getElementById('cta-custom');
        var endpoint='{{ route('landing.products.search') }}';

        function chip(html){ sel.innerHTML=html+'<button type="button" class="zc-pp__x" id="cta-clear">&times;</button>'; sel.classList.add('show');
            document.getElementById('cta-clear').addEventListener('click', clearSel); }
        function clearSel(){ url.value=''; sel.classList.remove('show'); sel.innerHTML=''; custom.value=''; }
        function esc(s){ return (s||'').replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

        // prefill on edit
        @if (old('cta_url', $page->cta_url))
            chip('<span class="zc-pp__thumb">🔗</span><span><span class="zc-pp__nm">Current link</span><span class="zc-pp__sub">{{ old('cta_url', $page->cta_url) }}</span></span>');
        @endif

        var t=null;
        search.addEventListener('input', function(){
            clearTimeout(t); var q=search.value.trim();
            if(q.length<1){ results.classList.remove('show'); results.innerHTML=''; return; }
            t=setTimeout(function(){
                fetch(endpoint+'?q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
                    .then(function(r){return r.json();}).then(function(d){
                        if(!d.results||!d.results.length){ results.innerHTML='<div class="zc-pp__empty">No products match “'+esc(q)+'”</div>'; results.classList.add('show'); return; }
                        results.innerHTML=d.results.map(function(p){
                            var th=p.thumb?'<img class="zc-pp__thumb" src="'+p.thumb+'" alt="">':'<span class="zc-pp__thumb">IMG</span>';
                            return '<div class="zc-pp__item" data-url="'+esc(p.url)+'" data-name="'+esc(p.name)+'" data-sku="'+esc(p.sku||'')+'" data-thumb="'+esc(p.thumb||'')+'" data-price="'+p.price+'">'+th+
                                '<span><span class="zc-pp__nm">'+esc(p.name)+'</span><span class="zc-pp__sub">SKU: '+esc(p.sku||'—')+'</span></span>'+
                                '<span class="zc-pp__price">৳'+Math.round(p.price)+'</span></div>';
                        }).join('');
                        results.classList.add('show');
                    });
            }, 250);
        });
        results.addEventListener('click', function(e){
            var it=e.target.closest('.zc-pp__item'); if(!it) return;
            url.value=it.getAttribute('data-url'); custom.value='';
            var th=it.getAttribute('data-thumb')?'<img class="zc-pp__thumb" src="'+it.getAttribute('data-thumb')+'" alt="">':'<span class="zc-pp__thumb">IMG</span>';
            chip(th+'<span><span class="zc-pp__nm">'+esc(it.getAttribute('data-name'))+'</span><span class="zc-pp__sub">SKU: '+esc(it.getAttribute('data-sku')||'—')+' · ৳'+Math.round(it.getAttribute('data-price'))+'</span></span>');
            results.classList.remove('show'); results.innerHTML=''; search.value='';
        });
        document.addEventListener('click', function(e){ if(!e.target.closest('.zc-pp__box')) results.classList.remove('show'); });

        // custom link fallback
        customToggle.addEventListener('click', function(){ custom.style.display = custom.style.display==='none'?'block':'none'; if(custom.style.display==='block') custom.focus(); });
        custom.addEventListener('input', function(){ url.value=custom.value.trim();
            if(custom.value.trim()) chip('<span class="zc-pp__thumb">🔗</span><span><span class="zc-pp__nm">Custom link</span><span class="zc-pp__sub">'+esc(custom.value.trim())+'</span></span>');
            else { sel.classList.remove('show'); sel.innerHTML=''; } });

        // ---- Suggested products (multi-select) ----
        var spSearch=document.getElementById('sp-search'), spResults=document.getElementById('sp-results'), spChips=document.getElementById('sp-chips');
        function spHas(id){ return spChips.querySelector('.zc-sp-chip[data-id="'+id+'"]'); }
        function spAdd(p){
            if(spHas(p.id)) return;
            var th=p.thumb?'<img src="'+p.thumb+'" alt="">':'<span class="ph">IMG</span>';
            var chip=document.createElement('span'); chip.className='zc-sp-chip'; chip.setAttribute('data-id',p.id);
            chip.innerHTML='<input type="hidden" name="suggested_products[]" value="'+p.id+'">'+th+' '+esc(p.name)+' <button type="button" class="zc-sp-x" aria-label="Remove">&times;</button>';
            spChips.appendChild(chip);
        }
        var spT=null;
        spSearch.addEventListener('input', function(){
            clearTimeout(spT); var q=spSearch.value.trim();
            if(q.length<1){ spResults.classList.remove('show'); spResults.innerHTML=''; return; }
            spT=setTimeout(function(){
                fetch(endpoint+'?q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
                    .then(function(r){return r.json();}).then(function(d){
                        if(!d.results||!d.results.length){ spResults.innerHTML='<div class="zc-pp__empty">No products match “'+esc(q)+'”</div>'; spResults.classList.add('show'); return; }
                        spResults.innerHTML=d.results.map(function(p){
                            var th=p.thumb?'<img class="zc-pp__thumb" src="'+p.thumb+'" alt="">':'<span class="zc-pp__thumb">IMG</span>';
                            return '<div class="zc-pp__item" data-id="'+p.id+'" data-name="'+esc(p.name)+'" data-thumb="'+esc(p.thumb||'')+'">'+th+
                                '<span><span class="zc-pp__nm">'+esc(p.name)+'</span><span class="zc-pp__sub">SKU: '+esc(p.sku||'—')+'</span></span>'+
                                '<span class="zc-pp__price">৳'+Math.round(p.price)+'</span></div>';
                        }).join('');
                        spResults.classList.add('show');
                    });
            }, 250);
        });
        spResults.addEventListener('click', function(e){
            var it=e.target.closest('.zc-pp__item'); if(!it) return;
            spAdd({id:it.getAttribute('data-id'), name:it.getAttribute('data-name'), thumb:it.getAttribute('data-thumb')});
            spResults.classList.remove('show'); spResults.innerHTML=''; spSearch.value=''; spSearch.focus();
        });
        spChips.addEventListener('click', function(e){ var x=e.target.closest('.zc-sp-x'); if(x) x.closest('.zc-sp-chip').remove(); });
        document.addEventListener('click', function(e){ if(!e.target.closest('#sp-search') && !e.target.closest('#sp-results')) spResults.classList.remove('show'); });
    })();
</script>
@endpush
@endsection
