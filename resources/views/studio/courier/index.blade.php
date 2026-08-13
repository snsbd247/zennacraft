@extends('layouts.studio')

@section('title', 'Courier')
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
@endpush

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $shipmentBadge = fn (?string $status): string => match ($status) {
            'delivered' => 'studio-badge--success',
            'assigned', 'pending' => 'studio-badge--warning',
            'returned', 'cancelled' => 'studio-badge--danger',
            default => 'studio-badge--info',
        };
        // Providers with a live API client (App\Modules\Courier\Services\Api) —
        // other providers (RedX, Paperfly, manual) only support the manual
        // tracking-number entry above until a client is added for them too.
        $apiCourierSlugs = ['pathao', 'steadfast'];
    @endphp

    <div class="space-y-6">
        <section class="studio-page-hero">
            <div class="studio-page-hero__meta">
                <div>
                    <h1 class="studio-section-title">Courier</h1>
                    <p class="studio-section-subtitle">Courier providers, live shipments, and courier assignment.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($providers as $provider)
                    <div class="zc-op-stat">
                        <div class="zc-op-stat__label">{{ $provider->name }}</div>
                        <div class="zc-op-stat__value">{{ number_format($provider->shipments_count) }}</div>
                        <div class="zc-op-muted">shipments · {{ ucfirst($provider->status) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        @if (session('success'))
            <div class="studio-callout studio-callout--success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="studio-callout studio-callout--danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="studio-callout studio-callout--danger">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="zc-op-panel p-5">
            <div class="studio-section-title">Assign a courier</div>
            <p class="studio-section-subtitle">Pick an order that's ready to ship and hand it to a courier provider.</p>
            @if ($assignableOrders->isEmpty())
                <div class="zc-op-muted mt-3">No orders are currently awaiting courier assignment.</div>
            @else
                <form method="POST" action="{{ route('courier.assign') }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4" style="align-items:end;">
                    @csrf
                    <div class="zc-op-field">
                        <label for="order_id">Order</label>
                        <select id="order_id" name="order_id" class="studio-form-control" required>
                            <option value="">Select order</option>
                            @foreach ($assignableOrders as $order)
                                <option value="{{ $order->id }}">{{ $order->order_number }} — {{ $order->customer_name }} (৳{{ $money($order->total) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="zc-op-field">
                        <label for="courier_provider_id">Courier</label>
                        <select id="courier_provider_id" name="courier_provider_id" class="studio-form-control" required>
                            <option value="">Select courier</option>
                            @foreach ($activeProviders as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="zc-op-field">
                        <label for="tracking_number">Tracking # (optional)</label>
                        <input type="text" id="tracking_number" name="tracking_number" class="studio-form-control" placeholder="Consignment / tracking">
                    </div>
                    <div class="zc-op-field" style="display:flex; gap:0.5rem;">
                        <input type="number" step="0.01" min="0" name="courier_cost" class="studio-form-control" placeholder="Cost ৳" style="max-width:8rem;">
                        <button type="submit" class="studio-command-button studio-command-button--primary">Assign</button>
                    </div>
                </form>
            @endif
        </section>

        <section class="zc-op-panel overflow-hidden p-0">
            <div class="studio-toolbar px-5 py-4" style="border-bottom: 1px solid var(--studio-border);">
                <div class="studio-section-title">Recent Shipments</div>
            </div>
            <div class="studio-responsive-scroll">
                <table class="zc-op-tbl">
                    <thead><tr><th>Order</th><th>Courier</th><th>Tracking</th><th>Status</th><th>COD</th><th>Assigned</th><th>API</th></tr></thead>
                    <tbody>
                        @forelse ($shipments as $shipment)
                            <tr>
                                <td>
                                    @if ($shipment->order)
                                        <a href="{{ route('orders.show', $shipment->order) }}" class="zc-op-strong" style="color: var(--studio-accent);">{{ $shipment->order->order_number }}</a>
                                    @else
                                        <span class="zc-op-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $shipment->courierProvider?->name ?? 'Unassigned' }}</td>
                                <td class="zc-op-muted">{{ $shipment->tracking_number ?: '—' }}</td>
                                <td><span class="studio-badge {{ $shipmentBadge($shipment->status) }}">{{ ucfirst($shipment->status) }}</span></td>
                                <td>৳{{ $money($shipment->cod_amount) }}</td>
                                <td class="zc-op-muted">{{ $shipment->assigned_at?->format('M j, Y') ?: '—' }}</td>
                                <td style="white-space:nowrap;">
                                    @if ($shipment->courierProvider && in_array($shipment->courierProvider->slug, $apiCourierSlugs, true))
                                        <form method="POST" action="{{ route('courier.shipments.push', $shipment) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="studio-command-button" title="Push this shipment to {{ $shipment->courierProvider->name }}'s API">Push</button>
                                        </form>
                                        <form method="POST" action="{{ route('courier.shipments.sync-status', $shipment) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="studio-command-button" title="Pull the latest status from {{ $shipment->courierProvider->name }}">Sync</button>
                                        </form>
                                    @else
                                        <span class="zc-op-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="zc-op-empty">No shipments yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($shipments->hasPages())
                <div class="p-4" style="border-top: 1px solid var(--studio-border);">{{ $shipments->links() }}</div>
            @endif
        </section>
    </div>
@endsection
