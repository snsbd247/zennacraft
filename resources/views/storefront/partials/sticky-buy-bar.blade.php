<div class="zc-mobile-product-cta">
    <form method="GET" action="{{ route('checkout') }}" class="flex items-center gap-3">
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <input type="hidden" name="quantity" value="1" data-sticky-quantity-input>
        <input type="hidden" name="variant_id" value="" data-selected-variant-input>
        <div class="min-w-0 flex-1">
            <div class="truncate text-xs font-bold text-slate-500" data-selected-offer-name>Single Piece</div>
            <div class="truncate text-lg font-black text-slate-950" data-selected-offer-price>{{ number_format((float) $product->price, 2) }}</div>
        </div>
        <button type="submit" class="zc-btn zc-btn-primary zc-ds-btn zc-ds-btn--primary shrink-0">Buy Now</button>
    </form>
</div>
<div class="zc-mobile-cta-spacer"></div>
