@extends('layouts.studio')

@section('title', 'Order Processing Note')
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
        $statusBadge = fn (?string $status): string => match ($status) {
            'delivered' => 'studio-badge--success',
            'pending' => 'studio-badge--warning',
            'cancelled', 'returned' => 'studio-badge--danger',
            default => 'studio-badge--info',
        };
    @endphp

    <div class="space-y-6">
        <section class="studio-page-hero">
            <div class="studio-page-hero__meta">
                <div>
                    <h1 class="studio-section-title">Order Processing Note</h1>
                    <p class="studio-section-subtitle">Internal notes staff have added across all orders — {{ number_format($notes->total()) }} total.</p>
                </div>
            </div>
        </section>

        @if (session('success'))
            <div class="studio-callout studio-callout--success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="studio-callout studio-callout--danger">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="zc-op-panel p-5">
            <div class="studio-section-title">Add a note</div>
            <form method="POST" action="{{ route('orders.notes.store') }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_2fr_auto]" style="align-items:end;">
                @csrf
                <div class="zc-op-field">
                    <label for="order_number">Order number</label>
                    <input type="text" id="order_number" name="order_number" value="{{ old('order_number') }}" class="studio-form-control" placeholder="ZC-…" required>
                </div>
                <div class="zc-op-field">
                    <label for="note">Note</label>
                    <input type="text" id="note" name="note" value="{{ old('note') }}" class="studio-form-control" placeholder="Processing note…" required>
                </div>
                <button type="submit" class="studio-command-button studio-command-button--primary">Add Note</button>
            </form>
        </section>

        <section class="zc-op-panel p-5">
            <form method="GET" action="{{ route('orders.notes.index') }}" class="zc-op-field" style="max-width:22rem;">
                <label for="filter_order">Filter by order number</label>
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" id="filter_order" name="order_number" value="{{ $filters['order_number'] ?? '' }}" class="studio-form-control" placeholder="ZC-…">
                    <button type="submit" class="studio-command-button">Search</button>
                </div>
            </form>
        </section>

        <section class="zc-op-panel overflow-hidden p-0">
            <div class="studio-responsive-scroll">
                <table class="zc-op-tbl">
                    <thead><tr><th>Order</th><th>Note</th><th>By</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse ($notes as $note)
                            <tr>
                                <td>
                                    @if ($note->order)
                                        <a href="{{ route('orders.show', $note->order) }}" class="zc-op-strong" style="color: var(--studio-accent);">{{ $note->order->order_number }}</a>
                                        <div><span class="studio-badge {{ $statusBadge($note->order->status) }}">{{ $statusLabels[$note->order->status] ?? ucfirst($note->order->status) }}</span></div>
                                    @else
                                        <span class="zc-op-muted">Order removed</span>
                                    @endif
                                </td>
                                <td>{{ $note->note }}</td>
                                <td class="zc-op-muted">{{ $note->staffUser?->name ?? 'Staff' }}</td>
                                <td class="zc-op-muted">{{ $note->created_at?->format('M j, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="zc-op-empty">No notes yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($notes->hasPages())
                <div class="p-4" style="border-top: 1px solid var(--studio-border);">{{ $notes->links() }}</div>
            @endif
        </section>
    </div>
@endsection
