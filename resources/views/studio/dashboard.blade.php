@extends('layouts.studio')

@section('title', 'Dashboard')
@section('subtitle', 'Zenna Craft admin workspace')

@push('studio-styles')
    <style>
        :root {
            --zc-gold-grad: linear-gradient(135deg, #f8ecc9 0%, #e0bd7d 45%, #a9793f 100%);
            --zc-card-grad: linear-gradient(165deg, rgba(255, 253, 247, 0.05), rgba(255, 253, 247, 0) 55%);
            --zc-card-shadow: 0 30px 80px -46px rgba(16, 24, 40, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            --zc-card-shadow-hover: 0 34px 90px -40px rgba(16, 24, 40, 0.28), 0 0 0 1px rgba(212, 180, 131, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        /* ---------- Premium section framing ---------- */
        .zc-section-head {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .zc-section-head__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.4rem;
            height: 2.4rem;
            flex: none;
            border-radius: 12px;
            background: linear-gradient(160deg, rgba(212, 180, 131, 0.22), rgba(212, 180, 131, 0.05));
            border: 1px solid rgba(212, 180, 131, 0.25);
            color: #a9793f;
        }

        .zc-section-head__icon svg {
            width: 1.2rem;
            height: 1.2rem;
        }

        /* ---------- Premium card shell ---------- */
        .zc-panel {
            position: relative;
            border-radius: 20px;
            border: 1px solid var(--studio-border);
            background: var(--studio-surface), var(--zc-card-grad);
            background-blend-mode: overlay;
            box-shadow: var(--zc-card-shadow);
            transition: box-shadow 0.25s ease, transform 0.25s ease, border-color 0.25s ease;
        }

        .zc-panel:hover {
            box-shadow: var(--zc-card-shadow-hover);
            border-color: rgba(212, 180, 131, 0.28);
        }

        /* ---------- KPI cards ---------- */
        .zc-kpi-grid {
            display: grid;
            gap: 0.7rem;
            grid-template-columns: repeat(2, 1fr);
        }

        @media (min-width: 560px) {
            .zc-kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (min-width: 880px) {
            .zc-kpi-grid { grid-template-columns: repeat(4, 1fr); }
        }

        @media (min-width: 1200px) {
            .zc-kpi-grid { grid-template-columns: repeat(6, 1fr); }
        }

        .zc-kpi {
            position: relative;
            display: block;
            border-radius: 13px;
            padding: 0.8rem 0.85rem;
            border: 1px solid var(--studio-border);
            background:
                radial-gradient(circle at 105% -10%, rgba(212, 180, 131, 0.16), transparent 60%),
                linear-gradient(165deg, rgba(255, 253, 247, 0.055), rgba(255, 253, 247, 0) 60%),
                var(--studio-surface-soft);
            box-shadow: var(--zc-card-shadow);
            text-decoration: none;
            overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        }

        a.zc-kpi {
            cursor: pointer;
        }

        a.zc-kpi:hover {
            transform: translateY(-3px);
            box-shadow: var(--zc-card-shadow-hover);
            border-color: rgba(212, 180, 131, 0.32);
        }

        /* Staggered rise-in. `backwards` fill (not `both`) so the finished
           state reverts to base — otherwise it would block the hover lift. */
        @keyframes zcKpiRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .zc-kpi { animation: zcKpiRise .5s cubic-bezier(.2,.8,.25,1) backwards; }
        .zc-kpi-grid > *:nth-child(1) { animation-delay: .02s; }
        .zc-kpi-grid > *:nth-child(2) { animation-delay: .06s; }
        .zc-kpi-grid > *:nth-child(3) { animation-delay: .10s; }
        .zc-kpi-grid > *:nth-child(4) { animation-delay: .14s; }
        .zc-kpi-grid > *:nth-child(5) { animation-delay: .18s; }
        .zc-kpi-grid > *:nth-child(6) { animation-delay: .22s; }
        .zc-kpi-grid > *:nth-child(7) { animation-delay: .26s; }
        .zc-kpi-grid > *:nth-child(8) { animation-delay: .30s; }
        .zc-kpi-grid > *:nth-child(9) { animation-delay: .34s; }
        .zc-kpi-grid > *:nth-child(10) { animation-delay: .38s; }
        .zc-kpi-grid > *:nth-child(11) { animation-delay: .42s; }
        .zc-kpi-grid > *:nth-child(n+12) { animation-delay: .46s; }
        @media (prefers-reduced-motion: reduce) { .zc-kpi { animation: none; } }

        .zc-kpi__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 8px;
            margin-bottom: 0.45rem;
        }

        .zc-kpi__icon svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        .zc-kpi--neutral .zc-kpi__icon { background: rgba(122, 162, 201, 0.16); color: #3b6ea5; }
        .zc-kpi--warning .zc-kpi__icon { background: rgba(212, 180, 131, 0.2); color: #a9793f; }
        .zc-kpi--info .zc-kpi__icon { background: rgba(122, 162, 201, 0.16); color: #3b6ea5; }
        .zc-kpi--success .zc-kpi__icon { background: rgba(95, 165, 120, 0.2); color: #1c8a4e; }
        .zc-kpi--danger .zc-kpi__icon { background: rgba(208, 135, 112, 0.2); color: #c0392b; }

        .zc-kpi__label {
            font-size: 0.63rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--studio-muted);
            line-height: 1.2;
        }

        .zc-kpi__value {
            margin-top: 0.15rem;
            font-size: 1.3rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--studio-text);
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .zc-kpi__value--gold {
            background: var(--zc-gold-grad);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .zc-kpi__note {
            margin-top: 0.2rem;
            font-size: 0.66rem;
            color: var(--studio-muted);
        }

        /* ---------- Tables ---------- */
        .zc-dash-recent {
            width: 100%;
            border-collapse: collapse;
        }

        .zc-dash-recent th {
            text-align: left;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: var(--studio-muted);
            padding: 0.75rem 1.35rem;
            background: rgba(255, 253, 247, 0.025);
            border-bottom: 1px solid var(--studio-border);
        }

        .zc-dash-recent td {
            padding: 0.85rem 1.35rem;
            border-bottom: 1px solid var(--studio-border);
            color: var(--studio-text);
            font-size: 0.88rem;
            vertical-align: middle;
        }

        .zc-dash-recent tbody tr {
            position: relative;
            transition: background 0.15s ease;
        }

        .zc-dash-recent tbody tr:last-child td {
            border-bottom: none;
        }

        .zc-dash-recent tbody tr:hover {
            background: rgba(212, 180, 131, 0.055);
        }

        .zc-dash-order-number {
            font-weight: 700;
            color: var(--studio-text);
        }

        .zc-dash-muted {
            color: var(--studio-muted);
            font-size: 0.76rem;
        }

        .zc-dash-empty {
            padding: 2.75rem 1.25rem;
            text-align: center;
            color: var(--studio-muted);
            font-size: 0.9rem;
        }

        .zc-dash-amount--positive {
            color: #1c8a4e;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .zc-dash-amount--negative {
            color: #c0392b;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        /* ---------- Bar charts ---------- */
        .zc-bars {
            display: flex;
            align-items: flex-end;
            gap: 0.7rem;
            height: 180px;
            padding: 0 0.25rem;
            overflow-x: auto;
        }

        .zc-bars__col {
            display: flex;
            flex: 1 0 auto;
            min-width: 2.6rem;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
            gap: 0.5rem;
        }

        .zc-bars__value {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--studio-text);
            font-variant-numeric: tabular-nums;
        }

        .zc-bars__bar {
            width: 100%;
            max-width: 28px;
            border-radius: 8px 8px 3px 3px;
            background: var(--zc-gold-grad);
            box-shadow: 0 8px 22px -10px rgba(212, 180, 131, 0.55);
            min-height: 4px;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .zc-bars__col:hover .zc-bars__bar {
            transform: scaleY(1.02);
            box-shadow: 0 10px 26px -8px rgba(212, 180, 131, 0.75);
        }

        .zc-bars__label {
            font-size: 0.68rem;
            color: var(--studio-muted);
            white-space: nowrap;
        }

        /* ---------- Facebook CAPI ---------- */
        .zc-capi-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
        }

        .zc-capi-stat {
            position: relative;
            border: 1px solid var(--studio-border);
            border-radius: 16px;
            background:
                radial-gradient(circle at 100% 0%, rgba(212, 180, 131, 0.1), transparent 60%),
                var(--studio-surface-soft);
            padding: 1rem 1.1rem;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .zc-capi-stat:hover {
            border-color: rgba(212, 180, 131, 0.3);
            transform: translateY(-2px);
        }

        .zc-capi-stat__label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--studio-muted);
            font-weight: 800;
        }

        .zc-capi-stat__value {
            margin-top: 0.35rem;
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--studio-text);
            font-variant-numeric: tabular-nums;
        }

        /* ---------- Accounts ---------- */
        .zc-acct-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px dashed var(--studio-border);
            font-size: 0.87rem;
            color: var(--studio-text);
            font-variant-numeric: tabular-nums;
        }

        .zc-acct-row__label {
            color: var(--studio-muted);
            font-variant-numeric: normal;
        }

        .zc-acct-row--total {
            border-bottom: none;
            border-top: 1px solid var(--studio-border-strong);
            margin-top: 0.3rem;
            padding-top: 0.9rem;
            font-weight: 800;
            font-size: 0.95rem;
        }

        .zc-acct-row--total .zc-acct-row__label {
            color: var(--studio-text);
        }

        .zc-acct-button {
            display: block;
            width: 100%;
            text-align: center;
            margin-top: 1.35rem;
            background: var(--zc-gold-grad) !important;
            color: #1a1408 !important;
            border: none !important;
            box-shadow: 0 16px 34px -16px rgba(212, 180, 131, 0.55);
            font-weight: 800 !important;
            letter-spacing: 0.03em;
        }

        /* ---------- Top products / districts ---------- */
        .zc-product-cell {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .zc-product-thumb {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--studio-surface-soft);
            border: 1px solid var(--studio-border);
            flex: none;
        }

        .zc-product-name {
            font-weight: 700;
            color: var(--studio-text);
        }

        .zc-product-sku {
            font-size: 0.74rem;
            color: var(--studio-muted);
        }

        .zc-district-row {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.7rem 0;
            border-bottom: 1px dashed var(--studio-border);
        }

        .zc-district-row:last-child {
            border-bottom: none;
        }

        .zc-district-pin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.9rem;
            height: 1.9rem;
            flex: none;
            border-radius: 999px;
            background: rgba(212, 180, 131, 0.14);
            border: 1px solid rgba(212, 180, 131, 0.25);
            color: #a9793f;
        }

        .zc-district-pin svg {
            width: 0.95rem;
            height: 0.95rem;
        }

        .zc-district-name {
            font-weight: 700;
            color: var(--studio-text);
            min-width: 6.5rem;
        }

        .zc-district-bar-track {
            flex: 1;
            height: 7px;
            border-radius: 999px;
            background: rgba(16, 24, 40, 0.08);
            overflow: hidden;
        }

        .zc-district-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--zc-gold-grad);
        }

        .zc-district-percent {
            font-size: 0.78rem;
            color: var(--studio-muted);
            min-width: 3.6rem;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .zc-district-count {
            font-weight: 800;
            color: var(--studio-text);
            min-width: 2.5rem;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $staffName = optional(auth()->guard('staff')->user())->name;
        $statusLabels = [
            'pending' => 'Pending Order',
            'confirmed' => 'Approved Order',
            'processing' => 'Packaging Order',
            'shipped' => 'Shipment Order',
            'delivered' => 'Delivered order',
            'cancelled' => 'Cancel order',
            'returned' => 'Return order',
        ];
        $statusVariant = fn (string $status): string => match ($status) {
            'delivered' => 'zc-kpi--success',
            'pending' => 'zc-kpi--warning',
            'cancelled', 'returned' => 'zc-kpi--danger',
            default => 'zc-kpi--info',
        };
        $sourceLabels = [
            'website' => 'Website',
            'landing' => 'Landing Page',
            'custom' => 'Admin Placed',
            'whatsapp' => 'WhatsApp',
        ];
        $facebookLabels = [
            'sent' => 'Sent',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'skipped' => 'Skipped',
        ];
        $monthAbbrev = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $maxMonthly = max(1, $monthlyOrderCounts->max() ?? 1);
        $maxSource = max(1, $ordersBySource->max() ?? 1);

        $kpiIcon = function (string $name): string {
            $paths = match ($name) {
                'orders' => '<path d="M6 8h12l-1 12H7z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
                'new' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 8v8M8 12h8"/>',
                'pending' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/>',
                'confirmed' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
                'processing' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v3"/><path d="M21 8v3"/><path d="M12 13v3"/><path d="M7 5.5l10 5"/>',
                'shipped' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
                'delivered' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
                'cancelled' => '<circle cx="12" cy="12" r="8.5"/><path d="M9 9l6 6M15 9l-6 6"/>',
                'returned' => '<path d="M9 14l-4-4 4-4"/><path d="M5 10h9a5 5 0 0 1 0 10h-1"/>',
                'customers' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M14.5 19a4.5 4.5 0 0 1 9 0"/>',
                'profit' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
                'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16"/><path d="M8 3v4M16 3v4"/><path d="M8 14v3M12 13v4M16 15v2"/>',
                'income' => '<path d="M12 3v18"/><path d="M17 8a4 4 0 0 0-4-3H10a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-3a4 4 0 0 1-4-3"/>',
                'expense' => '<path d="M4 6h16"/><path d="M6 6v13a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
                'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16" cy="14" r="1.3"/>',
                'chart' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-8"/>',
                'meta' => '<path d="M14 8h2V5h-2c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h2.2l.8-3H13V9c0-.6.4-1 1-1z"/>',
                'star' => '<path d="M12 3l2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9z"/>',
                'pin' => '<path d="M12 21s-7-6.3-7-11.5A7 7 0 0 1 19 9.5C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.4"/>',
                default => '<circle cx="12" cy="12" r="1.5"/>',
            };

            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
        };

        $statusIcon = fn (string $status): string => match ($status) {
            'pending' => 'pending', 'confirmed' => 'confirmed', 'processing' => 'processing',
            'shipped' => 'shipped', 'delivered' => 'delivered', 'cancelled' => 'cancelled',
            'returned' => 'returned', default => 'orders',
        };
    @endphp

    <div class="space-y-5">
        <section class="studio-page-hero" aria-labelledby="dashboard-title">
            <div class="studio-page-hero__meta">
                <div>
                    <h1 id="dashboard-title" class="studio-section-title">
                        Welcome back{{ $staffName ? ', '.$staffName : '' }}
                    </h1>
                    <p class="studio-section-subtitle">{{ now()->format('l, F j, Y') }} — here's where things stand today.</p>
                </div>
                <div class="studio-inline-actions">
                    <span class="studio-badge studio-badge--success">Live data</span>
                </div>
            </div>

            <div class="zc-kpi-grid mt-4" aria-label="Dashboard KPI summary">
                <a href="{{ route('orders.index') }}" class="zc-kpi zc-kpi--neutral">
                    <span class="zc-kpi__icon">{!! $kpiIcon('new') !!}</span>
                    <div class="zc-kpi__label">New Order</div>
                    <div class="zc-kpi__value">{{ number_format($newOrdersToday) }}</div>
                    <div class="zc-kpi__note">৳{{ $money($newOrdersTodayAmount) }}</div>
                </a>

                @foreach ($statuses as $status)
                    @php
                        $statusLink = \Illuminate\Support\Facades\Route::has('orders.index') ? route('orders.index', ['status' => $status]) : null;
                    @endphp
                    @if ($statusLink)
                        <a href="{{ $statusLink }}" class="zc-kpi {{ $statusVariant($status) }}">
                            <span class="zc-kpi__icon">{!! $kpiIcon($statusIcon($status)) !!}</span>
                            <div class="zc-kpi__label">{{ $statusLabels[$status] }}</div>
                            <div class="zc-kpi__value">{{ number_format((int) ($statusCounts[$status] ?? 0)) }}</div>
                            <div class="zc-kpi__note">৳{{ $money($statusAmounts[$status] ?? 0) }}</div>
                        </a>
                    @else
                        <div class="zc-kpi {{ $statusVariant($status) }}">
                            <span class="zc-kpi__icon">{!! $kpiIcon($statusIcon($status)) !!}</span>
                            <div class="zc-kpi__label">{{ $statusLabels[$status] }}</div>
                            <div class="zc-kpi__value">{{ number_format((int) ($statusCounts[$status] ?? 0)) }}</div>
                            <div class="zc-kpi__note">৳{{ $money($statusAmounts[$status] ?? 0) }}</div>
                        </div>
                    @endif
                @endforeach

                @php
                    $totalOrdersLink = \Illuminate\Support\Facades\Route::has('orders.index') ? route('orders.index') : null;
                @endphp
                @if ($totalOrdersLink)
                    <a href="{{ $totalOrdersLink }}" class="zc-kpi zc-kpi--neutral">
                        <span class="zc-kpi__icon">{!! $kpiIcon('orders') !!}</span>
                        <div class="zc-kpi__label">All order</div>
                        <div class="zc-kpi__value">{{ number_format($totalOrders) }}</div>
                        <div class="zc-kpi__note">৳{{ $money($totalOrdersAmount) }}</div>
                    </a>
                @else
                    <div class="zc-kpi zc-kpi--neutral">
                        <span class="zc-kpi__icon">{!! $kpiIcon('orders') !!}</span>
                        <div class="zc-kpi__label">All order</div>
                        <div class="zc-kpi__value">{{ number_format($totalOrders) }}</div>
                        <div class="zc-kpi__note">৳{{ $money($totalOrdersAmount) }}</div>
                    </div>
                @endif

                @php
                    $customersLink = \Illuminate\Support\Facades\Route::has('customers.index') ? route('customers.index') : null;
                @endphp
                @if ($customersLink)
                    <a href="{{ $customersLink }}" class="zc-kpi zc-kpi--info">
                        <span class="zc-kpi__icon">{!! $kpiIcon('customers') !!}</span>
                        <div class="zc-kpi__label">Customers</div>
                        <div class="zc-kpi__value">{{ number_format($totalCustomers) }}</div>
                    </a>
                @else
                    <div class="zc-kpi zc-kpi--info">
                        <span class="zc-kpi__icon">{!! $kpiIcon('customers') !!}</span>
                        <div class="zc-kpi__label">Customers</div>
                        <div class="zc-kpi__value">{{ number_format($totalCustomers) }}</div>
                    </div>
                @endif

                <a href="{{ route('orders.index', ['status' => 'delivered']) }}" class="zc-kpi zc-kpi--success">
                    <span class="zc-kpi__icon">{!! $kpiIcon('profit') !!}</span>
                    <div class="zc-kpi__label">Total Profit</div>
                    <div class="zc-kpi__value zc-kpi__value--gold">{{ $money($totalProfit) }}</div>
                    <div class="zc-kpi__note">Delivered orders only</div>
                </a>
                <a href="{{ route('orders.index', ['status' => 'delivered']) }}" class="zc-kpi zc-kpi--info">
                    <span class="zc-kpi__icon">{!! $kpiIcon('calendar') !!}</span>
                    <div class="zc-kpi__label">This Month's Profit</div>
                    <div class="zc-kpi__value zc-kpi__value--gold">{{ $money($monthProfit) }}</div>
                    <div class="zc-kpi__note">Delivered this month</div>
                </a>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="zc-panel studio-table-shell overflow-hidden p-0">
                <div class="studio-toolbar px-5 py-4" style="border-bottom: 1px solid var(--studio-border);">
                    <div class="zc-section-head">
                        <span class="zc-section-head__icon">{!! $kpiIcon('income') !!}</span>
                        <div>
                            <div class="studio-section-title">Income</div>
                            <p class="studio-section-subtitle">Delivered-order payments — ৳{{ $money($monthRevenue) }} this month.</p>
                        </div>
                    </div>
                </div>
                <div class="studio-responsive-scroll">
                    <table class="zc-dash-recent">
                        <caption class="sr-only">Recent income from delivered orders</caption>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentIncome as $order)
                                <tr>
                                    <td class="zc-dash-muted">{{ $order->created_at?->format('M j, Y g:i A') }}</td>
                                    <td class="zc-dash-order-number">{{ $order->order_number }}</td>
                                    <td class="zc-dash-amount--positive">+{{ $money($order->total) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="zc-dash-empty">No delivered orders yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="zc-panel studio-table-shell overflow-hidden p-0">
                <div class="studio-toolbar px-5 py-4" style="border-bottom: 1px solid var(--studio-border);">
                    <div class="zc-section-head">
                        <span class="zc-section-head__icon">{!! $kpiIcon('expense') !!}</span>
                        <div>
                            <div class="studio-section-title">Expense</div>
                            <p class="studio-section-subtitle">Recorded business expenses — ৳{{ $money($monthExpenses) }} this month.</p>
                        </div>
                    </div>
                </div>
                <div class="studio-responsive-scroll">
                    <table class="zc-dash-recent">
                        <caption class="sr-only">Recent business expenses</caption>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentExpenses as $expense)
                                <tr>
                                    <td class="zc-dash-muted">{{ optional($expense->expense_date)->format('M j, Y') }}</td>
                                    <td>
                                        {{ $expense->category?->name ?: 'Uncategorized' }}
                                        @if ($expense->description)
                                            <div class="zc-dash-muted">{{ \Illuminate\Support\Str::limit($expense->description, 40) }}</div>
                                        @endif
                                    </td>
                                    <td class="zc-dash-amount--negative">-{{ $money($expense->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="zc-dash-empty">No expenses recorded yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section>
            <div class="zc-section-head">
                <span class="zc-section-head__icon">{!! $kpiIcon('wallet') !!}</span>
                <div>
                    <div class="studio-section-title">Accounts</div>
                    <p class="studio-section-subtitle">
                        Per-channel balances. Every delivered order and recorded expense lands in
                        {{ $accounts->firstWhere('slug', 'cash')?->name ?? 'Cash' }} by default until Orders/Expenses have a way to pick a channel.
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-6 xl:grid-cols-3">
                <div class="zc-panel p-5">
                    <div class="studio-section-title">Today's Credit</div>
                    @foreach ($accounts as $account)
                        <div class="zc-acct-row">
                            <span class="zc-acct-row__label">{{ $account->name }}</span>
                            <span>{{ $money($todayCredit[$account->id] ?? 0) }}</span>
                        </div>
                    @endforeach
                    <div class="zc-acct-row zc-acct-row--total">
                        <span class="zc-acct-row__label">Total</span>
                        <span>{{ $money($todayCredit->sum()) }}</span>
                    </div>
                    <span class="studio-command-button zc-acct-button">Today Credit</span>
                </div>

                <div class="zc-panel p-5">
                    <div class="studio-section-title">Today's Debit</div>
                    @foreach ($accounts as $account)
                        <div class="zc-acct-row">
                            <span class="zc-acct-row__label">{{ $account->name }}</span>
                            <span>{{ $money($todayDebit[$account->id] ?? 0) }}</span>
                        </div>
                    @endforeach
                    <div class="zc-acct-row zc-acct-row--total">
                        <span class="zc-acct-row__label">Total</span>
                        <span>{{ $money($todayDebit->sum()) }}</span>
                    </div>
                    <span class="studio-command-button zc-acct-button">Today Debit</span>
                </div>

                <div class="zc-panel p-5">
                    <div class="studio-section-title">Total Balance</div>
                    @foreach ($accounts as $account)
                        <div class="zc-acct-row">
                            <span class="zc-acct-row__label">In {{ $account->name }}</span>
                            <span>{{ $money($totalBalance[$account->id] ?? 0) }}</span>
                        </div>
                    @endforeach
                    <div class="zc-acct-row zc-acct-row--total">
                        <span class="zc-acct-row__label">In Total</span>
                        <span>{{ $money($totalBalance->sum()) }}</span>
                    </div>
                    <span class="studio-command-button zc-acct-button">Total Balance</span>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="zc-panel p-5">
                <div class="zc-section-head">
                    <span class="zc-section-head__icon">{!! $kpiIcon('chart') !!}</span>
                    <div>
                        <div class="studio-section-title">Orders by Month</div>
                        <p class="studio-section-subtitle">{{ now()->year }}, order count per month.</p>
                    </div>
                </div>
                <div class="zc-bars mt-5">
                    @for ($m = 1; $m <= 12; $m++)
                        @php $count = (int) ($monthlyOrderCounts[$m] ?? 0); @endphp
                        <div class="zc-bars__col">
                            <span class="zc-bars__value">{{ $count }}</span>
                            <span class="zc-bars__bar" style="height: {{ max(3, (int) round(($count / $maxMonthly) * 140)) }}px;"></span>
                            <span class="zc-bars__label">{{ $monthAbbrev[$m - 1] }}</span>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="zc-panel p-5">
                <div class="zc-section-head">
                    <span class="zc-section-head__icon">{!! $kpiIcon('chart') !!}</span>
                    <div>
                        <div class="studio-section-title">Orders by Source</div>
                        <p class="studio-section-subtitle">Where orders are coming from, all-time.</p>
                    </div>
                </div>
                <div class="zc-bars mt-5">
                    @foreach ($sources as $source)
                        @php $count = (int) ($ordersBySource[$source] ?? 0); @endphp
                        <div class="zc-bars__col">
                            <span class="zc-bars__value">{{ $count }}</span>
                            <span class="zc-bars__bar" style="height: {{ max(3, (int) round(($count / $maxSource) * 140)) }}px;"></span>
                            <span class="zc-bars__label">{{ $sourceLabels[$source] ?? ucfirst($source) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="zc-panel p-5">
            <div class="zc-section-head">
                <span class="zc-section-head__icon">{!! $kpiIcon('meta') !!}</span>
                <div>
                    <div class="studio-section-title">Facebook CAPI</div>
                    <p class="studio-section-subtitle">Conversions API events sent to Facebook — {{ number_format($facebookTotal) }} total.</p>
                </div>
            </div>
            <div class="zc-capi-strip mt-5">
                @foreach ($facebookStatuses as $status)
                    <div class="zc-capi-stat">
                        <div class="zc-capi-stat__label">{{ $facebookLabels[$status] }}</div>
                        <div class="zc-capi-stat__value">{{ number_format((int) ($facebookCapiCounts[$status] ?? 0)) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <div class="zc-section-head">
                <span class="zc-section-head__icon">{!! $kpiIcon('star') !!}</span>
                <div class="studio-section-title">Top Products</div>
            </div>

            <div class="mt-5 grid gap-6 xl:grid-cols-2">
                <div class="zc-panel studio-table-shell overflow-hidden p-0">
                    <div class="studio-toolbar px-5 py-4" style="border-bottom: 1px solid var(--studio-border);">
                        <div class="studio-section-title">Top View Products</div>
                    </div>
                    <div class="studio-responsive-scroll">
                        <table class="zc-dash-recent">
                            <caption class="sr-only">Most-viewed products by view count</caption>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Views</th>
                                    <th>Last visit at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topViewedProducts as $row)
                                    <tr>
                                        <td>
                                            <div class="zc-product-cell">
                                                <img class="zc-product-thumb" src="{{ $mediaUrl($row['product']->thumbnail) ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22%3E%3C/svg%3E' }}" alt="" loading="lazy">
                                                <div>
                                                    <div class="zc-product-name">{{ $row['product']->name }}</div>
                                                    <div class="zc-product-sku">SKU: {{ $row['product']->sku }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ number_format($row['views']) }}</td>
                                        <td class="zc-dash-muted">{{ \Illuminate\Support\Carbon::parse($row['last_viewed_at'])->format('Y-m-d h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            <div class="zc-dash-empty">No product views tracked yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="zc-panel studio-table-shell overflow-hidden p-0">
                    <div class="studio-toolbar px-5 py-4" style="border-bottom: 1px solid var(--studio-border);">
                        <div class="studio-section-title">Top Selling by Zone</div>
                    </div>
                    <div class="p-5">
                        @forelse ($topDistricts as $row)
                            @php $percent = $totalOrders > 0 ? ((int) $row->aggregate / $totalOrders) * 100 : 0; @endphp
                            <div class="zc-district-row">
                                <span class="zc-district-pin">{!! $kpiIcon('pin') !!}</span>
                                <span class="zc-district-name">{{ $row->district }}</span>
                                <span class="zc-district-bar-track">
                                    <span class="zc-district-bar-fill" style="width: {{ max(2, round($percent)) }}%;"></span>
                                </span>
                                <span class="zc-district-percent">{{ number_format($percent, 2) }}%</span>
                                <span class="zc-district-count">{{ number_format((int) $row->aggregate) }}</span>
                            </div>
                        @empty
                            <div class="zc-dash-empty">No orders with a delivery zone yet — new checkouts will populate this.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>

    @push('studio-scripts')
    <script>
        (function () {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            // Count the KPI numbers up from zero on load — only pure integer
            // values (skips money/decimals/anything with a symbol).
            document.querySelectorAll('.zc-kpi__value').forEach(function (el) {
                var txt = el.textContent.trim();
                if (!/^[0-9,]+$/.test(txt)) return;
                var target = parseInt(txt.replace(/,/g, ''), 10);
                if (!target) return;
                var dur = 850, t0 = null;
                el.textContent = '0';
                function step(ts) {
                    if (!t0) t0 = ts;
                    var p = Math.min(1, (ts - t0) / dur);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased).toLocaleString('en-US');
                    if (p < 1) requestAnimationFrame(step); else el.textContent = target.toLocaleString('en-US');
                }
                requestAnimationFrame(step);
            });
        })();
    </script>
    @endpush
@endsection
