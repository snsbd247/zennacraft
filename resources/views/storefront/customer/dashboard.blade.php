@extends('layouts.app')
@section('title', 'My Account — '.$storeName)


@section('content')
@php
    $displayName = $customer->name ?: $customer->phone;
    $initial = strtoupper(mb_substr($customer->name ?: $customer->phone ?: 'Z', 0, 1));
    $tier = $loyaltyProfile['tier'] ?? 'New';
    $tierClass = 'zc-tier--'.strtolower($tier);
    $activeCount = max(0, ($orderStats['total'] ?? 0) - ($orderStats['delivered'] ?? 0) - ($orderStats['cancelled'] ?? 0) - ($orderStats['returned'] ?? 0));
    $money = fn ($v) => number_format((float) $v, 0);
    $statusMap = [
        'pending' => ['New', 'is-pending'], 'confirmed' => ['Confirmed', 'is-active'],
        'processing' => ['Processing', 'is-active'], 'shipped' => ['Shipped', 'is-active'],
        'delivered' => ['Delivered', 'is-delivered'], 'cancelled' => ['Cancelled', 'is-cancel'],
        'returned' => ['Returned', 'is-cancel'],
    ];
    // Loyalty progress toward 5 delivered orders (VIP threshold).
    $delivered = $loyaltyProfile['delivered_orders'] ?? 0;
    $loyalPct = min(100, (int) round(($delivered / 5) * 100));
@endphp
<section class="zc-acct">
<section class="zc-hero2">
    <div class="zc-wrap">
        <div class="zc-crumbs"><a href="{{ route('storefront.home') }}">Home</a> <span>/</span> <span>My Account</span></div>
        <div class="zc-hero2__row">
            <div class="zc-avatar">{{ $initial }}</div>
            <div style="flex:1 1 auto;min-width:0;">
                <h1>Welcome back, {{ $displayName }}</h1>
                <div class="sub">Your orders, tracking and profile — all in one place.</div>
            </div>
            <span class="zc-tier {{ $tierClass }}">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17.8 5.9 20.4l1.4-6.8L2.2 9l6.9-.7z"/></svg>
                {{ $tier }} member
            </span>
        </div>
    </div>
</section>

