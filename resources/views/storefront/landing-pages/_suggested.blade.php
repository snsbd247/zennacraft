{{-- Suggested products on a landing page — real product cards (Add to Cart / Buy now) + Order Now → checkout --}}
<section class="zc-lp-suggest">
    <div class="zc-wrap">
        <div class="zc-lp-suggest__head">
            <span class="zc-lp-suggest__eyebrow">Handpicked for you</span>
            <h2>Suggested products</h2>
            <p>Add what you love to the cart, then check out together — cash on delivery.</p>
        </div>
        <div class="zc-grid zc-grid--4">
            @foreach ($products as $product)@include('storefront.partials.product-card')@endforeach
        </div>
        <div class="zc-lp-suggest__cta">
            <a href="{{ $ctaUrl }}" class="zc-btn zc-btn--honey zc-btn--lg zc-fire">{{ $ctaText }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="18" height="18"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            <a href="{{ route('cart.index') }}" class="zc-lp-suggest__cartlink">View cart</a>
        </div>
    </div>
</section>
<style>
    .zc-lp-suggest{background:linear-gradient(180deg,#fbf7ef,#f3f8f4);padding:54px 0 64px;}
    .zc-lp-suggest__head{text-align:center;max-width:600px;margin:0 auto 30px;}
    .zc-lp-suggest__eyebrow{display:inline-block;letter-spacing:.2em;text-transform:uppercase;font-size:12px;font-weight:800;color:#1f7a3d;}
    .zc-lp-suggest__head h2{font-size:clamp(24px,3.4vw,34px);font-weight:800;color:#14532d;margin-top:8px;}
    .zc-lp-suggest__head p{margin-top:8px;color:#5f6b63;font-size:15px;}
    .zc-lp-suggest__cta{text-align:center;margin-top:36px;display:flex;flex-direction:column;align-items:center;gap:12px;}
    .zc-btn--lg{font-size:18px;padding:16px 46px;border-radius:999px;display:inline-flex;align-items:center;gap:10px;}
    .zc-lp-suggest__cartlink{color:#1f7a3d;font-weight:700;text-decoration:underline;font-size:14.5px;}
</style>
