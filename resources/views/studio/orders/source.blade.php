@extends('layouts.studio')

@section('title', 'Order Source')
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
@endpush

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $sourceNames = [
            'website' => 'Website', 'landing' => 'Landing Page', 'custom' => 'Admin Placed', 'whatsapp' => 'WhatsApp',
        ];
        $maxCount = max(1, $rows->max('orders_count') ?? 1);
    @endphp

    <div class="space-y-6">
        <section class="studio-page-hero">
            <div class="studio-page-hero__meta">
                <div>
                    <h1 class="studio-section-title">Order Source</h1>
                    <p class="studio-section-subtitle">Where your {{ number_format($totalOrders) }} orders came from — with revenue and delivered-only profit per channel.</p>
                </div>
            </div>
        </section>

        <section class="zc-op-panel overflow-hidden p-0">
            <div class="studio-responsive-scroll">
                <table class="zc-op-tbl">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Share</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Delivered Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $pct = $totalOrders > 0 ? ((int) $row->orders_count / $totalOrders) * 100 : 0; @endphp
                            <tr>
                                <td class="zc-op-strong">{{ $sourceNames[$row->source] ?? ucfirst($row->source ?: 'Unknown') }}</td>
                                <td style="min-width: 12rem;">
                                    <div class="zc-op-bar-track"><div class="zc-op-bar-fill" style="width: {{ max(2, ($row->orders_count / $maxCount) * 100) }}%;"></div></div>
                                    <div class="zc-op-muted" style="margin-top:0.3rem;">{{ number_format($pct, 1) }}%</div>
                                </td>
                                <td class="zc-op-strong">{{ number_format((int) $row->orders_count) }}</td>
                                <td>৳{{ $money($row->revenue) }}</td>
                                <td class="zc-op-strong" style="color:#1c8a4e;">৳{{ $money($deliveredProfit[$row->source] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="zc-op-empty">No orders yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
