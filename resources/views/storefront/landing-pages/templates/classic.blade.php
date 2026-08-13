{{-- Classic: clean centered hero over the image + readable content card + one CTA --}}
<div class="zc-lpc">
    <section class="zc-lpc__hero" @if ($heroImg) style="background-image:linear-gradient(160deg,rgba(9,30,17,.78),rgba(9,30,17,.45)),url('{{ $heroImg }}');" @endif>
        <div class="zc-lpc__hero-in">
            <span class="zc-lpc__eyebrow">{{ $storeName ?? '' }}</span>
            <h1>{{ $landingPage->hero_title ?: $landingPage->title }}</h1>
            @if ($landingPage->hero_subtitle)<p>{{ $landingPage->hero_subtitle }}</p>@endif
            <a href="{{ $ctaUrl }}" class="zc-lpc__cta">{{ $ctaText }}</a>
        </div>
    </section>
    @if ($landingPage->content)
        <section class="zc-lpc__body"><div class="zc-lpc__card">{!! $landingPage->content !!}</div>
            <div class="zc-lpc__foot"><a href="{{ $ctaUrl }}" class="zc-lpc__cta">{{ $ctaText }}</a></div>
        </section>
    @endif
</div>
<style>
    .zc-lpc__hero{min-height:64vh;display:grid;place-items:center;text-align:center;color:#fff;background:linear-gradient(160deg,#14532d,#1f7a3d);background-size:cover;background-position:center;padding:64px 20px;}
    .zc-lpc__hero-in{max-width:760px;}
    .zc-lpc__eyebrow{display:inline-block;letter-spacing:.28em;text-transform:uppercase;font-size:12px;font-weight:800;opacity:.85;margin-bottom:14px;}
    .zc-lpc__hero h1{font-size:clamp(30px,5vw,52px);font-weight:800;line-height:1.12;}
    .zc-lpc__hero p{margin-top:16px;font-size:clamp(15px,2vw,19px);opacity:.92;line-height:1.6;}
    .zc-lpc__cta{display:inline-block;margin-top:26px;background:linear-gradient(90deg,#f2a20c,#f4b840);color:#3a2600;font-weight:800;padding:14px 34px;border-radius:999px;text-decoration:none;box-shadow:0 16px 30px -14px rgba(242,162,12,.7);transition:transform .15s;}
    .zc-lpc__cta:hover{transform:translateY(-2px);}
    .zc-lpc__body{max-width:820px;margin:0 auto;padding:48px 20px;}
    .zc-lpc__card{background:#fff;border:1px solid #e7eee9;border-radius:18px;padding:34px;line-height:1.85;font-size:16.5px;color:#243b30;box-shadow:0 24px 50px -34px rgba(20,83,45,.4);}
    .zc-lpc__card h2,.zc-lpc__card h3{color:#14532d;font-weight:800;margin:.6em 0 .3em;}
    .zc-lpc__card img{max-width:100%;border-radius:12px;}
    .zc-lpc__foot{text-align:center;margin-top:26px;}
</style>
