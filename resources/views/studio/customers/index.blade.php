@extends('layouts.studio')
@section('title', 'Customers')
@section('subtitle', 'Customer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cu-toolbar{display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;margin-bottom:1.25rem;}
    .zc-cu-toolbar select,.zc-cu-toolbar input{min-width:0;}
    .zc-cu-toolbar .grow{flex:1;min-width:180px;}
    .zc-cu-cust{line-height:1.45;}
    .zc-cu-cust b{font-weight:800;color:var(--studio-text);display:block;}
    .zc-cu-cust .ph{font-size:0.82rem;color:var(--studio-text);}
    .zc-cu-cust .ad{font-size:0.76rem;color:var(--studio-muted);}
    .zc-cu-tag{display:inline-flex;align-items:center;gap:5px;padding:0.15rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:800;}
    .zc-cu-tag::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;}
    .zc-cu-tag--regular{background:rgba(52,199,123,0.14);color:#1c8a4e;}
    .zc-cu-tag--loyal{background:rgba(242,162,12,0.16);color:#a5700a;}
    .zc-cu-tag--risky{background:rgba(224,90,74,0.14);color:#c0392b;}
    .zc-cu-order{display:inline-grid;place-items:center;min-width:1.9rem;height:1.9rem;padding:0 0.5rem;border-radius:999px;background:rgba(52,199,123,0.12);color:#1c8a4e;font-weight:800;font-size:0.82rem;}
    .zc-cu-block{border:none;border-radius:9px;padding:0.42rem 0.8rem;font-weight:800;font-size:0.76rem;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:#fff;}
    .zc-cu-block--on{background:#e0483a;} .zc-cu-block--off{background:#2aa564;}
    .zc-cu-block svg{width:0.85rem;height:0.85rem;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h1 class="studio-section-title" style="justify-content:center;margin-bottom:1.1rem;">Customers</h1>

        @include('studio.partials.stat-strip')
        <div class="zc-stat-strip">
            <div class="zc-stat zc-stat--blue">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 6.2a3 3 0 0 1 0 5.6"/><path d="M17.5 14.4A5.5 5.5 0 0 1 20.5 19"/></svg></span>
                <div><div class="zc-stat__v" data-countup>{{ number_format($totalCustomers ?? 0) }}</div><div class="zc-stat__l">Total customers</div></div>
            </div>
            <a href="{{ route('customers.index', ['status' => 'active']) }}" class="zc-stat zc-stat--green">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                <div><div class="zc-stat__v" data-countup>{{ number_format($activeCount ?? 0) }}</div><div class="zc-stat__l">Active</div></div>
            </a>
            <a href="{{ route('customers.index', ['status' => 'blocked']) }}" class="zc-stat zc-stat--red">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg></span>
                <div><div class="zc-stat__v" data-countup>{{ number_format($blockedCount ?? 0) }}</div><div class="zc-stat__l">Blocked</div></div>
            </a>
            <div class="zc-stat zc-stat--amber">
                <span class="zc-stat__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/><path d="M18 3v4M20 5h-4"/></svg></span>
                <div><div class="zc-stat__v" data-countup>{{ number_format($newCustomers ?? 0) }}</div><div class="zc-stat__l">New · 30 days</div></div>
            </div>
        </div>

        <form method="GET" action="{{ route('customers.index') }}" class="zc-cu-toolbar">
            <a href="{{ route('customers.export', array_filter(['q' => $term])) }}" class="studio-command-button">⬇ Export CSV</a>
            <select name="status" class="studio-form-control" style="max-width:190px;" onchange="this.form.submit()">
                @foreach (['all' => 'All customers', 'active' => 'Active only', 'blocked' => 'Blocked only'] as $v => $l)
                    <option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $term }}" class="studio-form-control grow" placeholder="customer name or 01xxxxxxxxx">
            <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
        </form>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Customer</th><th>Behavior</th><th>Total Order</th><th>City</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    @php
                        $blocked = in_array($customer->id, $blockedIds, true);
                        $delivered = (int) $customer->delivered_orders;
                        $bad = (int) $customer->cancelled_orders + (int) $customer->returned_orders;
                        $tot = max(1, (int) $customer->total_orders);
                        $tag = ($bad >= 2 && $bad / $tot >= 0.4) ? 'risky' : ($delivered >= 3 ? 'loyal' : 'regular');
                    @endphp
                    <tr data-row="{{ $customer->id }}">
                        <td>{{ $loop->iteration + ($customers->firstItem() ? $customers->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-cu-cust">
                                <b>{{ $customer->name ?: 'Guest' }}</b>
                                <span class="ph">{{ $customer->phone }}</span>
                                @if ($customer->address)<span class="ad">{{ \Illuminate\Support\Str::limit($customer->address, 42) }}</span>@endif
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-start;">
                                <span class="zc-cu-tag zc-cu-tag--{{ $tag }}">{{ ucfirst($tag) }}</span>
                                <span class="zc-sm-pill zc-cu-status {{ $blocked ? 'zc-sm-pill--off' : 'zc-sm-pill--on' }}">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                            </div>
                        </td>
                        <td><span class="zc-cu-order">{{ $customer->orders_count }}</span></td>
                        <td>{{ $cityByCustomer[$customer->id] ?? '—' }}</td>
                        <td>
                            <div class="zc-sm-act">
                                <button type="button" class="zc-cu-block {{ $blocked ? 'zc-cu-block--off' : 'zc-cu-block--on' }}" data-block="{{ route('customers.block', $customer) }}" data-unblock="{{ route('customers.unblock', $customer) }}" data-state="{{ $blocked ? 'blocked' : 'active' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                    <span class="lbl">{{ $blocked ? 'Unblock' : 'Block' }}</span>
                                </button>
                                <a href="{{ route('customers.show', $customer) }}" class="zc-sm-btn zc-sm-btn--view" title="View profile"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="zc-sm-empty">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="zc-sm-pager">
            <span>Showing {{ $customers->firstItem() ?? 0 }} to {{ $customers->lastItem() ?? 0 }} of total {{ $customers->total() }} customers</span>
            @if ($customers->hasPages())
                <span style="margin-left:auto;"></span>
                @if (!$customers->onFirstPage())<a href="{{ $customers->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif
                @if ($customers->hasMorePages())<a href="{{ $customers->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif
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
        document.addEventListener('click', function(e){
            var btn=e.target.closest('[data-block]'); if(!btn) return;
            var blocked = btn.getAttribute('data-state')==='blocked';
            var url = blocked ? btn.getAttribute('data-unblock') : btn.getAttribute('data-block');
            if(!blocked && !confirm('Block this customer? They will not be able to place new orders.')) return;
            btn.disabled = true;
            fetch(url,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){
                    btn.disabled=false;
                    if(!res.ok){ showToast((res.d&&res.d.message)||'Failed',true); return; }
                    var nowBlocked = res.d.blocked;
                    btn.setAttribute('data-state', nowBlocked?'blocked':'active');
                    btn.className='zc-cu-block '+(nowBlocked?'zc-cu-block--off':'zc-cu-block--on');
                    btn.querySelector('.lbl').textContent = nowBlocked?'Unblock':'Block';
                    var pill=btn.closest('tr').querySelector('.zc-cu-status');
                    pill.textContent = nowBlocked?'Blocked':'Active';
                    pill.className='zc-sm-pill zc-cu-status '+(nowBlocked?'zc-sm-pill--off':'zc-sm-pill--on');
                    showToast(res.d.message);
                }).catch(function(){ btn.disabled=false; showToast('Failed',true); });
        });
    })();
</script>
@endpush
@endsection
