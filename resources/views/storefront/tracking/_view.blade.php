@php
    $mediaUrl = $mediaUrl ?? fn ($m) => $m ? app(\App\Modules\Media\Services\MediaService::class)->url($m) : null;
    $money = fn ($v) => number_format((float) $v, 2);
    $status = $order->status;
    $steps = ['Order Placed', 'Approved', 'Ready to Ship', 'Packed', 'In Transit', 'Delivered'];
    $stepOf = ['pending' => 1, 'confirmed' => 2, 'processing' => 4, 'shipped' => 5, 'delivered' => 6];
    $current = $stepOf[$status] ?? 1;
    $cancelled = in_array($status, ['cancelled', 'returned'], true);
    $paid = (float) $order->paid_amount;
    $due = max(0, (float) $order->total - $paid);
    $statusBadge = ['pending' => 'Pending', 'confirmed' => 'Approved', 'processing' => 'Processing', 'shipped' => 'In Transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'][$status] ?? ucfirst($status);
@endphp

<div class="zc-trk">
    <div class="zc-trk__main">
        <div class="zc-trk-card">
            <div class="zc-trk-card__head">
                <span><span class="ic">⏱</span> Order Timeline</span>
                <span class="zc-trk-badge zc-trk-badge--{{ $cancelled ? 'bad' : ($current >= 6 ? 'ok' : 'pending') }}">{{ $statusBadge }}</span>
            </div>
            @if ($cancelled)
                <div class="zc-trk-cancel">This order was {{ strtolower($statusBadge) }}.</div>
            @else
                <div class="zc-trk-steps">
                    @foreach ($steps as $idx => $label)
                        @php $i = $idx + 1; @endphp
                        <div class="zc-trk-step {{ $i <= $current ? 'is-done' : '' }} {{ $i === $current ? 'is-current' : '' }}">
                            <span class="zc-trk-step__dot">{{ $i }}</span>
                            <span class="zc-trk-step__label">{{ $label }}</span>
                            @if ($i === 1)<span class="zc-trk-step__date">{{ optional($order->created_at)->format('d M') }}</span>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="zc-trk-card">
            <div class="zc-trk-card__head"><span><span class="ic">🛍</span> Products</span><span class="zc-trk-muted">{{ $order->items->count() }} Item(s)</span></div>
            <div class="zc-trk-items">
                @foreach ($order->items as $it)
                    @php $img = $mediaUrl(optional($it->product)->thumbnail); @endphp
                    <div class="zc-trk-item">
                        @if ($img)<img src="{{ $img }}" alt="">@else<span class="zc-trk-item__ph"></span>@endif
                        <div class="zc-trk-item__b"><b>{{ $it->product_name }}</b><span>Qty: {{ $it->quantity }}</span></div>
                        <div class="zc-trk-item__pr">৳{{ $money($it->subtotal) }}<span>৳{{ $money($it->price) }} / unit</span></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="zc-trk__side">
        <div class="zc-trk-card">
            <div class="zc-trk-card__head"><span><span class="ic">🧾</span> Order Summary</span></div>
            <div class="zc-trk-sum">
                <div><span>Subtotal</span><span>৳{{ $money($order->subtotal) }} BDT</span></div>
                <div><span>Delivery Fee</span><span>+ ৳{{ $money($order->delivery_fee) }} BDT</span></div>
                <div class="zc-trk-sum__grand"><span>Grand Total</span><span>৳{{ $money($order->total) }} BDT</span></div>
                <div class="zc-trk-sum__paid"><span>Total Paid</span><span>৳{{ $money($paid) }} BDT</span></div>
                <div class="zc-trk-sum__due"><span>Amount Due</span><span>৳{{ $money($due) }} BDT</span></div>
                <div class="zc-trk-sum__status">Payment Status <span class="zc-trk-pill {{ $due <= 0 ? 'ok' : 'bad' }}">{{ $due <= 0 ? 'Paid' : 'Unpaid' }}</span></div>
            </div>
            @if ($due > 0)<div class="zc-trk-cod">💵 Cash on delivery — pay ৳{{ $money($due) }} when your order arrives.</div>@endif
        </div>

        <div class="zc-trk-card">
            <div class="zc-trk-card__head"><span><span class="ic">📦</span> Shipping Details</span></div>
            <div class="zc-trk-ship">
                <div><span>Customer</span><b>{{ $order->customer_name }}</b></div>
                <div><span>Phone</span><b>{{ $order->customer_phone }}</b></div>
                <div><span>Delivery Address</span><b>{{ $order->address }}@if ($order->district), {{ $order->district }}@endif</b></div>
                <div><span>Payment Method</span><b>{{ strtoupper($order->payment_method ?? 'COD') }}</b></div>
            </div>
        </div>
    </div>
</div>
