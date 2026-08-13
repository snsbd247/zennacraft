<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS · {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; color: #000; background: #fff; margin: 0; padding: 16px; }
        .pos { width: 300px; margin: 0 auto; }
        .center { text-align: center; }
        .brand { font-size: 18px; font-weight: 800; letter-spacing: 1px; }
        .muted { color: #444; font-size: 12px; }
        hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { text-align: left; padding: 3px 0; }
        td.r, th.r { text-align: right; }
        .tot { font-size: 14px; font-weight: 800; }
        .print-btn { display: block; width: 100%; margin: 16px 0; padding: 10px; background: #111; color: #fff; border: none; font-weight: 700; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="pos">
        <div class="center brand">ZENNA CRAFT</div>
        <div class="center muted">Order Invoice</div>
        <hr>
        <div class="muted">Invoice: <b>{{ $order->order_number }}</b></div>
        <div class="muted">Date: {{ $order->created_at?->format('d-m-Y h:i A') }}</div>
        <div class="muted">Customer: {{ $order->customer_name }}</div>
        <div class="muted">Phone: {{ $order->customer_phone }}</div>
        <div class="muted">Address: {{ $order->address }}{{ $order->district ? ', '.$order->district : '' }}</div>
        <hr>
        <table>
            <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Amt</th></tr></thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr><td>{{ $item->product_name }}</td><td class="r">{{ $item->quantity }}</td><td class="r">{{ number_format((float) $item->subtotal, 0) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <hr>
        <table>
            <tr><td>Subtotal</td><td class="r">{{ number_format((float) $order->subtotal, 0) }}</td></tr>
            <tr><td>Delivery</td><td class="r">{{ number_format((float) $order->delivery_fee, 0) }}</td></tr>
            <tr class="tot"><td>Total</td><td class="r">{{ number_format((float) $order->total, 0) }}</td></tr>
        </table>
        <hr>
        <div class="center muted">Thank you for shopping with us!</div>
        <button class="print-btn" onclick="window.print()">Print</button>
    </div>
</body>
</html>
