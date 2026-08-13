@extends('layouts.studio')

@section('title', 'Order Processing Report')
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
@endpush

@section('content')
    @php
        $statusLabels = [
            'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing',
            'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned',
        ];
        $maxStatus = max(1, collect($statusCounts)->max() ?? 1);
    @endphp

    <div class="space-y-6">
        <section class="studio-page-hero">
            <div class="studio-page-hero__meta">
                <div>
                    <h1 class="studio-section-title">Order Processing Report</h1>
                    <p class="studio-section-subtitle">The lifecycle funnel and processing efficiency, from real order status history.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="zc-op-stat">
                    <div class="zc-op-stat__label">Total Orders</div>
                    <div class="zc-op-stat__value">{{ number_format($totalOrders) }}</div>
                </div>
                <div class="zc-op-stat">
                    <div class="zc-op-stat__label">Delivery Rate</div>
                    <div class="zc-op-stat__value">{{ $deliveryRate }}%</div>
                </div>
                <div class="zc-op-stat">
                    <div class="zc-op-stat__label">Cancel Rate</div>
                    <div class="zc-op-stat__value">{{ $cancelRate }}%</div>
                </div>
                <div class="zc-op-stat">
                    <div class="zc-op-stat__label">Return Rate</div>
                    <div class="zc-op-stat__value">{{ $returnRate }}%</div>
                </div>
            </div>
        </section>

        <section class="zc-op-panel p-5">
            <div class="studio-section-title">Avg. Time to Delivery</div>
            <p class="studio-section-subtitle">
                @if ($avgProcessingHours !== null)
                    On average, delivered orders take <strong>{{ $avgProcessingHours }} hours</strong>
                    ({{ round($avgProcessingHours / 24, 1) }} days) from placement to delivery.
                @else
                    Not enough delivered orders with status history yet to compute an average.
                @endif
            </p>
        </section>

        <section class="zc-op-panel p-5">
            <div class="studio-section-title">Orders by Stage</div>
            <p class="studio-section-subtitle">Count of orders currently in each lifecycle stage.</p>
            <div class="mt-5 space-y-3">
                @foreach ($statuses as $status)
                    @php
                        $count = (int) ($statusCounts[$status] ?? 0);
                        $pct = $totalOrders > 0 ? ($count / $totalOrders) * 100 : 0;
                    @endphp
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.35rem;">
                            <span class="zc-op-strong">{{ $statusLabels[$status] }}</span>
                            <span class="zc-op-muted">{{ number_format($count) }} · {{ number_format($pct, 1) }}%</span>
                        </div>
                        <div class="zc-op-bar-track">
                            <div class="zc-op-bar-fill" style="width: {{ max(1, ($count / $maxStatus) * 100) }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
