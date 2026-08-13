{{-- Bold Promo: full-bleed dark hero, oversized headline, glowing gradient CTA --}}
<div class="zc-lpb">
    <section class="zc-lpb__hero">
        @if ($heroImg)<div class="zc-lpb__img" style="background-image:url('{{ $heroImg }}');"></div>@endif
        <div class="zc-lpb__hero-in">
            <span class="zc-lpb__tag">● Limited time</span>
            <h1>{{ $landingPage->hero_title ?: $landingPage->title }}</h1>
            @if ($landingPage->hero_subtitle)<p>{{ $landingPage->hero_subtitle }}</p>@endif
            <a href="{{ $ctaUrl }}" class="zc-lpb__cta">{{ $ctaText }} <span>→</span></a>
        </div>
    </section>
    @if ($landingPage->content)
        <section class="zc-lpb__body">{!! $landingPage->content !!}</section>
        <div class="zc-lpb__foot"><a href="{{ $ctaUrl }}" class="zc-lpb__cta">{{ $ctaText }} <span>→</span></a></div>
    @endif
</div>
<style>
    .zc-lpb{background:#0b1220;color:#e8edf6;}
    .zc-lpb__hero{position:relative;min-height:82vh;display:grid;place-items:center;text-align:center;overflow:hidden;padding:70px 20px;}
    .zc-lpb__img{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.42;filter:saturate(1.1);}
    .zc-lpb__hero::after{content:'';position:absolute;inset:0;background:radial-gradient(60% 60% at 50% 40%,rgba(242,162,12,.16),transparent 70%),linear-gradient(180deg,rgba(11,18,32,.5),rgba(11,18,32,.9));}
    .zc-lpb__hero-in{position:relative;z-index:2;max-width:900px;}
    .zc-lpb__tag{display:inline-block;color:#f4b840;font-weight:800;letter-spacing:.2em;text-transform:uppercase;font-size:12px;border:1px solid rgba(244,184,64,.4);padding:6px 14px;border-radius:999px;margin-bottom:20px;}
    .zc-lpb__hero h1{font-size:clamp(38px,7vw,84px);font-weight:900;line-height:1.02;letter-spacing:-.02em;background:linear-gradient(180deg,#fff,#b9c4d6);-webkit-background-clip:text;background-clip:text;color:transparent;}
    .zc-lpb__hero p{margin-top:20px;font-size:clamp(16px,2.2vw,22px);color:#aeb9cc;line-height:1.6;max-width:640px;margin-left:auto;margin-right:auto;}
    .zc-lpb__cta{display:inline-flex;align-items:center;gap:10px;margin-top:34px;background:linear-gradient(90deg,#f2a20c,#f4b840);color:#241700;font-weight:900;font-size:18px;padding:18px 44px;border-radius:14px;text-decoration:none;box-shadow:0 0 0 0 rgba(242,162,12,.6),0 22px 48px -18px rgba(242,162,12,.9);animation:zc-lpb-pulse 2.4s infinite;}
    .zc-lpb__cta span{transition:transform .18s;} .zc-lpb__cta:hover span{transform:translateX(5px);}
    @keyframes zc-lpb-pulse{0%{box-shadow:0 0 0 0 rgba(242,162,12,.5),0 22px 48px -18px rgba(242,162,12,.9);}70%{box-shadow:0 0 0 18px rgba(242,162,12,0),0 22px 48px -18px rgba(242,162,12,.9);}100%{box-shadow:0 0 0 0 rgba(242,162,12,0),0 22px 48px -18px rgba(242,162,12,.9);}}
    .zc-lpb__body{max-width:820px;margin:0 auto;padding:56px 22px;line-height:1.9;font-size:17px;color:#cdd6e6;}
    .zc-lpb__body h2,.zc-lpb__body h3{color:#fff;font-weight:800;margin:.7em 0 .3em;}
    .zc-lpb__body img{max-width:100%;border-radius:14px;}
    .zc-lpb__foot{text-align:center;padding:0 20px 70px;}
</style>
