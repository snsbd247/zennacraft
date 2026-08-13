@php
    $money = fn ($value) => number_format((float) $value, 0);
    $tabLabels = [
        'pending' => 'New', 'confirmed' => 'Approved', 'processing' => 'Packaging',
        'shipped' => 'Shipment', 'delivered' => 'Delivered', 'returned' => 'Return', 'cancelled' => 'Cancel',
    ];
    $statusBadge = fn (string $status): string => match ($status) {
        'delivered' => 'studio-badge--success',
        'pending' => 'studio-badge--warning',
        'cancelled', 'returned' => 'studio-badge--danger',
        default => 'studio-badge--info',
    };
    $verifyBadge = fn (?string $vs): string => match ($vs) {
        'verified' => 'studio-badge--success',
        'called', 'callback_required', 'call_later' => 'studio-badge--warning',
        'failed', 'cancelled', 'no_answer' => 'studio-badge--danger',
        default => 'studio-badge--neutral',
    };
    $waPhone = fn (?string $phone): string => preg_replace('/[^0-9]/', '', preg_replace('/^0/', '880', (string) $phone));

    $seg = $order->customer?->segment;
    $risk = $order->customer?->riskProfile;
    $isFraud = $risk && in_array($risk->risk_level, ['high', 'critical'], true);
    $custCount = $seg?->total_orders ?? 1;
    $firstItem = $order->items->first();
    $itemsCount = $order->items_count ?? $order->items->count();
    $thumb = ($firstItem && ($url = $mediaUrl($firstItem->product?->thumbnail))) ? $url : null;
    $placeholderThumb = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22%3E%3C/svg%3E';
    $paid = $order->status === 'delivered' ? (float) $order->total : 0.0;
    $due = (float) $order->total - $paid;
