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
@once
@push('storefront-styles')
<style>
    .zc-trk{display:grid;grid-template-columns:1.55fr 1fr;gap:20px;align-items:start;}
    @media(max-width:900px){.zc-trk{grid-template-columns:1fr;}}
    .zc-trk__main,.zc-trk__side{display:grid;gap:20px;}
    .zc-trk-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 18px 40px -30px rgba(9,20,13,.35);overflow:hidden;}
    .zc-trk-card__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:16px 20px;border-bottom:1px solid var(--line-soft);font-weight:800;color:var(--ink);font-size:15.5px;}
    .zc-trk-card__head .ic{color:var(--honey-deep);}
    .zc-trk-muted{color:var(--muted);font-weight:600;font-size:13px;}
    .zc-trk-badge{font-size:11px;font-weight:800;padding:4px 11px;border-radius:999px;letter-spacing:.03em;text-transform:uppercase;}
    .zc-trk-badge--pending{background:#fef3d6;color:#a5700a;} .zc-trk-badge--ok{background:#e3f6ea;color:#1c8a4e;} .zc-trk-badge--bad{background:#fdecea;color:#c0392b;}
    /* Steps */
    .zc-trk-steps{display:flex;justify-content:space-between;padding:26px 20px 22px;gap:6px;overflow-x:auto;}
    .zc-trk-step{position:relative;flex:1 0 auto;min-width:88px;text-align:center;}
    .zc-trk-step::before{content:"";position:absolute;top:18px;left:-50%;width:100%;height:3px;background:var(--line);z-index:0;}
    .zc-trk-step:first-child::before{display:none;}
    .zc-trk-step.is-done::before{background:var(--honey);}
    .zc-trk-step__dot{position:relative;z-index:1;width:38px;height:38px;border-radius:50%;display:grid;place-items:center;margin:0 auto 9px;font-weight:800;background:#fff;border:3px solid var(--line);color:var(--muted);transition:all .2s;}
    .zc-trk-step.is-done .zc-trk-step__dot{border-color:var(--honey);color:var(--honey-deep);}
    .zc-trk-step.is-current .zc-trk-step__dot{background:var(--honey);border-color:var(--honey);color:#3a2600;box-shadow:0 0 0 5px rgba(242,162,12,.18);}
    .zc-trk-step__label{display:block;font-size:12px;font-weight:700;color:var(--ink);line-height:1.3;}
    .zc-trk-step:not(.is-done) .zc-trk-step__label{color:var(--muted);}
    .zc-trk-step__date{display:block;font-size:11px;color:var(--muted);margin-top:2px;}
    .zc-trk-cancel{padding:26px 20px;text-align:center;color:#c0392b;font-weight:700;}
    /* Items */
    .zc-trk-items{padding:6px 8px;}
    .zc-trk-item{display:flex;align-items:center;gap:14px;padding:12px;border-bottom:1px solid var(--line-soft);}
    .zc-trk-item:last-child{border-bottom:none;}
    .zc-trk-item img,.zc-trk-item__ph{width:56px;height:56px;border-radius:10px;object-fit:cover;border:1px solid var(--line);background:var(--panel);flex:none;}
    .zc-trk-item__b{flex:1;min-width:0;}
    .zc-trk-item__b b{display:block;font-size:14.5px;color:var(--ink);}
    .zc-trk-item__b span{font-size:12.5px;color:var(--honey-deep);font-weight:700;}
    .zc-trk-item__pr{text-align:right;font-weight:800;color:var(--ink);white-space:nowrap;}
    .zc-trk-item__pr span{display:block;font-size:11.5px;color:var(--muted);font-weight:600;}
    /* Summary */
    .zc-trk-sum{padding:16px 20px;display:grid;gap:11px;font-size:14px;}
    .zc-trk-sum > div{display:flex;justify-content:space-between;align-items:center;}
    .zc-trk-sum > div span:first-child{color:var(--muted);}
    .zc-trk-sum > div span:last-child{font-weight:700;color:var(--ink);}
    .zc-trk-sum__grand{border-top:1px dashed var(--line);padding-top:12px;font-size:17px;}
    .zc-trk-sum__grand span{font-weight:800 !important;}
    .zc-trk-sum__paid span:last-child{color:#1c8a4e !important;}
    .zc-trk-sum__due span:last-child{color:#c0392b !important;}
    .zc-trk-sum__status{justify-content:center !important;gap:8px;border-top:1px dashed var(--line);padding-top:12px;color:var(--muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
    .zc-trk-pill{padding:3px 12px;border-radius:999px;font-size:12px;}
    .zc-trk-pill.ok{background:#e3f6ea;color:#1c8a4e;} .zc-trk-pill.bad{background:#fdecea;color:#c0392b;}
    .zc-trk-cod{margin:0 20px 18px;background:var(--honey-soft);border:1px solid #f2dca6;color:#8a5a00;border-radius:11px;padding:12px 14px;font-size:13px;font-weight:600;text-align:center;}
    /* Shipping */
    .zc-trk-ship{padding:16px 20px;display:grid;gap:14px;}
    .zc-trk-ship > div span{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:3px;}
    .zc-trk-ship > div b{font-size:14px;color:var(--ink);font-weight:700;line-height:1.5;}
</style>
@endpush
@endonce

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
