@php
    $money = fn ($v) => number_format((float) $v, 2);
    $itemsCount = $order->items->sum('quantity');
    $paid = $order->paid_amount !== null ? (float) $order->paid_amount : ($order->status === 'delivered' ? (float) $order->total : 0.0);
    $due = max(0, (float) $order->total - $paid);
    $payMethod = strtoupper($order->payment_method ?: 'COD');
@endphp
<div class="zc-od-summary" data-region="order-summary">
    <div class="zc-od-kpi"><div class="zc-od-kpi__l">Items</div><div class="zc-od-kpi__v">{{ $itemsCount }} pcs</div></div>
    <div class="zc-od-kpi"><div class="zc-od-kpi__l">Order total</div><div class="zc-od-kpi__v">৳{{ $money($order->total) }}</div></div>
    <div class="zc-od-kpi"><div class="zc-od-kpi__l">Amount due</div><div class="zc-od-kpi__v" style="color:{{ $due > 0 ? '#c0392b' : '#1c8a4e' }};">৳{{ $money($due) }}</div></div>
    <div class="zc-od-kpi"><div class="zc-od-kpi__l">Payment</div><div class="zc-od-kpi__v">{{ $payMethod }}</div></div>
</div>