<section class="zc-sec zc-wrap">
    @if (session('success'))<div class="zc-note" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

    <div class="zc-stats">
        <a href="{{ route('customer.orders') }}" class="zc-stat">
            <div class="zc-stat__ico i-leaf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2h9l4 4v16H6z"/><path d="M9 12h7M9 16h7"/></svg></div>
            <div class="zc-stat__n">{{ $orderStats['total'] ?? 0 }}</div>
            <div class="zc-stat__l">Total orders</div>
        </a>
        <div class="zc-stat">
            <div class="zc-stat__ico i-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="6" width="15" height="11" rx="1"/><path d="M16 9h4l3 3v5h-7z"/><circle cx="6" cy="18" r="1.6"/><circle cx="18" cy="18" r="1.6"/></svg></div>
            <div class="zc-stat__n">{{ $activeCount }}</div>
            <div class="zc-stat__l">In progress</div>
        </div>
        <div class="zc-stat">
            <div class="zc-stat__ico i-leaf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"/></svg></div>
            <div class="zc-stat__n">{{ $orderStats['delivered'] ?? 0 }}</div>
            <div class="zc-stat__l">Delivered</div>
        </div>
        <div class="zc-stat">
            <div class="zc-stat__ico i-honey"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="zc-stat__n">৳{{ $money($orderStats['spent'] ?? 0) }}</div>
            <div class="zc-stat__l">Total spent</div>
        </div>
    </div>

    <div class="zc-acct-grid">
        {{-- Recent orders --}}
        <div class="zc-panel">
            <div class="zc-panel__head">
                <h2>Recent orders</h2>
                <a href="{{ route('customer.orders') }}">View all →</a>
            </div>
            @forelse ($recentOrders as $order)
                @php [$label, $cls] = $statusMap[$order->status] ?? [ucfirst($order->status), 'is-active']; $first = $order->items->first(); $more = $order->items->count() - 1; @endphp
                <div class="zc-ord">
                    <div class="zc-ord__main">
                        <div class="zc-ord__no">{{ $order->order_number }}</div>
                        <div class="zc-ord__meta">{{ optional($order->created_at)->format('d M Y') }}@if ($first) · {{ \Illuminate\Support\Str::limit($first->product_name, 26) }}@if ($more > 0) +{{ $more }} more @endif @endif</div>
                    </div>
                    <span class="zc-pill {{ $cls }}">{{ $label }}</span>
                    <div class="zc-ord__total">৳{{ $money($order->total) }}</div>
                    <div class="zc-ord__acts">
                        <a href="{{ route('customer.orders.show', $order) }}" class="pri">View</a>
                        <a href="{{ route('customer.orders.tracking', $order) }}">Track</a>
                    </div>
                </div>
            @empty
                <div class="zc-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2h9l4 4v16H6z"/><path d="M9 12h7M9 16h5"/></svg>
                    <div style="font-weight:800;margin-bottom:4px;">No orders yet</div>
                    <div class="zc-muted" style="font-size:13px;margin-bottom:16px;">When you place an order, it'll show up here.</div>
                    <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--primary zc-btn--sm">Start shopping</a>
                </div>
            @endforelse
        </div>

        {{-- Sidebar --}}
        <aside class="zc-side">
            {{-- Profile --}}
            <div class="zc-panel">
                <div class="zc-panel__head">
                    <h2>Profile</h2>
                    <a href="#" role="button" data-prof-toggle>Edit</a>
                </div>
                <div class="zc-prof__id">
                    <div class="zc-prof__av">{{ $initial }}</div>
                    <div style="min-width:0;">
                        <div style="font-weight:800;" data-prof-name>{{ $customer->name ?: 'Add your name' }}</div>
                        <div class="zc-muted" style="font-size:13px;">{{ $customer->phone }}</div>
                    </div>
                </div>
                <div class="zc-prof__row"><span>Email</span><b data-prof-email>{{ $customer->email ?: '—' }}</b></div>
                <div class="zc-prof__row"><span>Member since</span><b>{{ optional($journey['customer_since'] ?? $customer->created_at)->format('M Y') }}</b></div>
                @if (($orderStats['coupon_savings'] ?? 0) > 0)
                    <div class="zc-prof__row"><span>Saved with coupons</span><b style="color:var(--leaf-deep);">৳{{ $money($orderStats['coupon_savings']) }}</b></div>
                @endif

                <form class="zc-prof-form" data-prof-form method="POST" action="{{ route('customer.profile.update') }}">
                    @csrf @method('PATCH')
                    <label>Full name</label>
                    <input type="text" name="name" class="zc-input" value="{{ $customer->name }}" required maxlength="255">
                    <label>Email (optional)</label>
                    <input type="email" name="email" class="zc-input" value="{{ $customer->email }}" maxlength="255">
                    <label>Default address (optional)</label>
                    <input type="text" name="address" class="zc-input" value="{{ $customer->address }}" maxlength="1000">
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <button type="submit" class="zc-btn zc-btn--primary zc-btn--sm" data-prof-save>Save changes</button>
                        <button type="button" class="zc-btn zc-btn--outline zc-btn--sm" data-prof-cancel>Cancel</button>
                    </div>
                    <div class="msg" data-prof-msg hidden></div>
                </form>
            </div>

            {{-- Loyalty --}}
            <div class="zc-panel">
                <div class="zc-panel__head"><h2>Loyalty</h2><span class="zc-tier {{ $tierClass }}" style="color:var(--leaf-deep);background:var(--leaf-soft);">{{ $tier }}</span></div>
                <div class="zc-loyal">
                    <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:700;"><span>{{ $delivered }} delivered</span><span class="zc-muted">Goal: 5 (VIP)</span></div>
                    <div class="zc-loyal__bar"><div class="zc-loyal__fill" style="width:{{ $loyalPct }}%;"></div></div>
                    <div class="zc-loyal__note">{{ $loyaltyProfile['next_milestone'] ?? 'Keep shopping to unlock rewards.' }}</div>
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="zc-panel">
                <div class="zc-quick">
                    <a href="{{ route('tracking.form') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="6" width="15" height="11" rx="1"/><path d="M16 9h4l3 3v5h-7z"/></svg> Track an order</a>
                    <a href="{{ route('storefront.products') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6h15l-1.6 9.2a2 2 0 0 1-2 1.8H9.6a2 2 0 0 1-2-1.7L6 3H3"/></svg> Continue shopping</a>
                    <form method="POST" action="{{ route('customer.logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 17l5-5-5-5M21 12H9M9 4H5v16h4"/></svg> Sign out</button>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</section>
</section>
@endsection

@push('storefront-scripts')
<script>
(function () {
    var toggle = document.querySelector('[data-prof-toggle]');
    var form = document.querySelector('[data-prof-form]');
    var cancel = document.querySelector('[data-prof-cancel]');
    var msg = document.querySelector('[data-prof-msg]');
    if (!toggle || !form) return;

    var open = function (show) { form.classList.toggle('is-open', show); if (show) form.querySelector('[name=name]').focus(); };
    toggle.addEventListener('click', function (e) { e.preventDefault(); open(!form.classList.contains('is-open')); });
    cancel.addEventListener('click', function () { open(false); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var save = form.querySelector('[data-prof-save]');
        save.disabled = true; save.textContent = 'Saving…';
        msg.hidden = true;
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            if (res.ok && res.d.success) {
                var c = res.d.customer || {};
                document.querySelector('[data-prof-name]').textContent = c.name || 'Add your name';
                document.querySelector('[data-prof-email]').textContent = c.email || '—';
                document.querySelectorAll('.zc-avatar, .zc-prof__av').forEach(function (el) {
                    el.textContent = (c.name || '{{ $customer->phone }}').trim().charAt(0).toUpperCase();
                });
                msg.textContent = 'Profile updated ✓'; msg.className = 'msg ok'; msg.hidden = false;
                setTimeout(function () { form.classList.remove('is-open'); }, 900);
            } else {
                var first = res.d.errors ? Object.values(res.d.errors)[0][0] : (res.d.message || 'Could not save. Try again.');
                msg.textContent = first; msg.className = 'msg err'; msg.hidden = false;
            }
        }).catch(function () {
            msg.textContent = 'Could not reach the server.'; msg.className = 'msg err'; msg.hidden = false;
        }).finally(function () {
            save.disabled = false; save.textContent = 'Save changes';
        });
    });
})();
</script>
@endpush
