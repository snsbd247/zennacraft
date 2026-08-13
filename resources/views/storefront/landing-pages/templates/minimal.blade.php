{{-- Minimal: airy, editorial, serif headline, understated outline CTA --}}
<div class="zc-lpm">
    <section class="zc-lpm__hero">
        <span class="zc-lpm__kicker">— {{ $storeName ?? '' }}</span>
        <h1>{{ $landingPage->hero_title ?: $landingPage->title }}</h1>
        @if ($landingPage->hero_subtitle)<p>{{ $landingPage->hero_subtitle }}</p>@endif
        <a href="{{ $ctaUrl }}" class="zc-lpm__cta">{{ $ctaText }}</a>
    </section>
    @if ($heroImg)<div class="zc-lpm__fig"><img src="{{ $heroImg }}" alt="{{ $landingPage->hero_title ?: $landingPage->title }}"></div>@endif
    @if ($landingPage->content)
        <section class="zc-lpm__body">{!! $landingPage->content !!}</section>
        <div class="zc-lpm__foot"><a href="{{ $ctaUrl }}" class="zc-lpm__cta">{{ $ctaText }}</a></div>
    @endif
</div>
<style>
    .zc-lpm{background:#fbfaf7;color:#1a1a1a;}
    .zc-lpm__hero{max-width:760px;margin:0 auto;text-align:center;padding:96px 24px 40px;}
    .zc-lpm__kicker{letter-spacing:.24em;text-transform:uppercase;font-size:12px;font-weight:700;color:#8a8578;}
    .zc-lpm__hero h1{font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:clamp(34px,5.5vw,60px);line-height:1.14;margin-top:22px;letter-spacing:-.01em;}
    .zc-lpm__hero p{margin-top:20px;font-size:19px;line-height:1.75;color:#55524b;max-width:600px;margin-left:auto;margin-right:auto;}
    .zc-lpm__cta{display:inline-block;margin-top:30px;border:1px solid #1a1a1a;color:#1a1a1a;font-weight:600;letter-spacing:.05em;padding:14px 40px;text-decoration:none;transition:background .2s,color .2s;}
    .zc-lpm__cta:hover{background:#1a1a1a;color:#fff;}
    .zc-lpm__fig{max-width:1000px;margin:20px auto 0;padding:0 24px;}
    .zc-lpm__fig img{width:100%;border-radius:2px;display:block;}
    .zc-lpm__body{max-width:680px;margin:0 auto;padding:56px 24px;line-height:2;font-size:18px;color:#33312c;}
    .zc-lpm__body h2,.zc-lpm__body h3{font-family:Georgia,serif;font-weight:400;margin:1em 0 .3em;}
    .zc-lpm__body img{max-width:100%;border-radius:2px;}
    .zc-lpm__foot{text-align:center;padding:0 24px 90px;}
</style>
