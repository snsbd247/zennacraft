@php
    $labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'];
    $cls = match ($order->status) {
        'delivered' => 'ok', 'pending' => 'warn', 'cancelled', 'returned' => 'bad', default => 'info',
    };
@endphp
<span class="zc-od-pill is-{{ $cls }}" data-region="order-status-badge">{{ $labels[$order->status] ?? ucfirst($order->status) }}</span>
