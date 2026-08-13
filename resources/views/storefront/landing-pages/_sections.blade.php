{{-- Rich landing sections: gallery · call/WhatsApp · video · features · reviews. Each renders only if it has data. --}}
@php
    $features = collect(preg_split('/\r\n|\r|\n/', (string) $landingPage->features))->map(fn ($l) => trim($l))->filter()->values();
    $videoId = null;
    if ($landingPage->video_url && preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_\-]{6,})#', $landingPage->video_url, $m)) {
        $videoId = $m[1];
    }
    $call = trim((string) $landingPage->contact_phone);
    $wa = preg_replace('/\D/', '', (string) $landingPage->whatsapp_number);
    $hasAny = $galleryImages->isNotEmpty() || $videoId || $features->isNotEmpty() || $reviews->isNotEmpty() || $call || $wa;
@endphp
@if ($hasAny)
<div class="zc-ls" style="--acc:{{ $accent }};">
    @if ($galleryImages->isNotEmpty())
        <section class="zc-ls-sec"><div class="zc-ls-wrap">
            <div class="zc-ls-carousel">
                <button type="button" class="zc-ls-nav prev" data-ls-prev aria-label="Previous">‹</button>
                <div class="zc-ls-track" data-ls-track>
                    @foreach ($galleryImages as $g)<div class="zc-ls-gimg"><img src="{{ $mediaUrl($g) }}" alt="{{ $landingPage->title }}" loading="lazy"></div>@endforeach
                </div>
                <button type="button" class="zc-ls-nav next" data-ls-next aria-label="Next">›</button>
            </div>
            <div class="zc-ls-order"><a href="#zc-order" class="zc-ls-btn">{{ $ctaText }}</a></div>
        </div></section>
    @endif

    @if ($call || $wa)
        <section class="zc-ls-contact"><div class="zc-ls-wrap zc-ls-contact__in">
            <span class="lead">যে কোনো প্রয়োজনে যোগাযোগ করুন</span>
            <div class="zc-ls-cbtns">
                @if ($call)<a href="tel:{{ $call }}" class="zc-ls-cbtn call"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg> {{ $call }}</a>@endif
                @if ($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="zc-ls-cbtn wa"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.8 1-.3.2-.5 0a6.5 6.5 0 0 1-1.9-1.2 7.3 7.3 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.4.2-.4v-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3A2.8 2.8 0 0 0 5.9 10a5 5 0 0 0 1 2.6 11 11 0 0 0 4.2 3.7c2 .8 2 .5 2.4.5a2.5 2.5 0 0 0 1.6-1.1 2 2 0 0 0 .1-1.1c0-.1-.2-.2-.4-.3Z"/></svg> WhatsApp</a>@endif
            </div>
        </div></section>
    @endif

    @if ($videoId)
        <section class="zc-ls-sec"><div class="zc-ls-wrap"><div class="zc-ls-video"><iframe src="https://www.youtube.com/embed/{{ $videoId }}" title="Video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div></div></section>
    @endif

    @if ($features->isNotEmpty())
        <section class="zc-ls-sec"><div class="zc-ls-wrap zc-ls-narrow">
            <ul class="zc-ls-features">
                @foreach ($features as $f)<li><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10" opacity=".15" fill="currentColor" stroke="none"/><path d="M7 12.5l3.2 3.2L17 8.5"/></svg> {{ $f }}</li>@endforeach
            </ul>
            <div class="zc-ls-order"><a href="#zc-order" class="zc-ls-btn">{{ $ctaText }}</a></div>
        </div></section>
    @endif

    @if ($reviews->isNotEmpty())
        <section class="zc-ls-sec zc-ls-reviews"><div class="zc-ls-wrap">
            <h2 class="zc-ls-h2">Customer Reviews</h2>
            <div class="zc-ls-carousel">
                <button type="button" class="zc-ls-nav prev" data-ls-prev aria-label="Previous">‹</button>
                <div class="zc-ls-track" data-ls-track>
                    @foreach ($reviews as $r)
                        @php $rt = (int) $r->rating; @endphp
                        <div class="zc-ls-review">
                            <div class="stars">{{ str_repeat('★', max(0, min(5, $rt))) }}{{ str_repeat('☆', max(0, 5 - $rt)) }}</div>
                            <p>“{{ \Illuminate\Support\Str::limit($r->body, 170) }}”</p>
                            <div class="who"><span class="av">{{ strtoupper(mb_substr($r->reviewer_name ?: 'Z', 0, 1)) }}</span><span><b>{{ $r->reviewer_name ?: 'Verified customer' }}</b><small>✓ Verified purchase</small></span></div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="zc-ls-nav next" data-ls-next aria-label="Next">›</button>
            </div>
        </div></section>
    @endif
</div>

<style>
    .zc-ls{background:#fff;}
    .zc-ls-sec{padding:34px 16px;}
    .zc-ls-wrap{max-width:960px;margin:0 auto;}
    .zc-ls-narrow{max-width:640px;}
    .zc-ls-h2{text-align:center;font-weight:900;font-size:clamp(20px,3vw,28px);color:#14532d;margin-bottom:20px;}
    .zc-ls-carousel{position:relative;}
    .zc-ls-track{display:flex;gap:12px;overflow-x:auto;scroll-behavior:smooth;scroll-snap-type:x mandatory;padding-bottom:6px;scrollbar-width:none;}
    .zc-ls-track::-webkit-scrollbar{display:none;}
    .zc-ls-gimg{flex:0 0 clamp(200px,42%,300px);scroll-snap-align:start;border-radius:14px;overflow:hidden;border:1px solid #eee;background:#f6f6f4;}
    .zc-ls-gimg img{width:100%;height:100%;aspect-ratio:1/1;object-fit:cover;display:block;}
    .zc-ls-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;width:38px;height:38px;border-radius:50%;border:none;background:#fff;box-shadow:0 6px 18px -6px rgba(0,0,0,.35);color:var(--acc);font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center;}
    .zc-ls-nav.prev{left:-6px;} .zc-ls-nav.next{right:-6px;}
    @media(max-width:640px){.zc-ls-nav{display:none;}}
    .zc-ls-order{text-align:center;margin-top:24px;}
    .zc-ls-btn{display:inline-block;background:var(--acc);color:#fff;font-weight:800;padding:14px 40px;border-radius:999px;text-decoration:none;box-shadow:0 14px 28px -14px var(--acc);}
    .zc-ls-contact{background:linear-gradient(120deg,#0e2a5e,#2563eb);padding:22px 16px;}
    .zc-ls-contact__in{display:flex;align-items:center;justify-content:center;gap:18px;flex-wrap:wrap;color:#fff;}
    .zc-ls-contact .lead{font-weight:800;font-size:16px;}
    .zc-ls-cbtns{display:flex;gap:10px;flex-wrap:wrap;}
    .zc-ls-cbtn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:999px;font-weight:800;text-decoration:none;font-size:14.5px;}
    .zc-ls-cbtn.call{background:#fff;color:#0e2a5e;} .zc-ls-cbtn.wa{background:#25d366;color:#fff;}
    .zc-ls-video{position:relative;padding-top:56.25%;border-radius:16px;overflow:hidden;box-shadow:0 24px 50px -30px rgba(0,0,0,.5);}
    .zc-ls-video iframe{position:absolute;inset:0;width:100%;height:100%;}
    .zc-ls-features{list-style:none;display:grid;gap:12px;}
    .zc-ls-features li{display:flex;align-items:flex-start;gap:10px;font-size:16px;color:#243b30;font-weight:600;line-height:1.5;}
    .zc-ls-features li svg{color:var(--acc);flex:none;margin-top:1px;}
    .zc-ls-reviews{background:#f4f7f5;}
    .zc-ls-review{flex:0 0 clamp(240px,80%,300px);scroll-snap-align:start;background:#fff;border:1px solid #e8eee8;border-radius:14px;padding:18px;box-shadow:0 12px 30px -24px rgba(0,0,0,.4);}
    .zc-ls-review .stars{color:#f5a623;font-size:15px;letter-spacing:1px;}
    .zc-ls-review p{margin:8px 0 14px;font-size:14px;color:#444;line-height:1.6;}
    .zc-ls-review .who{display:flex;align-items:center;gap:10px;}
    .zc-ls-review .av{width:38px;height:38px;border-radius:50%;background:var(--acc);color:#fff;display:grid;place-items:center;font-weight:800;flex:none;}
    .zc-ls-review .who b{display:block;font-size:13.5px;color:#222;} .zc-ls-review .who small{color:#1c8a4e;font-size:11.5px;}
</style>
<script>
(function(){
    document.querySelectorAll('.zc-ls-carousel').forEach(function(c){
        var track = c.querySelector('[data-ls-track]'); if(!track) return;
        var step = function(){ var first = track.querySelector(':scope > *'); return first ? first.getBoundingClientRect().width + 12 : 260; };
        var p = c.querySelector('[data-ls-prev]'), n = c.querySelector('[data-ls-next]');
        if(p) p.addEventListener('click', function(){ track.scrollBy({left:-step(),behavior:'smooth'}); });
        if(n) n.addEventListener('click', function(){ track.scrollBy({left:step(),behavior:'smooth'}); });
    });
})();
</script>
@endif
