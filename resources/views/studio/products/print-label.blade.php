<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label · {{ $product->sku }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; padding: 20px; text-align: center; }
        .label { width: 260px; margin: 0 auto; padding: 12px 10px; border: 1px solid #000; }
        .brand { font-size: 11px; font-weight: 800; letter-spacing: 0.5px; }
        .barcode { margin: 8px auto 4px; width: 220px; }
        .barcode svg { width: 100%; height: 46px; }
        .code { font-size: 12px; margin-top: 2px; }
        .price { font-size: 14px; font-weight: 800; margin-top: 4px; }
        .print-btn { display: block; margin: 18px auto 0; padding: 10px 22px; background: #111; color: #fff; border: none; font-weight: 700; cursor: pointer; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="label">
        <div class="brand">ZENNA CRAFT</div>
        <div class="barcode">{!! \App\Modules\Shared\Support\Barcode::code39Svg($product->sku, 46, 2, 5) !!}</div>
        <div class="code">code: {{ $product->sku }}</div>
        <div class="price">price: {{ number_format((float) $product->price, 0) }} /=</div>
    </div>
    <button class="print-btn" onclick="window.print()">Print</button>
</body>
</html>
