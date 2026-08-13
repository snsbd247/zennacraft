{{-- Sales Page: offer badge, benefit points, sticky mobile CTA, repeated buttons --}}
<div class="zc-lps">
    <section class="zc-lps__hero">
        <div class="zc-lps__hero-in">
            <div class="zc-lps__wrap">
                <div class="zc-lps__copy">
                    <span class="zc-lps__badge">🔥 Special Offer</span>
                    <h1>{{ $landingPage->hero_title ?: $landingPage->title }}</h1>
                    @if ($landingPage->hero_subtitle)<p>{{ $landingPage->hero_subtitle }}</p>@endif
                    <div class="zc-lps__benefits">
                        <span>✓ Cash on Delivery</span><span>✓ 100% Genuine</span><span>✓ Fast Delivery</span><span>✓ Easy Exchange</span>
                    </div>
                    <a href="{{ $ctaUrl }}" class="zc-lps__cta">{{ $ctaText }}</a>
                </div>
                @if ($heroImg)<div class="zc-lps__media"><img src="{{ $heroImg }}" alt="{{ $landingPage->hero_title ?: $landingPage->title }}"></div>@endif
            </div>
        </div>
    </section>
    @if ($landingPage->content)
        <section class="zc-lps__body"><div class="zc-lps__card">{!! $landingPage->content !!}</div></section>
    @endif
    <section class="zc-lps__close">
        <h2>{{ $landingPage->hero_title ?: 'Order now — pay when it arrives' }}</h2>
        <a href="{{ $ctaUrl }}" class="zc-lps__cta zc-lps__cta--lg">{{ $ctaText }}</a>
    </section>
    <a href="{{ $ctaUrl }}" class="zc-lps__sticky">{{ $ctaText }}</a>
</div>
<style>
    .zc-lps{background:#fff7ea;color:#3a2b12;padding-bottom:72px;}
    .zc-lps__hero{background:linear-gradient(135deg,#fff1d6,#ffe0b0);padding:48px 20px;}
    .zc-lps__hero-in{max-width:1080px;margin:0 auto;}
    .zc-lps__wrap{display:grid;grid-template-columns:1.1fr .9fr;gap:36px;align-items:center;}
    @media(max-width:800px){.zc-lps__wrap{grid-template-columns:1fr;}}
    .zc-lps__badge{display:inline-block;background:#e0483d;color:#fff;font-weight:800;font-size:13px;padding:7px 16px;border-radius:999px;box-shadow:0 10px 22px -10px rgba(224,72,61,.7);}
    .zc-lps__copy h1{font-size:clamp(28px,4.4vw,46px);font-weight:900;line-height:1.12;margin-top:16px;color:#5a3d0e;}
    .zc-lps__copy p{margin-top:14px;font-size:17px;line-height:1.65;color:#7a5a24;}
    .zc-lps__benefits{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:20px;font-weight:700;color:#3f7a3d;font-size:14.5px;}
    .zc-lps__media img{width:100%;border-radius:18px;box-shadow:0 30px 60px -30px rgba(122,76,0,.55);}
    .zc-lps__cta{display:inline-block;margin-top:24px;background:linear-gradient(90deg,#e0483d,#f0633a);color:#fff;font-weight:900;font-size:18px;padding:16px 46px;border-radius:12px;text-decoration:none;box-shadow:0 18px 34px -14px rgba(224,72,61,.8);transition:transform .15s;}
    .zc-lps__cta:hover{transform:translateY(-2px);}
    .zc-lps__body{max-width:820px;margin:0 auto;padding:44px 20px;}
    .zc-lps__card{background:#fff;border:1px solid #f0e2c8;border-radius:18px;padding:32px;line-height:1.85;font-size:16.5px;color:#4a3a1e;box-shadow:0 24px 50px -34px rgba(122,76,0,.4);}
    .zc-lps__card h2,.zc-lps__card h3{color:#5a3d0e;font-weight:800;margin:.6em 0 .3em;}
    .zc-lps__card img{max-width:100%;border-radius:12px;}
    .zc-lps__close{text-align:center;padding:20px 20px 10px;}
    .zc-lps__close h2{font-size:clamp(22px,3.4vw,32px);font-weight:900;color:#5a3d0e;margin-bottom:18px;}
    .zc-lps__cta--lg{font-size:20px;padding:18px 56px;}
    .zc-lps__sticky{display:none;position:fixed;left:14px;right:14px;bottom:14px;z-index:50;text-align:center;background:linear-gradient(90deg,#e0483d,#f0633a);color:#fff;font-weight:900;padding:15px;border-radius:12px;text-decoration:none;box-shadow:0 16px 30px -10px rgba(224,72,61,.8);}
    @media(max-width:640px){.zc-lps__sticky{display:block;}}
</style>
