@extends('layouts.studio')

@section('title', 'Order '.$order->order_number)
@section('subtitle', 'Orders')

@push('studio-styles')
    @include('studio.orders.partials._styles')
<style>
    .zc-od{max-width:1200px;margin:0 auto;}
    .zc-od-pill{display:inline-flex;align-items:center;font-size:0.72rem;font-weight:800;letter-spacing:.02em;padding:5px 13px;border-radius:999px;text-transform:uppercase;}
    .zc-od-pill.is-ok{background:#e3f6ea;color:#1c8a4e;} .zc-od-pill.is-info{background:#e6effc;color:#2563a8;}
    .zc-od-pill.is-warn{background:#fdf1dc;color:#a5741b;} .zc-od-pill.is-bad{background:#fdecea;color:#c0392b;}

    /* Header */
    .zc-od-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:18px;padding:18px 22px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 20px 50px -42px rgba(16,24,40,.35);}
    .zc-od-head__t{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
    .zc-od-head__t h1{font-size:1.5rem;font-weight:800;margin:0;letter-spacing:-.01em;}
    .zc-od-head__meta{color:var(--studio-muted);font-size:0.82rem;margin-top:4px;}
    .zc-od-acts{display:flex;gap:8px;flex-wrap:wrap;}
    .zc-od-btn{display:inline-flex;align-items:center;gap:7px;font-size:0.8rem;font-weight:700;padding:9px 15px;border-radius:10px;border:1px solid var(--studio-border);background:var(--studio-surface);color:var(--studio-text);cursor:pointer;}
    .zc-od-btn svg{width:15px;height:15px;}
    .zc-od-btn:hover{border-color:var(--studio-accent);}
    .zc-od-btn--dark{background:#1f2733;border-color:#1f2733;color:#fff;}

    /* Summary strip */
    .zc-od-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:16px;}
    .zc-od-kpi{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:14px;padding:13px 16px;}
    .zc-od-kpi__l{font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--studio-muted);}
    .zc-od-kpi__v{margin-top:5px;font-size:1.15rem;font-weight:800;color:var(--studio-text);font-variant-numeric:tabular-nums;}

    .zc-od-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:16px;margin-top:16px;align-items:start;}
    .zc-od-col{display:flex;flex-direction:column;gap:16px;}
    .zc-od-card{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:18px;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,.04),0 20px 50px -42px rgba(16,24,40,.35);}
    .zc-od-card__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid var(--studio-border);}
    .zc-od-card__head h2{font-size:0.95rem;font-weight:800;margin:0;}
    .zc-od-card__body{padding:16px 18px;}

    /* Items */
    .zc-od-item{display:flex;align-items:center;gap:13px;padding:13px 18px;border-bottom:1px solid var(--studio-border);}
    .zc-od-item:last-of-type{border-bottom:none;}
    .zc-od-thumb{width:46px;height:46px;border-radius:11px;object-fit:cover;background:var(--studio-surface-soft);border:1px solid var(--studio-border);flex:none;}
    .zc-od-thumb--ph{display:grid;place-items:center;color:var(--studio-muted);}
    .zc-od-item__main{flex:1 1 auto;min-width:0;}
    .zc-od-item__name{font-weight:700;font-size:0.9rem;}
    .zc-od-item__sku{font-size:0.74rem;color:var(--studio-muted);margin-top:2px;}
    .zc-od-item__qty{font-size:0.8rem;color:var(--studio-muted);white-space:nowrap;}
    .zc-od-item__amt{font-weight:800;font-size:0.9rem;white-space:nowrap;font-variant-numeric:tabular-nums;}
    .zc-od-totals{padding:14px 18px;border-top:1px solid var(--studio-border);display:grid;gap:7px;}
    .zc-od-totals .r{display:flex;justify-content:space-between;font-size:0.86rem;}
    .zc-od-totals .r span{color:var(--studio-muted);}
    .zc-od-totals .r.grand{font-weight:800;font-size:1rem;border-top:1px dashed var(--studio-border);padding-top:10px;margin-top:3px;}
    .zc-od-totals .r.grand span{color:var(--studio-text);}

    /* Stepper */
    .zc-od-steps{display:flex;align-items:flex-start;padding:20px 18px 8px;overflow-x:auto;}
    .zc-od-step{display:flex;flex-direction:column;align-items:center;gap:7px;flex:0 0 auto;width:74px;text-align:center;}
    .zc-od-step .dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:0.85rem;border:2px solid var(--studio-border);background:var(--studio-surface);color:var(--studio-muted);}
    .zc-od-step .dot svg{width:16px;height:16px;}
    .zc-od-step .lbl{font-size:0.72rem;font-weight:700;color:var(--studio-muted);}
    .zc-od-step.is-done .dot{background:#1c8a4e;border-color:#1c8a4e;color:#fff;}
    .zc-od-step.is-done .lbl{color:var(--studio-text);}
    .zc-od-step.is-active .dot{background:var(--studio-accent);border-color:var(--studio-accent);color:#3a2a08;box-shadow:0 0 0 4px rgba(212,180,131,.25);}
    .zc-od-step.is-active .lbl{color:var(--studio-text);}
    .zc-od-line{flex:1 1 auto;height:2px;margin-top:17px;background:var(--studio-border);min-width:14px;}
    .zc-od-line.is-done{background:#1c8a4e;}
    .zc-od-terminal{display:flex;align-items:center;gap:10px;margin:16px 18px 4px;padding:12px 15px;border-radius:12px;background:#fdecea;color:#c0392b;font-size:0.88rem;font-weight:600;}
    .zc-od-terminal svg{width:20px;height:20px;flex:none;}

    /* Timeline (history) */
    .zc-od-timeline{padding:6px 18px 16px;}
    .zc-od-subhead{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--studio-muted);margin:8px 0 12px;}
    .zc-od-tl{display:flex;gap:12px;padding-bottom:14px;position:relative;}
    .zc-od-tl:not(:last-child)::before{content:"";position:absolute;left:5px;top:14px;bottom:0;width:2px;background:var(--studio-border);}
    .zc-od-tl__dot{width:12px;height:12px;border-radius:50%;flex:none;margin-top:2px;z-index:1;}
    .zc-od-tl__dot.is-ok{background:#1c8a4e;} .zc-od-tl__dot.is-info{background:#2563a8;}
    .zc-od-tl__dot.is-warn{background:#c99a3a;} .zc-od-tl__dot.is-bad{background:#c0392b;}
    .zc-od-chip{display:inline-flex;font-size:0.68rem;font-weight:800;padding:2px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.02em;}
    .zc-od-chip.is-ok{background:#e3f6ea;color:#1c8a4e;} .zc-od-chip.is-info{background:#e6effc;color:#2563a8;}
    .zc-od-chip.is-warn{background:#fdf1dc;color:#a5741b;} .zc-od-chip.is-bad{background:#fdecea;color:#c0392b;}
    .zc-od-tl__meta{font-size:0.75rem;color:var(--studio-muted);margin-top:3px;}
    .zc-od-tl__note{font-size:0.82rem;margin-top:4px;}

    /* Customer */
    .zc-od-kv{display:grid;gap:11px;}
    .zc-od-kv__k{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--studio-muted);}
    .zc-od-kv__v{font-size:0.9rem;margin-top:2px;}
    .zc-od-phone{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .zc-od-ic{width:30px;height:30px;border-radius:9px;border:1px solid var(--studio-border);display:grid;place-items:center;color:var(--studio-text);background:var(--studio-surface);cursor:pointer;}
    .zc-od-ic svg{width:15px;height:15px;} .zc-od-ic:hover{border-color:var(--studio-accent);}
    .zc-od-ic--wa{color:#25806f;}

    /* Forms / notes */
    .zc-od-field{margin-bottom:12px;}
    .zc-od-field label{display:block;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--studio-muted);margin-bottom:6px;}
    .zc-od-notelist{display:grid;gap:9px;}
    .zc-od-note{border:1px solid var(--studio-border);border-radius:12px;padding:10px 12px;}
    .zc-od-note__body{font-size:0.85rem;}
    .zc-od-note__meta{font-size:0.72rem;color:var(--studio-muted);margin-top:4px;}
    .zc-od-muted{color:var(--studio-muted);font-size:0.85rem;}

    /* Editable items */
    .zc-od-editbadge{font-size:0.64rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#1c8a4e;background:#e3f6ea;padding:3px 9px;border-radius:999px;}
    .zc-od-itemedit{display:flex;align-items:center;gap:5px;margin:0;}
    .zc-od-qtyin,.zc-od-pricein{border:1px solid var(--studio-border);border-radius:8px;padding:6px 7px;font-size:0.82rem;background:var(--studio-surface);color:var(--studio-text);font-variant-numeric:tabular-nums;}
    .zc-od-qtyin{width:50px;text-align:center;} .zc-od-pricein{width:80px;}
    .zc-od-x,.zc-od-cur{color:var(--studio-muted);font-size:0.78rem;}
    .zc-od-mini{width:30px;height:30px;flex:none;border-radius:8px;border:1px solid var(--studio-border);background:var(--studio-surface);display:grid;place-items:center;cursor:pointer;color:#1c8a4e;}
    .zc-od-mini svg{width:15px;height:15px;} .zc-od-mini:hover{background:#e3f6ea;border-color:#1c8a4e;}
    .zc-od-mini--del{color:#c0392b;} .zc-od-mini--del:hover{background:#fdecea;border-color:#c0392b;}
    .zc-od-delform{margin:0;}
    .zc-od-additem{padding:12px 18px;border-bottom:1px solid var(--studio-border);}
    .zc-od-search{position:relative;display:flex;align-items:center;gap:8px;border:1px dashed var(--studio-border);border-radius:11px;padding:9px 12px;}
    .zc-od-search > svg{width:16px;height:16px;color:var(--studio-muted);flex:none;}
    .zc-od-search input{flex:1;border:none;background:transparent;outline:none;font-size:0.88rem;color:var(--studio-text);}
    .zc-od-results{position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:12px;box-shadow:0 20px 44px -20px rgba(16,24,40,.4);z-index:20;max-height:280px;overflow-y:auto;padding:5px;}
    .zc-od-res{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;text-align:left;padding:9px 11px;border:none;background:transparent;border-radius:9px;cursor:pointer;font-size:0.85rem;color:var(--studio-text);}
    .zc-od-res:hover{background:var(--studio-surface-soft);}
    .zc-od-res .nm{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .zc-od-res .pr{font-weight:700;color:var(--studio-muted);white-space:nowrap;font-variant-numeric:tabular-nums;}
    .zc-od-res--var{padding-left:20px;font-weight:500;color:var(--studio-muted);}
    .zc-od-res--empty{color:var(--studio-muted);cursor:default;justify-content:center;} .zc-od-res--empty:hover{background:transparent;}
    .zc-od-discrow{align-items:center;} .zc-od-disc{display:inline-flex;align-items:center;gap:5px;margin:0;} .zc-od-disc .zc-od-pricein{width:92px;}

    @media(max-width:1000px){ .zc-od-grid{grid-template-columns:1fr;} }
    @media(max-width:560px){ .zc-od-summary{grid-template-columns:repeat(2,1fr);} .zc-od-item{flex-wrap:wrap;} }
</style>
@endpush

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $itemsCount = $order->items->sum('quantity');
    $paid = $order->paid_amount !== null ? (float) $order->paid_amount : ($order->status === 'delivered' ? (float) $order->total : 0.0);
    $due = max(0, (float) $order->total - $paid);
    $payMethod = strtoupper($order->payment_method ?: 'COD');
    $mediaUrlFn = $mediaUrl ?? fn ($m) => null;
    $phoneDigits = preg_replace('/[^0-9]/', '', preg_replace('/^0/', '880', (string) $order->customer_phone));
@endphp

<div class="zc-od space-y-0">
    {{-- Header --}}
    <section class="zc-od-head">
        <div>
            <div class="zc-od-head__t">
                <h1>{{ $order->order_number }}</h1>
                @include('studio.orders.partials._detail-status-badge', ['order' => $order])
            </div>
            <div class="zc-od-head__meta">
                Placed {{ $order->created_at?->format('M j, Y g:i A') }}
                @if ($order->exchangedFrom)· Exchange for <a href="{{ route('orders.show', $order->exchangedFrom) }}" style="color:var(--studio-accent);font-weight:700;">{{ $order->exchangedFrom->order_number }}</a>@endif
            </div>
        </div>
        <div class="zc-od-acts">
            <a href="{{ route('orders.index') }}" class="zc-od-btn zc-od-btn--dark">← Back to list</a>
            <a href="{{ URL::signedRoute('checkout.invoice', ['order' => $order->order_number]) }}" target="_blank" rel="noopener" class="zc-od-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 2h9l4 4v16H6z"/><path d="M9 12h7M9 16h5"/></svg> Invoice</a>
            <a href="{{ route('orders.label-print', $order) }}" target="_blank" rel="noopener" class="zc-od-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 9V4h12v5"/><rect x="4" y="9" width="16" height="8" rx="1"/><path d="M8 17h8v4H8z"/></svg> Label</a>
            <a href="{{ route('orders.pos-print', $order) }}" target="_blank" rel="noopener" class="zc-od-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="6" y="3" width="12" height="18" rx="1"/><path d="M9 7h6M9 11h6M9 15h4"/></svg> POS</a>
        </div>
    </section>

    @if (session('success'))<div class="studio-callout studio-callout--success" style="margin-top:14px;">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger" style="margin-top:14px;"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Summary strip --}}
    @include('studio.orders.partials._detail-summary', ['order' => $order])

    <div class="zc-od-grid">
        {{-- Main column --}}
        <div class="zc-od-col">
            {{-- Items (editable when the order is still pending/confirmed/processing) --}}
            @include('studio.orders.partials._detail-items', ['order' => $order, 'mediaUrl' => $mediaUrl ?? null])

            {{-- Fulfillment (stepper + history) --}}
            @include('studio.orders.partials._detail-fulfillment', ['order' => $order])
        </div>

        {{-- Sidebar --}}
        <div class="zc-od-col">
            {{-- Customer --}}
            <section class="zc-od-card">
                <div class="zc-od-card__head">
                    <h2>Customer</h2>
                    @if ($order->customer)<a href="{{ route('customers.show', $order->customer) }}" class="zc-od-muted" style="font-weight:700;color:var(--studio-accent);">Profile →</a>@endif
                </div>
                <div class="zc-od-card__body">
                    <div class="zc-od-kv">
                        <div><div class="zc-od-kv__k">Name</div><div class="zc-od-kv__v zc-op-strong">{{ $order->customer_name }}</div></div>
                        <div>
                            <div class="zc-od-kv__k">Phone</div>
                            <div class="zc-od-phone" style="margin-top:5px;">
                                <span class="zc-od-kv__v" style="margin:0;">{{ $order->customer_phone }}</span>
                                <a href="tel:{{ $order->customer_phone }}" class="zc-od-ic" title="Call"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5c0 8 7 15 15 15l2-3-4-2-2 2c-3-1.5-5.5-4-7-7l2-2-2-4z"/></svg></a>
                                <button type="button" class="zc-od-ic" title="Copy" data-copy="{{ $order->customer_phone }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg></button>
                                <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="zc-od-ic zc-od-ic--wa" title="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.1-.3.2-.5s0-.3 0-.5l-.7-1.6c-.2-.4-.4-.4-.5-.4h-.5c-.2 0-.5.1-.7.3a3 3 0 0 0-.9 2.2c0 1.3.9 2.5 1.1 2.7a10 10 0 0 0 4 3.5c1.4.6 1.9.6 2.6.5.4 0 1.4-.5 1.6-1.1.2-.5.2-1 .1-1.1s-.2-.1-.4-.2z"/></svg></a>
                            </div>
                        </div>
                        <div id="zc-od-address-view">
                            @if ($order->district)<div><div class="zc-od-kv__k">District</div><div class="zc-od-kv__v">{{ $order->district }}</div></div>@endif
                            <div>
                                <div class="zc-od-kv__k" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                    <span>Address</span>
                                    <button type="button" class="zc-od-ic" title="Edit address" onclick="document.getElementById('zc-od-address-view').style.display='none';document.getElementById('zc-od-address-edit').style.display='block';">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                </div>
                                <div class="zc-od-kv__v" style="white-space:pre-line;">{{ $order->address }}</div>
                            </div>
                        </div>
                        <form id="zc-od-address-edit" method="POST" action="{{ route('orders.address.update', $order) }}" style="display:none;">
                            @csrf
                            @method('PATCH')
                            <div class="zc-od-kv__k">District</div>
                            <input type="text" name="district" class="studio-form-control" value="{{ old('district', $order->district) }}" style="margin-bottom:8px;">
                            <div class="zc-od-kv__k">Address</div>
                            <textarea name="address" class="studio-form-control" rows="3" required>{{ old('address', $order->address) }}</textarea>
                            <div style="display:flex;gap:8px;margin-top:8px;">
                                <button type="submit" class="studio-command-button studio-command-button--primary">Save</button>
                                <button type="button" class="studio-command-button" onclick="document.getElementById('zc-od-address-edit').style.display='none';document.getElementById('zc-od-address-view').style.display='block';">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            {{-- Shipment --}}
            @if ($order->shipment)
                <section class="zc-od-card">
                    <div class="zc-od-card__head"><h2>Shipment</h2></div>
                    <div class="zc-od-card__body">
                        <div class="zc-od-kv">
                            <div><div class="zc-od-kv__k">Courier</div><div class="zc-od-kv__v zc-op-strong">{{ $order->shipment->courierProvider?->name ?? 'Assigned' }}</div></div>
                            @if ($order->shipment->tracking_number)<div><div class="zc-od-kv__k">Memo / Tracking</div><div class="zc-od-kv__v">{{ $order->shipment->tracking_number }}</div></div>@endif
                        </div>
                    </div>
                </section>
            @endif

            {{-- Update status (AJAX) --}}
            <section class="zc-od-card">
                <div class="zc-od-card__head"><h2>Update status</h2></div>
                <div class="zc-od-card__body">
                    <form data-ajax-form method="POST" action="{{ route('orders.status', $order) }}">
                        @csrf
                        <input type="hidden" name="detail" value="1">
                        <div class="zc-od-field">
                            <label for="status">New status</label>
                            <select id="status" name="status" class="studio-form-control">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="zc-od-field">
                            <label for="note">Note (optional)</label>
                            <textarea id="note" name="note" rows="2" class="studio-form-control" placeholder="Internal note for this change"></textarea>
                        </div>
                        <button type="submit" data-ajax-submit class="studio-command-button studio-command-button--primary" style="width:100%;justify-content:center;">Save status</button>
                    </form>
                </div>
            </section>

            {{-- Notes (AJAX add) --}}
            <section class="zc-od-card">
                <div class="zc-od-card__head"><h2>Notes</h2></div>
                <div class="zc-od-card__body">
                    <form data-ajax-form method="POST" action="{{ route('orders.comment', $order) }}" style="margin-bottom:14px;">
                        @csrf
                        <input type="hidden" name="detail" value="1">
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="note" class="studio-form-control" placeholder="Add a note…" style="flex:1;">
                            <button type="submit" data-ajax-submit class="studio-command-button studio-command-button--primary">Add</button>
                        </div>
                    </form>
                    @include('studio.orders.partials._detail-notes', ['order' => $order])
                </div>
            </section>
        </div>
    </div>
</div>

@push('studio-scripts')
<script>
    // Copy phone number to clipboard (delegated).
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) return;
        navigator.clipboard && navigator.clipboard.writeText(btn.getAttribute('data-copy'));
        var t = document.querySelector('[data-ajax-toast]');
        if (t) { t.textContent = 'Phone copied'; t.classList.add('is-visible'); setTimeout(function(){ t.classList.remove('is-visible'); }, 1600); }
    });

    // Product search-and-add on the editable Items card. Delegated so it keeps
    // working after the items region is swapped in by an AJAX edit.
    (function () {
        var timer = null;
        function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        document.addEventListener('input', function (e) {
            var input = e.target.closest('[data-add-search]');
            if (!input) return;
            var form = input.closest('[data-add-form]');
            var box = form.querySelector('[data-add-results]');
            var url = form.getAttribute('data-search-url');
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 1) { box.hidden = true; box.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        var items = d.results || [];
                        if (!items.length) { box.innerHTML = '<div class="zc-od-res zc-od-res--empty">No products found</div>'; box.hidden = false; return; }
                        box.innerHTML = items.map(function (p) {
                            var html = '<button type="button" class="zc-od-res" data-pick data-product="' + p.id + '"><span class="nm">' + esc(p.name) + '</span><span class="pr">৳' + p.price + '</span></button>';
                            (p.variants || []).forEach(function (v) {
                                html += '<button type="button" class="zc-od-res zc-od-res--var" data-pick data-product="' + p.id + '" data-variant="' + v.id + '"><span class="nm">↳ ' + esc(v.label || '') + '</span><span class="pr">৳' + v.price + '</span></button>';
                            });
                            return html;
                        }).join('');
                        box.hidden = false;
                    }).catch(function () { box.hidden = true; });
            }, 220);
        });

        document.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-pick]');
            if (pick) {
                var form = pick.closest('[data-add-form]');
                form.querySelector('[data-add-product]').value = pick.getAttribute('data-product');
                form.querySelector('[data-add-variant]').value = pick.getAttribute('data-variant') || '';
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
                return;
            }
            if (!e.target.closest('[data-add-form]')) {
                document.querySelectorAll('[data-add-results]').forEach(function (b) { b.hidden = true; });
            }
        });
    })();
</script>
@endpush
@endsection
