@php
    $mediaUrl = $mediaUrl ?? fn ($m): ?string => null;
    $img = $mediaUrl($product->thumbnail ?? null);
    $price = (float) $product->price;
    $compare = $product->compare_price ? (float) $product->compare_price : null;
    $hasOff = $compare && $compare > $price;
    $save = $hasOff ? $compare - $price : null;
@endphp
<article @class(['pcard', 'pcard--best' => $product->is_bestseller ?? false])>
    <div class="pcard__media">
        <div class="pcard__badges">
            @if ($product->is_bestseller ?? false)<span class="zc-badge zc-badge--best">★ Best Seller</span>@endif
            @if ($hasOff)<span class="zc-badge zc-badge--save">Save ৳{{ number_format($save) }}</span>@endif
        </div>
        <button class="pcard__wish" type="button" aria-label="Save to wishlist" onclick="this.classList.toggle('is-on');this.querySelector('svg').style.fill=this.classList.contains('is-on')?'currentColor':'none';">
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.5-9.5-9C1 8 2.5 4.5 6 4.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 6.5C19 15.5 12 20 12 20Z"/></svg>
        </button>
        <a href="{{ route('storefront.product.show', $product->slug) }}" aria-label="{{ $product->name }}" style="position:absolute;inset:0;">
            @if ($img)
                <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
            @else
                <svg class="pcard__ph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg>
            @endif
        </a>
    </div>
    <div class="pcard__body">
        <span class="pcard__cat">{{ $product->category->name ?? 'Product' }}</span>
        <a href="{{ route('storefront.product.show', $product->slug) }}" class="pcard__title">{{ $product->name }}</a>
        <div class="pcard__price">
            <span class="pcard__now">৳{{ number_format($price) }}</span>
            @if ($hasOff)<span class="pcard__old">৳{{ number_format($compare) }}</span>@endif
        </div>
        <div class="pcard__actions two">
            <form method="POST" action="{{ route('cart.add') }}" data-cart-ajax>
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="zc-btn pcard__add zc-btn--block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg> Add
                </button>
            </form>
            <a href="{{ route('checkout', ['product_id' => $product->id, 'quantity' => 1]) }}" class="zc-btn zc-btn--honey zc-btn--block zc-fire">Buy now</a>
        </div>
    </div>
</article>
