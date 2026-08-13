@php
    $labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'];
    $steps = [['pending', 'Pending'], ['confirmed', 'Confirmed'], ['processing', 'Processing'], ['shipped', 'Shipped'], ['delivered', 'Delivered']];
    $flow = ['pending' => 1, 'confirmed' => 2, 'processing' => 3, 'shipped' => 4, 'delivered' => 5];
    $terminal = in_array($order->status, ['cancelled', 'returned'], true);
    $current = $flow[$order->status] ?? 0;
    $histories = $order->statusHistories()->latest()->get();
    $badge = fn ($s) => match ($s) { 'delivered' => 'ok', 'pending' => 'warn', 'cancelled', 'returned' => 'bad', default => 'info' };
@endphp
<section class="zc-od-card" data-region="order-fulfillment">
    <div class="zc-od-card__head"><h2>Fulfillment</h2></div>

    @if ($terminal)
        <div class="zc-od-terminal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
            This order was <b>{{ $labels[$order->status] }}</b>.
        </div>
    @else
        <div class="zc-od-steps">
            @foreach ($steps as $i => [$key, $label])
                @php $n = $i + 1; $state = $n < $current ? 'done' : ($n === $current ? 'active' : 'todo'); @endphp
                <div class="zc-od-step is-{{ $state }}">
                    <span class="dot">
                        @if ($n < $current)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                        @else
                            {{ $n }}
                        @endif
                    </span>
                    <span class="lbl">{{ $label }}</span>
                </div>
                @if (! $loop->last)<span class="zc-od-line is-{{ $n < $current ? 'done' : 'todo' }}"></span>@endif
            @endforeach
        </div>
    @endif

    <div class="zc-od-timeline">
        <div class="zc-od-subhead">Status history</div>
        @forelse ($histories as $h)
            <div class="zc-od-tl">
                <span class="zc-od-tl__dot is-{{ $badge($h->new_status) }}"></span>
                <div class="zc-od-tl__body">
                    <div><span class="zc-od-chip is-{{ $badge($h->new_status) }}">{{ $labels[$h->new_status] ?? ucfirst($h->new_status) }}</span></div>
                    <div class="zc-od-tl__meta">{{ $h->created_at?->format('M j, Y g:i A') }} · {{ $h->staffUser?->name ?? 'System' }}</div>
                    @if ($h->notes)<div class="zc-od-tl__note">{{ $h->notes }}</div>@endif
                </div>
            </div>
        @empty
            <div class="zc-od-muted" style="padding:6px 0;">No status changes yet.</div>
        @endforelse
    </div>
</section>
