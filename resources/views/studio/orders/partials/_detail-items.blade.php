@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num = fn ($v) => number_format((float) $v, 2, '.', '');
    $editable = in_array($order->status, \App\Modules\Order\Models\Order::EDITABLE_STATUSES, true);
    $mediaUrlFn = $mediaUrl ?? fn ($m) => null;
    $discount = (float) $order->manual_discount_amount;
@endphp
<section class="zc-od-card" data-region="order-items">
    <div class="zc-od-card__head">
        <h2>Items</h2>
        @if ($editable)
            <span class="zc-od-editbadge">Editable</span>
        @else
            <span class="zc-od-muted">Locked · {{ ucfirst($order->status) }}</span>
        @endif
    </div>

    @foreach ($order->items as $item)
        @php $thumb = $mediaUrlFn($item->product?->thumbnail); @endphp
        <div class="zc-od-item">
            @if ($thumb)
                <img class="zc-od-thumb" src="{{ $thumb }}" alt="" loading="lazy">
            @else
                <span class="zc-od-thumb zc-od-thumb--ph"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m3 15 5-5 5 5M14 14l3-3 4 4"/></svg></span>
            @endif
            <div class="zc-od-item__main">
                <div class="zc-od-item__name">{{ $item->product_name }}</div>
                <div class="zc-od-item__sku">SKU: {{ $item->sku ?: '—' }}</div>
            </div>

            @if ($editable)
                <form data-ajax-form method="POST" action="{{ route('orders.items.update', [$order, $item]) }}" class="zc-od-itemedit">
                    @csrf @method('PATCH')
                    <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="999" class="zc-od-qtyin" aria-label="Quantity">
                    <span class="zc-od-x">×</span>
                    <span class="zc-od-cur">৳</span>
                    <input type="number" name="price" value="{{ $num($item->price) }}" min="0" step="0.01" class="zc-od-pricein" aria-label="Unit price">
                    <button type="submit" data-ajax-submit class="zc-od-mini" title="Save line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg></button>
                </form>
                <form data-ajax-form method="POST" action="{{ route('orders.items.destroy', [$order, $item]) }}" data-confirm="Remove this product from the order?" class="zc-od-delform">
                    @csrf @method('DELETE')
                    <button type="submit" data-ajax-submit class="zc-od-mini zc-od-mini--del" title="Remove"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </form>
            @else
                <div class="zc-od-item__qty">{{ $item->quantity }} × ৳{{ $money($item->price) }}</div>
            @endif

            <div class="zc-od-item__amt">৳{{ $money($item->subtotal) }}</div>
        </div>
    @endforeach

    @if ($editable)
        <div class="zc-od-additem">
            <form data-ajax-form method="POST" action="{{ route('orders.items.store', $order) }}" data-add-form data-search-url="{{ route('orders.create.products.search') }}">
                @csrf
                <input type="hidden" name="product_id" data-add-product>
                <input type="hidden" name="variant_id" data-add-variant>
                <input type="hidden" name="quantity" value="1">
                <div class="zc-od-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-add-search placeholder="Search a product to add…" autocomplete="off">
                    <div class="zc-od-results" data-add-results hidden></div>
                </div>
            </form>
        </div>
    @endif

    <div class="zc-od-totals">
        <div class="r"><span>Subtotal</span><span>৳{{ $money($order->subtotal) }}</span></div>
        <div class="r"><span>Delivery</span><span>৳{{ $money($order->delivery_fee) }}</span></div>
        @if ($editable)
            <div class="r zc-od-discrow">
                <span>Discount</span>
                <form data-ajax-form method="POST" action="{{ route('orders.discount', $order) }}" class="zc-od-disc">
                    @csrf
                    <span class="zc-od-cur">−৳</span>
                    <input type="number" name="discount" value="{{ $num($discount) }}" min="0" step="0.01" class="zc-od-pricein" aria-label="Discount amount">
                    <button type="submit" data-ajax-submit class="zc-od-mini" title="Apply discount"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg></button>
                </form>
            </div>
        @elseif ($discount > 0)
            <div class="r"><span>Discount</span><span>−৳{{ $money($discount) }}</span></div>
        @endif
        <div class="r grand"><span>Total</span><span>৳{{ $money($order->total) }}</span></div>
    </div>
</section>
