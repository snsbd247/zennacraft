@php $timeline = collect($timeline ?? []); @endphp
<section class="zc-pagehero"><div class="zc-wrap"><div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <span>Tracking</span></div><h1>Order #{{ $order->order_number }}</h1><p style="opacity:.9;margin-top:6px;">Current status: <b>{{ ucfirst($shipment?->status ?? $order->status) }}</b></p></div></section>
<section class="zc-sec zc-wrap" style="max-width:720px;">
    <div class="zc-card" style="padding:24px;">
        @if ($timeline->isNotEmpty())
            <div style="display:grid;gap:0;">
                @foreach ($timeline as $ev)
                    <div style="display:flex;gap:14px;">
                        <div style="display:flex;flex-direction:column;align-items:center;"><span style="width:14px;height:14px;border-radius:50%;background:{{ $loop->first ? 'var(--leaf)' : 'var(--line)' }};flex:none;margin-top:4px;"></span>@if(!$loop->last)<span style="width:2px;flex:1;background:var(--line);"></span>@endif</div>
                        <div style="padding-bottom:18px;"><b>{{ ucfirst($ev->status ?? $ev['status'] ?? 'Update') }}</b><div class="zc-muted" style="font-size:13px;">{{ $ev->description ?? $ev['description'] ?? '' }}</div><div class="zc-muted" style="font-size:12px;">{{ optional($ev->created_at ?? null)->format('M j, Y g:i A') }}</div></div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="zc-muted">Your order is confirmed. Detailed tracking updates will appear here as it moves.</p>
        @endif
    </div>
    <div style="margin-top:16px;"><a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--outline">Continue shopping</a></div>
</section>