@endphp
<tr data-region="order-row-{{ $order->id }}">
    <td><input type="checkbox" class="zc-ol-check"></td>
    <td><a href="{{ route('orders.show', $order) }}" class="zc-op-strong" style="color:var(--studio-accent);">{{ $order->order_number }}</a></td>

    {{-- Customer --}}
    <td style="min-width:13rem;">
        <div style="display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
            <span class="zc-op-strong">{{ $order->customer_name }}</span>
            @if ($isFraud)
                <span class="zc-ol-badge zc-ol-badge--fraud">Fraud {{ $risk->risk_score }}</span>
            @elseif ($seg && in_array(strtolower($seg->segment), ['vip', 'loyal'], true))
                <span class="zc-ol-badge zc-ol-badge--vip">{{ ucfirst($seg->segment) }} {{ $custCount }}</span>
            @else
                <span class="zc-ol-badge zc-ol-badge--regular">Regular {{ $custCount }}</span>
            @endif
        </div>
        <div style="margin-top:0.2rem; display:flex; align-items:center; gap:0.3rem; flex-wrap:wrap;">
            <span class="zc-op-muted">{{ $order->customer_phone }}</span>
            <button type="button" class="zc-ol-fraud-i" title="Courier fraud check" data-fraud-check data-phone="{{ $order->customer_phone }}" data-url="{{ route('orders.fraud-check') }}">i</button>
            <a href="tel:{{ $order->customer_phone }}" class="zc-ol-icon-btn" title="Call"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5c0 8 7 15 15 15l2-3-4-2-2 2c-3-1.5-5.5-4-7-7l2-2-2-4z"/></svg></a>
            <button type="button" class="zc-ol-icon-btn" title="Copy phone" data-copy="{{ $order->customer_phone }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg></button>
            <a href="https://wa.me/{{ $waPhone($order->customer_phone) }}" target="_blank" rel="noopener" class="zc-ol-icon-btn zc-ol-icon-btn--wa" title="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.1-.3.2-.5s0-.3 0-.5l-.7-1.6c-.2-.4-.4-.4-.5-.4h-.5c-.2 0-.5.1-.7.3a3 3 0 0 0-.9 2.2c0 1.3.9 2.5 1.1 2.7a10 10 0 0 0 4 3.5c1.4.6 1.9.6 2.6.5.4 0 1.4-.5 1.6-1.1.2-.5.2-1 .1-1.1s-.2-.1-.4-.2z"/></svg></a>
        </div>
        <div class="zc-op-muted" style="margin-top:0.2rem; font-size:0.72rem;">
            @if ($order->district || $order->address){{ $order->district ?: \Illuminate\Support\Str::limit($order->address, 24) }}@endif
            @if ($seg && $seg->total_orders > 1)
                <span style="margin-left:0.3rem;">· all {{ $seg->total_orders }} / deliv {{ $seg->delivered_orders }} / <b style="color:#e2a28c;">ret {{ $seg->returned_orders }}</b></span>
            @endif
        </div>
    </td>

    {{-- Product (hover to zoom the image) --}}
    <td style="min-width:12rem;">
        @if ($firstItem)
            <div class="zc-ol-prod">
                <img class="zc-ol-thumb" src="{{ $thumb ?: $placeholderThumb }}" alt="" loading="lazy"
                     @if ($thumb) data-zoom="{{ $thumb }}" @endif>
                <div>
                    <div class="zc-op-muted">Qty {{ $firstItem->quantity }} × {{ $itemsCount }} item{{ $itemsCount > 1 ? 's' : '' }}</div>
                    <div class="zc-op-strong" style="font-size:0.8rem;">{{ \Illuminate\Support\Str::limit($firstItem->product_name, 24) }}</div>
                    @if ($itemsCount > 1)<div class="zc-op-muted">+{{ $itemsCount - 1 }} more</div>@endif
                </div>
            </div>
        @else
            <span class="zc-op-muted">—</span>
        @endif
    </td>

    {{-- Total --}}
    <td>
        <div class="zc-ol-money">
            <div><span>Total:</span> <b>{{ $money($order->total) }}</b></div>
            <div><span>Paid:</span> <b>{{ $money($paid) }}</b></div>
            <div><span>Due:</span> <b>{{ $money($due) }}</b></div>
        </div>
    </td>

    {{-- Activities --}}
    <td style="min-width:10rem;">
        <div><span class="studio-badge {{ $statusBadge($order->status) }}">{{ $tabLabels[$order->status] ?? ucfirst($order->status) }}</span></div>
        <div class="zc-ol-act" style="margin-top:0.25rem; font-size:0.7rem;">
            <div>{{ $order->created_at?->format('d-m-Y, h:i A') }}</div>
            <div>By {{ $order->source === 'custom' ? 'Admin' : 'Customer' }}@if ($order->verifiedBy) · ✓ {{ $order->verifiedBy->name }}@endif</div>
        </div>
    </td>

    {{-- Call Verification --}}
    <td style="min-width:9rem;">
        <span class="studio-badge {{ $verifyBadge($order->verification_status) }}">{{ ucfirst(str_replace('_', ' ', $order->verification_status ?: 'unverified')) }}</span>
        @if ($order->verified_at)<div class="zc-op-muted" style="margin-top:0.3rem;">{{ $order->verified_at->format('d-m-Y') }}</div>@endif
        @if ($order->verification_status !== 'verified')
            <details class="zc-ol-pop" style="margin-top:0.4rem;">
                <summary class="studio-command-button" style="font-size:0.72rem; padding:0.3rem 0.6rem;">Call &amp; Verify</summary>
                <div class="zc-ol-pop__menu is-left">
                    <div class="zc-op-muted" style="font-size:0.72rem; margin-bottom:0.4rem;">Record call outcome</div>
                    <form data-ajax-form method="POST" action="{{ route('orders.verify', $order) }}" class="zc-ol-verify-btns">
                        @csrf
                        <button type="submit" data-ajax-submit name="outcome" value="verified" class="is-verify">✓ Verified — confirm order</button>
                        <button type="submit" data-ajax-submit name="outcome" value="no_answer">No Answer</button>
                        <button type="submit" data-ajax-submit name="outcome" value="wrong_number">Wrong Number</button>
                        <button type="submit" data-ajax-submit name="outcome" value="suspicious">Suspicious</button>
                        <button type="submit" data-ajax-submit name="outcome" value="customer_cancelled">Customer Cancelled</button>
                        <button type="submit" data-ajax-submit name="outcome" value="failed_verification">Failed Verification</button>
                    </form>
                </div>
            </details>
        @endif
    </td>

    {{-- Courier (click to assign) --}}
    <td style="min-width:8rem;">
        <details class="zc-ol-pop">
            <summary style="cursor:pointer; list-style:none;">
                @if ($order->shipment)
                    <span class="zc-op-strong">{{ $order->shipment->courierProvider?->name ?? 'Assigned' }}</span>
                    @if ($order->shipment->tracking_number)<div class="zc-op-muted">{{ $order->shipment->tracking_number }}</div>@endif
                @else
                    <span class="zc-ol-icon-btn" title="Assign courier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.7"/><circle cx="17" cy="18" r="1.7"/></svg></span>
                @endif
            </summary>
            <div class="zc-ol-pop__menu">
                <form data-ajax-form method="POST" action="{{ route('orders.courier.assign', $order) }}" class="zc-ol-formstack">
                    @csrf
                    <label class="zc-ol-flabel">Courier</label>
                    <select name="courier_provider_id" class="studio-form-control" required>
                        <option value="">Select Courier</option>
                        @foreach ($couriers as $c)
                            <option value="{{ $c->id }}" @selected($order->shipment && (int) $order->shipment->courier_provider_id === (int) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <label class="zc-ol-flabel">Memo Number</label>
                    <input type="text" name="tracking_number" class="studio-form-control" placeholder="Enter memo number" value="{{ $order->shipment?->tracking_number }}">
                    <button type="submit" data-ajax-submit class="studio-command-button studio-command-button--primary" style="justify-content:center;">submit</button>
                </form>
            </div>
        </details>
    </td>

    {{-- Note / Order Comment (click to add) --}}
    <td>
        <details class="zc-ol-pop">
            <summary class="zc-ol-icon-btn" title="Order comment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></summary>
            <div class="zc-ol-pop__menu">
                <div class="zc-ol-pop__title">Order Comment</div>
                <form data-ajax-form method="POST" action="{{ route('orders.comment', $order) }}" class="zc-ol-formstack">
                    @csrf
                    <label class="zc-ol-flabel">Select Comment</label>
                    <select name="preset" class="studio-form-control">
                        <option value="">Select Comment</option>
                        @foreach ($commentPresets as $preset)<option value="{{ $preset }}">{{ $preset }}</option>@endforeach
                    </select>
                    <label class="zc-ol-flabel">Write Comment (Optional)</label>
                    <input type="text" name="note" class="studio-form-control" placeholder="Write Comment">
                    <button type="submit" data-ajax-submit class="studio-command-button studio-command-button--primary" style="justify-content:center;">submit</button>
                </form>
                @if ($order->orderNotes->isNotEmpty())
                    <div class="zc-op-muted" style="margin-top:0.5rem; font-size:0.72rem;">Latest: {{ \Illuminate\Support\Str::limit($order->orderNotes->first()->note, 40) }}</div>
                @endif
            </div>
        </details>
    </td>

    {{-- Action --}}
    <td>
        <details class="zc-ol-pop">
            <summary class="zc-ol-icon-btn" title="Actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></summary>
            <div class="zc-ol-pop__menu zc-ol-actions-menu">
                <div class="zc-ol-menu-head">Update status</div>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="confirmed"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--approved"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Approved</button></form>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="processing"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--packaging"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/></svg>Packaging</button></form>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="shipped"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--shipment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>Shipment</button></form>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="delivered"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--delivered"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Delivered</button></form>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="returned"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--return"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-1"/></svg>Return</button></form>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="pending"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Pending</button></form>
                <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}"><input type="hidden" name="status" value="cancelled"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--cancel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>Cancel</button></form>
                <div class="zc-ol-menu-sep"></div>
                <form data-ajax-form method="POST" action="{{ route('orders.block', $order) }}" data-confirm="Block this customer's phone from future orders?"><button type="submit" data-ajax-submit class="zc-ol-act-btn zc-ol-act-btn--block"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>Ip Block</button></form>
                <a href="{{ route('orders.show', $order) }}" class="zc-ol-act-btn zc-ol-act-btn--edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
                <a href="{{ route('orders.show', $order) }}" class="zc-ol-act-btn zc-ol-act-btn--view"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>View</a>
                <div class="zc-ol-menu-sep"></div>
                <a href="{{ route('orders.label-print', $order) }}" target="_blank" rel="noopener" class="zc-ol-act-btn zc-ol-act-btn--label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V4h12v5"/><rect x="4" y="9" width="16" height="8" rx="1"/><path d="M8 17h8v4H8z"/></svg>Label print</a>
                <a href="{{ route('orders.pos-print', $order) }}" target="_blank" rel="noopener" class="zc-ol-act-btn zc-ol-act-btn--pos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="1"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>POS print</a>
            </div>
        </details>
    </td>
</tr>
