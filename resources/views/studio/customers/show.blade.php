@extends('layouts.studio')
@section('title', $customer->name ?: 'Customer')
@section('subtitle', 'Customer profile')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cp-head{display:flex;gap:1.1rem;align-items:center;flex-wrap:wrap;}
    .zc-cp-av{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#1f7a3d,#155e2e);color:#fff;display:grid;place-items:center;font-size:1.5rem;font-weight:800;flex:none;}
    .zc-cp-id{min-width:0;flex:1;}
    .zc-cp-id h1{margin:0;font-size:1.35rem;font-weight:800;color:var(--studio-text);}
    .zc-cp-meta{display:flex;flex-wrap:wrap;gap:0.5rem 1.1rem;margin-top:6px;font-size:0.85rem;color:var(--studio-muted);}
    .zc-cp-meta span{display:inline-flex;align-items:center;gap:6px;}
    .zc-cp-tag{display:inline-flex;align-items:center;gap:5px;padding:0.15rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:800;}
    .zc-cp-tag--regular{background:rgba(52,199,123,0.14);color:#1c8a4e;}
    .zc-cp-tag--loyal{background:rgba(242,162,12,0.16);color:#a5700a;}
    .zc-cp-tag--risky{background:rgba(224,90,74,0.14);color:#c0392b;}
    .zc-cp-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.9rem;margin-top:1.3rem;}
    .zc-cp-stat{border:1px solid var(--studio-border);border-radius:13px;padding:0.9rem 1rem;background:var(--studio-surface);}
    .zc-cp-stat .k{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--studio-muted);}
    .zc-cp-stat .v{font-size:1.4rem;font-weight:800;color:var(--studio-text);margin-top:3px;}
    .zc-cp-stat .sub{font-size:0.72rem;color:var(--studio-muted);}
    .zc-cp-grid{display:grid;grid-template-columns:1.55fr 1fr;gap:1.3rem;margin-top:1.3rem;align-items:start;}
    @media (max-width:960px){.zc-cp-grid{grid-template-columns:1fr;}}
    .zc-cp-card{border:1px solid var(--studio-border);border-radius:14px;background:var(--studio-surface);overflow:hidden;}
    .zc-cp-card h3{margin:0;padding:0.85rem 1.1rem;font-size:0.9rem;font-weight:800;color:var(--studio-text);border-bottom:1px solid var(--studio-border);background:var(--studio-surface-soft);display:flex;justify-content:space-between;align-items:center;}
    .zc-cp-card h3 small{font-weight:700;color:var(--studio-muted);}
    .zc-ostat{display:inline-flex;align-items:center;padding:0.12rem 0.55rem;border-radius:999px;font-size:0.7rem;font-weight:800;text-transform:capitalize;}
    .zc-ostat--delivered{background:rgba(52,199,123,.15);color:#1c8a4e;} .zc-ostat--pending{background:rgba(242,162,12,.16);color:#a5700a;}
    .zc-ostat--confirmed,.zc-ostat--processing,.zc-ostat--shipped{background:rgba(59,110,165,.15);color:#2b567f;}
    .zc-ostat--cancelled,.zc-ostat--returned{background:rgba(224,90,74,.15);color:#c0392b;}
    .zc-cartlist{list-style:none;margin:0;padding:0.3rem 0;max-height:560px;overflow:auto;}
    .zc-cartlist li{display:flex;gap:0.8rem;align-items:center;padding:0.7rem 1.1rem;border-bottom:1px solid var(--studio-border);}
    .zc-cartlist li:last-child{border-bottom:none;}
    .zc-cartlist__img{width:52px;height:52px;border-radius:10px;object-fit:cover;flex:none;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:grid;place-items:center;color:var(--studio-muted);}
    .zc-cartlist__b{min-width:0;flex:1;}
    .zc-cartlist__b b{display:block;font-size:0.86rem;color:var(--studio-text);font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .zc-cartlist__b span{display:block;font-size:0.74rem;color:var(--studio-muted);font-weight:700;}
    .zc-cartlist__b em{font-style:normal;font-size:0.72rem;color:var(--studio-muted);}
    .zc-cartlist__price{font-weight:800;font-size:0.92rem;color:var(--studio-text);white-space:nowrap;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
@php
    $tag = ($behaviorTag === 'Loyal') ? 'loyal' : (($behaviorTag === 'Risky') ? 'risky' : 'regular');
@endphp
<div class="space-y-4">
    <div class="zc-sm-head" style="margin-bottom:0.4rem;">
        <a href="{{ route('customers.index') }}" class="studio-command-button">← All customers</a>
        <div style="flex:1;"></div>
        <button type="button" id="zc-cp-block" class="zc-cu-block {{ $blocked ? 'zc-cu-block--off' : 'zc-cu-block--on' }}"
            data-block="{{ route('customers.block', $customer) }}" data-unblock="{{ route('customers.unblock', $customer) }}" data-state="{{ $blocked ? 'blocked' : 'active' }}"
            style="border:none;border-radius:9px;padding:0.55rem 1rem;font-weight:800;font-size:0.82rem;color:#fff;cursor:pointer;{{ $blocked ? 'background:#2aa564;' : 'background:#e0483a;' }}">
            <span class="lbl">{{ $blocked ? 'Unblock customer' : 'Block customer' }}</span>
        </button>
    </div>

    <div class="studio-card" style="padding:1.4rem 1.6rem;">
        <div class="zc-cp-head">
            <div class="zc-cp-av">{{ strtoupper(mb_substr($customer->name ?: 'G', 0, 1)) }}</div>
            <div class="zc-cp-id">
                <h1>{{ $customer->name ?: 'Guest customer' }}</h1>
                <div class="zc-cp-meta">
                    <span>📞 {{ $customer->phone }}</span>
                    @if ($customer->email)<span>✉ {{ $customer->email }}</span>@endif
                    @if ($customer->address)<span>📍 {{ $customer->address }}</span>@endif
                    <span>🗓 Joined {{ optional($customer->created_at)->format('d M Y') ?? '—' }}</span>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                <span class="zc-cp-tag zc-cp-tag--{{ $tag }}">{{ $behaviorTag }}</span>
                <span class="zc-sm-pill zc-cp-statuspill {{ $blocked ? 'zc-sm-pill--off' : 'zc-sm-pill--on' }}">{{ $blocked ? 'Blocked' : 'Active' }}</span>
            </div>
        </div>

        <div class="zc-cp-stats">
            <div class="zc-cp-stat"><div class="k">Total orders</div><div class="v">{{ $metrics['total_orders'] }}</div><div class="sub">{{ $metrics['pending_orders'] }} pending</div></div>
            <div class="zc-cp-stat"><div class="k">Delivered</div><div class="v">{{ $metrics['delivered_orders'] }}</div><div class="sub">{{ $metrics['delivery_rate'] }}% success</div></div>
            <div class="zc-cp-stat"><div class="k">Cancelled / returned</div><div class="v">{{ $metrics['cancelled_orders'] + $metrics['returned_orders'] }}</div><div class="sub">{{ $metrics['cancel_rate'] }}% cancel</div></div>
            <div class="zc-cp-stat"><div class="k">Total value</div><div class="v">৳{{ number_format((float) ($financial['total_revenue'] ?? 0)) }}</div><div class="sub">৳{{ number_format((float) ($financial['delivered_revenue'] ?? 0)) }} delivered</div></div>
            <div class="zc-cp-stat"><div class="k">Avg. order</div><div class="v">৳{{ number_format((float) $metrics['average_order_value']) }}</div><div class="sub">per order</div></div>
            <div class="zc-cp-stat"><div class="k">COD score</div><div class="v">{{ (int) $customer->cod_score }}</div><div class="sub">reliability</div></div>
        </div>
    </div>

    <div class="zc-cp-grid">
        {{-- ORDER HISTORY --}}
        <div class="zc-cp-card">
            <h3>Order history <small>{{ $orders->count() }} shown</small></h3>
            <table class="zc-sm-tbl" style="margin:0;">
                <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><a href="{{ route('orders.show', $order) }}" style="font-weight:800;color:var(--studio-accent);">#{{ $order->order_number }}</a></td>
                            <td style="color:var(--studio-muted);">{{ optional($order->created_at)->format('d M Y') }}</td>
                            <td>{{ $order->items_count }}</td>
                            <td style="font-weight:700;">৳{{ number_format((float) $order->total) }}</td>
                            <td><span class="zc-ostat zc-ostat--{{ $order->status }}">{{ $order->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="zc-sm-empty">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PRODUCTS ADDED TO CART --}}
        <div class="zc-cp-card">
            <h3>Cart products <small>{{ $cartProducts->count() }} {{ \Illuminate\Support\Str::plural('product', $cartProducts->count()) }}</small></h3>
            @if ($cartProducts->isEmpty())
                <div class="zc-sm-empty">This customer hasn't added any product to cart yet.</div>
            @else
                <ul class="zc-cartlist">
                    @foreach ($cartProducts as $row)
                        @php $p = $row['product']; @endphp
                        <li>
                            @if ($row['image'])<img src="{{ $row['image'] }}" alt="" class="zc-cartlist__img">
                            @else<span class="zc-cartlist__img"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 8v9a1 1 0 0 1-.6.9l-6.6 3a2 2 0 0 1-1.6 0l-6.6-3A1 1 0 0 1 4 17V8"/><path d="M2.5 7 12 3l9.5 4-9.5 4z"/></svg></span>@endif
                            <div class="zc-cartlist__b">
                                <b>{{ $p->name }}</b>
                                <span>SKU: {{ $p->sku ?: '—' }}</span>
                                <em title="{{ optional($row['added_at'])->format('d M Y, g:i a') }}">Added {{ optional($row['added_at'])->diffForHumans() }}</em>
                            </div>
                            <div class="zc-cartlist__price">৳{{ number_format((float) $p->price) }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-cu-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-cu-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2400); }
        var btn=document.getElementById('zc-cp-block');
        btn && btn.addEventListener('click', function(){
            var blocked = btn.getAttribute('data-state')==='blocked';
            var url = blocked ? btn.getAttribute('data-unblock') : btn.getAttribute('data-block');
            if(!blocked && !confirm('Block this customer? They will not be able to place new orders.')) return;
            btn.disabled=true;
            fetch(url,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){
                    btn.disabled=false;
                    if(!res.ok){ showToast((res.d&&res.d.message)||'Failed',true); return; }
                    var nb=res.d.blocked;
                    btn.setAttribute('data-state', nb?'blocked':'active');
                    btn.style.background = nb?'#2aa564':'#e0483a';
                    btn.querySelector('.lbl').textContent = nb?'Unblock customer':'Block customer';
                    var pill=document.querySelector('.zc-cp-statuspill');
                    pill.textContent=nb?'Blocked':'Active';
                    pill.className='zc-sm-pill zc-cp-statuspill '+(nb?'zc-sm-pill--off':'zc-sm-pill--on');
                    showToast(res.d.message);
                }).catch(function(){ btn.disabled=false; showToast('Failed',true); });
        });
    })();
</script>
@endpush
@endsection
