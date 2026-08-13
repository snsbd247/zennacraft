@php
    $theme = app(\App\Modules\Theme\Services\ThemeService::class);
    $settings = app(\App\Modules\Settings\Services\SettingService::class);
    $logo = $theme->mediaUrl('site_logo');
    $store = $storeName ?? ($theme->get('brand_name') ?: 'Store');
    $invAddress = trim((string) $settings->get('invoice', 'invoice_address', '')) ?: trim((string) $theme->get('contact_address', ''));
    $phone = trim((string) $theme->get('contact_phone', ''));
    $email = trim((string) $theme->get('contact_email', ''));
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $theme->get('primary_color', '')) ? $theme->get('primary_color') : '#1f7a3d';
    $money = fn ($v) => number_format((float) $v, 2);
    $paid = (float) $order->paid_amount;
    $due = max(0, (float) $order->total - $paid);
@endphp<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }} — {{ $store }}</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        :root{--acc:{{ $accent }};}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#eef1f4;color:#1f2733;padding:24px 16px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
        .toolbar{max-width:820px;margin:0 auto 16px;display:flex;justify-content:flex-end;gap:10px;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:11px 20px;border-radius:9px;font-weight:700;font-size:14px;text-decoration:none;border:none;cursor:pointer;font-family:inherit;}
        .btn-primary{background:var(--acc);color:#fff;}
        .btn-ghost{background:#fff;color:#1f2733;border:1px solid #d7dde4;}
        .invoice{max-width:820px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 14px 44px -20px rgba(20,30,50,.28);padding:46px 48px;}
        .inv-head{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #1f2733;padding-bottom:24px;flex-wrap:wrap;}
        .inv-brand img{height:54px;width:auto;margin-bottom:11px;display:block;}
        .inv-brand__name{font-size:21px;font-weight:800;letter-spacing:-.01em;}
        .inv-brand__meta{font-size:12.5px;color:#67727f;line-height:1.65;margin-top:4px;max-width:34ch;}
        .inv-title{text-align:right;margin-left:auto;}
        .inv-title__word{font-size:32px;font-weight:800;letter-spacing:.07em;color:var(--acc);line-height:1;}
        .inv-meta{margin-left:auto;margin-top:14px;font-size:13px;border-collapse:collapse;}
        .inv-meta td{padding:2.5px 0;}
        .inv-meta td:first-child{color:#8892a0;padding-right:16px;text-align:right;}
        .inv-meta td:last-child{font-weight:700;text-align:right;}
        .status{font-size:11px;font-weight:800;padding:3px 11px;border-radius:999px;display:inline-block;}
        .status.ok{background:#e3f6ea;color:#1c8a4e;}
        .status.due{background:#fdecea;color:#c0392b;}
        .inv-parties{display:flex;justify-content:space-between;gap:24px;margin:28px 0 6px;flex-wrap:wrap;}
        .inv-label{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:#a2abb6;margin-bottom:6px;}
        .inv-party__name{font-size:15.5px;font-weight:700;}
        .inv-party__meta{font-size:13px;color:#67727f;line-height:1.65;max-width:32ch;}
        .inv-parties__pay{text-align:right;}
        .inv-items{width:100%;border-collapse:collapse;font-size:13.5px;margin-top:22px;}
        .inv-items thead th{background:#1f2733;color:#fff;font-weight:700;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;padding:12px 13px;text-align:left;}
        .inv-items thead th.r{text-align:right;}
        .inv-items thead th:first-child{border-radius:8px 0 0 8px;}
        .inv-items thead th:last-child{border-radius:0 8px 8px 0;}
        .inv-items tbody td{padding:13px;border-bottom:1px solid #edf0f3;vertical-align:top;}
        .inv-items td.r{text-align:right;font-variant-numeric:tabular-nums;}
        .inv-items td.idx{color:#a2abb6;width:34px;}
        .inv-items .name{font-weight:600;color:#1f2733;}
        .inv-items tbody tr:last-child td{border-bottom:none;}
        .inv-foot{display:flex;justify-content:space-between;gap:28px;margin-top:26px;flex-wrap:wrap;}
        .inv-note{max-width:42ch;}
        .inv-note p{font-size:13px;color:#67727f;line-height:1.7;margin-top:2px;}
        .inv-totals{min-width:270px;border-collapse:collapse;font-size:14px;margin-left:auto;}
        .inv-totals td{padding:7px 0;}
        .inv-totals td.r{text-align:right;font-weight:700;font-variant-numeric:tabular-nums;}
        .inv-totals td:first-child{color:#67727f;}
        .inv-totals tr.grand td{border-top:2px solid #1f2733;padding-top:13px;font-size:18px;}
        .inv-totals tr.grand td:first-child{font-weight:800;color:#1f2733;}
        .inv-totals tr.grand td.r{color:var(--acc);}
        .inv-totals tr.due td{color:#c0392b;}
        .inv-totals tr.due td:first-child{color:#c0392b;font-weight:700;}
        .inv-bottom{display:flex;justify-content:space-between;gap:12px;margin-top:36px;padding-top:16px;border-top:1px solid #edf0f3;font-size:11.5px;color:#a2abb6;flex-wrap:wrap;}
        @media print{
            body{background:#fff;padding:0;}
            .no-print{display:none !important;}
            .invoice{box-shadow:none;border-radius:0;max-width:none;padding:0;margin:0;}
            @page{margin:14mm;}
        }
        @media(max-width:640px){
            body{padding:14px 10px;}
            .invoice{padding:26px 22px;}
            .inv-title__word{font-size:25px;}
            .inv-head{border-bottom-width:2px;}
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('storefront.home') }}" class="btn btn-ghost">← Home</a>
        <button onclick="window.print()" class="btn btn-primary">🖨 Print / Save PDF</button>
    </div>

    <div class="invoice">
        <header class="inv-head">
            <div class="inv-brand">
                @if ($logo)<img src="{{ $logo }}" alt="{{ $store }}">@endif
                <div class="inv-brand__name">{{ $store }}</div>
                @if ($invAddress)<div class="inv-brand__meta">{!! nl2br(e($invAddress)) !!}</div>@endif
                @if ($phone || $email)<div class="inv-brand__meta">{{ $phone }}@if ($phone && $email) · @endif{{ $email }}</div>@endif
            </div>
            <div class="inv-title">
                <div class="inv-title__word">INVOICE</div>
                <table class="inv-meta">
                    <tr><td>Invoice No</td><td>{{ $order->order_number }}</td></tr>
                    <tr><td>Date</td><td>{{ optional($order->created_at)->format('d M Y') }}</td></tr>
                    <tr><td>Status</td><td><span class="status {{ $due <= 0 ? 'ok' : 'due' }}">{{ $due <= 0 ? 'PAID' : 'UNPAID' }}</span></td></tr>
                </table>
            </div>
        </header>

        <section class="inv-parties">
            <div>
                <div class="inv-label">Bill To</div>
                <div class="inv-party__name">{{ $order->customer_name }}</div>
                <div class="inv-party__meta">{{ $order->customer_phone }}</div>
                <div class="inv-party__meta">{{ $order->address }}@if ($order->district), {{ $order->district }}@endif</div>
            </div>
            <div class="inv-parties__pay">
                <div class="inv-label">Payment Method</div>
                <div class="inv-party__name">{{ strtoupper($order->payment_method ?? 'COD') }}</div>
                <div class="inv-party__meta">{{ $due <= 0 ? 'Fully paid' : 'Payable on delivery' }}</div>
            </div>
        </section>

        <table class="inv-items">
            <thead>
                <tr><th class="idx">#</th><th>Description</th><th class="r">Qty</th><th class="r">Unit Price</th><th class="r">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td class="idx">{{ $loop->iteration }}</td>
                        <td class="name">{{ $item->product_name }}</td>
                        <td class="r">{{ $item->quantity }}</td>
                        <td class="r">{{ $money($item->price) }}</td>
                        <td class="r">{{ $money($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="inv-foot">
            <div class="inv-note">
                <div class="inv-label">Notes</div>
                <p>Thank you for shopping with {{ $store }}. Please inspect your items at delivery before payment. Amounts are in BDT (৳).</p>
            </div>
            <table class="inv-totals">
                <tr><td>Subtotal</td><td class="r">৳{{ $money($order->subtotal) }}</td></tr>
                <tr><td>Delivery</td><td class="r">৳{{ $money($order->delivery_fee) }}</td></tr>
                <tr class="grand"><td>Total</td><td class="r">৳{{ $money($order->total) }}</td></tr>
                <tr><td>Paid</td><td class="r">৳{{ $money($paid) }}</td></tr>
                <tr class="due"><td>Amount Due</td><td class="r">৳{{ $money($due) }}</td></tr>
            </table>
        </section>

        <footer class="inv-bottom">
            <div>{{ $store }} — Cash on Delivery Invoice</div>
            <div>Generated {{ now()->format('d M Y, g:i A') }}</div>
        </footer>
    </div>
</body>
</html>
