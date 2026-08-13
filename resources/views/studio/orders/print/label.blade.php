<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label · {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; padding: 16px; }
        .label { width: 384px; margin: 0 auto; border: 2px solid #000; padding: 14px; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { font-size: 18px; font-weight: 800; letter-spacing: 1px; }
        .inv { font-size: 13px; font-weight: 700; border: 1px solid #000; padding: 2px 8px; border-radius: 4px; }
        hr { border: none; border-top: 1px solid #000; margin: 10px 0; }
        .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #555; }
        .big { font-size: 15px; font-weight: 700; }
        .addr { font-size: 14px; line-height: 1.4; margin-top: 2px; }
        .cod { margin-top: 10px; font-size: 16px; font-weight: 800; text-align: center; border: 2px solid #000; padding: 6px; }
        .print-btn { display: block; width: 384px; margin: 16px auto 0; padding: 10px; background: #111; color: #fff; border: none; font-weight: 700; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="label">
        <div class="row">
            <div class="brand">ZENNA CRAFT</div>
            <div class="inv">{{ $order->order_number }}</div>
        </div>
        <hr>
        <div class="lbl">Deliver To</div>
        <div class="big">{{ $order->customer_name }}</div>
        <div class="addr">{{ $order->address }}{{ $order->district ? ', '.$order->district : '' }}</div>
        <div class="addr"><b>{{ $order->customer_phone }}</b></div>
        <hr>
        <div class="row">
            <div>
                <div class="lbl">Courier</div>
                <div class="big">{{ $order->shipment?->courierProvider?->name ?? 'Not assigned' }}</div>
            </div>
            <div style="text-align:right;">
                <div class="lbl">Items</div>
                <div class="big">{{ $order->items->sum('quantity') }}</div>
            </div>
        </div>
        @if ($order->shipment?->tracking_number)
            <div style="margin-top:6px;font-size:12px;">Memo/Tracking: <b>{{ $order->shipment->tracking_number }}</b></div>
        @endif
        <div class="cod">COD: {{ number_format((float) $order->total, 0) }} Tk</div>
    </div>
    <button class="print-btn" onclick="window.print()">Print</button>
</body>
</html>
