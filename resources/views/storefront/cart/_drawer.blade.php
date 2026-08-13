{{-- Slide-out cart drawer body (re-rendered on every AJAX mutation) --}}
@php $sub = (float) ($summary['subtotal'] ?? 0); $total = (float) ($summary['total'] ?? 0); @endphp

@if ($offer && $items->isNotEmpty())
    @php
        $th = (float) $offer->threshold_amount;
        $remaining = max(0, $th - $sub);
        $pct = $th > 0 ? min(100, ($sub / $th) * 100) : 100;
        $reward = $offer->reward_text ?: ($offer->rewardProduct?->name ?: 'a free gift');
        $unlocked = $remaining <= 0;
    @endphp
    <div class="zc-cd-offer {{ $unlocked ? 'is-unlocked' : '' }}">
        <div class="zc-cd-offer__row">
            <span class="gift">🎁</span>
            <span>@if ($unlocked)🎉 You've unlocked <b>{{ $reward }}</b>!@else Get <b>{{ $reward }}</b> — add <b>৳{{ number_format($remaining) }}</b> more to unlock!@endif</span>
        </div>
        <div class="zc-cd-offer__bar"><span style="width:{{ $pct }}%"></span></div>
    </div>
@endif

<div class="zc-cd-items">
    @forelse ($items as $item)
        @php $key = $item['key'] ?? ''; $q = (int) ($item['quantity'] ?? 1); $unit = (float) ($item['price'] ?? 0); $line = (float) ($item['subtotal'] ?? $unit * $q); @endphp
        <div class="zc-cd-item" data-key="{{ $key }}" data-update="{{ route('cart.update', $key) }}" data-qty="{{ $q }}">
            <div class="zc-cd-item__img">@if (!empty($item['image_url']))<img src="{{ $item['image_url'] }}" alt="">@else<svg viewBox="0 0 24 24" fill="none" stroke="#cbb" stroke-width="1.3"><path d="M5 21V9l7-5 7 5v12"/></svg>@endif</div>
            <div class="zc-cd-item__mid">
                <div class="nm">{{ $item['display_name'] ?? $item['product_name'] ?? 'Item' }}</div>
                <div class="row">
                    <div class="qty">
                        <button type="button" data-cd-dec aria-label="Decrease">−</button>
                        <span class="q">{{ $q }}</span>
                        <button type="button" data-cd-inc aria-label="Increase">+</button>
                    </div>
                    <span class="pr">× ৳{{ number_format($unit) }} = <b>৳{{ number_format($line) }}</b></span>
                </div>
            </div>
            <button type="button" class="zc-cd-item__rm" data-cd-remove="{{ route('cart.remove', $key) }}" aria-label="Remove">&times;</button>
        </div>
    @empty
        <div class="zc-cd-empty">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>
            <p>Your cart is empty.</p>
            <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary">Start shopping</a>
        </div>
    @endforelse
</div>

@if ($suggested->isNotEmpty())
    <div class="zc-cd-suggest">
        <div class="zc-cd-suggest__head">
            <h4>You May Also Like</h4>
            <div class="nav"><button type="button" data-cd-prev aria-label="Previous">&#8249;</button><button type="button" data-cd-next aria-label="Next">&#8250;</button></div>
        </div>
        <div class="zc-cd-track" data-cd-track>
            @foreach ($suggested as $p)
                @php $th = $mediaUrl($p->thumbnail); @endphp
                <div class="zc-cd-scard">
                    <a href="{{ route('storefront.product.show', $p->slug) }}" class="img">@if ($th)<img src="{{ $th }}" alt="{{ $p->name }}">@else<svg viewBox="0 0 24 24" fill="none" stroke="#cbb" stroke-width="1.2"><path d="M5 21V9l7-5 7 5v12"/></svg>@endif</a>
                    <div class="nm">{{ \Illuminate\Support\Str::limit($p->name, 42) }}</div>
                    <div class="pr">৳{{ number_format((float) $p->price) }}</div>
                    <button type="button" class="add" data-cd-add="{{ $p->id }}">+ Add</button>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="zc-cd-foot">
    <div class="tot"><span>Total:</span><b>৳{{ number_format($total) }}</b></div>
    <a href="{{ $items->isEmpty() ? '#' : route('checkout', ['cart_checkout' => 1]) }}" class="zc-cd-checkout {{ $items->isEmpty() ? 'is-off' : '' }}">CHECKOUT</a>
</div>
