@extends('layouts.studio')
@section('title', 'Incomplete Orders')
@section('subtitle', 'Customer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-rc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.9rem;margin-bottom:1.2rem;}
    .zc-rc-stat{border:1px solid var(--studio-border);border-radius:13px;padding:0.85rem 1rem;background:var(--studio-surface);}
    .zc-rc-stat .k{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--studio-muted);}
    .zc-rc-stat .v{font-size:1.5rem;font-weight:800;color:var(--studio-text);margin-top:2px;}
    .zc-rc-tabs{display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem;}
    .zc-rc-tab{padding:0.4rem 0.9rem;border-radius:999px;border:1px solid var(--studio-border);font-size:0.8rem;font-weight:700;color:var(--studio-muted);text-decoration:none;}
    .zc-rc-tab.is-active{background:var(--studio-accent,#1f7a3d);color:#fff;border-color:transparent;}
    .zc-rc-cust b{font-weight:800;color:var(--studio-text);display:block;}
    .zc-rc-cust span{font-size:0.8rem;color:var(--studio-muted);}
    .zc-rc-addr{font-size:0.82rem;color:var(--studio-text);max-width:260px;}
    .zc-rc-none{color:var(--studio-muted);font-style:italic;}
    .zc-rc-call{display:inline-grid;place-items:center;width:2rem;height:2rem;border-radius:8px;color:#fff;}
    .zc-rc-call--tel{background:#2aa564;} .zc-rc-call--wa{background:#25d366;}
    .zc-rc-call svg{width:1rem;height:1rem;}
    .zc-rc-status{border:1px solid var(--studio-border);border-radius:8px;padding:0.3rem 0.5rem;font-size:0.78rem;font-weight:700;background:var(--studio-surface);color:var(--studio-text);}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h1 class="studio-section-title" style="justify-content:center;">Incomplete Orders</h1>
        <p style="text-align:center;font-size:0.82rem;color:var(--studio-muted);margin:2px 0 1.2rem;">Customers who filled in their checkout details but didn't click <b>Confirm</b> — reach out and win the order back.</p>

        <div class="zc-rc-stats">
            <div class="zc-rc-stat"><div class="k">With details</div><div class="v">{{ $stats['incomplete'] }}</div></div>
            <div class="zc-rc-stat"><div class="k">Today</div><div class="v">{{ $stats['today'] }}</div></div>
            <div class="zc-rc-stat"><div class="k">Callable</div><div class="v">{{ $stats['callable'] }}</div></div>
            <div class="zc-rc-stat"><div class="k">Recovered</div><div class="v">{{ $stats['recovered'] }}</div></div>
        </div>

        <div class="zc-rc-tabs">
            @foreach (['incomplete' => 'With details', 'callback' => 'Has phone', 'abandoned' => 'No details', 'recovered' => 'Recovered', 'all' => 'All'] as $t => $label)
                <a href="{{ route('recoveries.index', array_filter(['tab' => $t, 'q' => $term])) }}" class="zc-rc-tab {{ $tab === $t ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
            <form method="GET" action="{{ route('recoveries.index') }}" style="margin-left:auto;display:flex;gap:0.5rem;">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ $term }}" class="studio-form-control" placeholder="name or phone" style="min-width:190px;">
                <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
            </form>
        </div>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Customer</th><th>Address</th><th>Product</th><th>Status</th><th>When</th><th style="text-align:right;">Follow up</th></tr></thead>
            <tbody>
                @forelse ($recoveries as $rec)
                    @php $waDigits = preg_replace('/\D/', '', (string) $rec->customer_phone); @endphp
                    <tr>
                        <td>{{ $loop->iteration + ($recoveries->firstItem() ? $recoveries->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-rc-cust">
                                <b>{{ $rec->customer_name ?: 'Unknown' }}</b>
                                @if ($rec->customer_phone)<span>{{ $rec->customer_phone }}</span>@endif
                                @if ($rec->customer_email)<span>{{ $rec->customer_email }}</span>@endif
                            </div>
                        </td>
                        <td>@if ($rec->address)<div class="zc-rc-addr">{{ \Illuminate\Support\Str::limit($rec->address, 70) }}</div>@else<span class="zc-rc-none">—</span>@endif</td>
                        <td>
                            @if ($rec->product)
                                <div class="zc-sm-prod">
                                    @php $pimg = $mediaUrl($rec->product->thumbnail); @endphp
                                    @if ($pimg)<img src="{{ $pimg }}" alt="" class="zc-sm-thumb">
                                    @else<span class="zc-sm-thumb"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 8v9a1 1 0 0 1-.6.9l-6.6 3a2 2 0 0 1-1.6 0l-6.6-3A1 1 0 0 1 4 17V8"/><path d="M2.5 7 12 3l9.5 4-9.5 4z"/></svg></span>@endif
                                    <div style="min-width:0;">
                                        <div class="zc-sm-name">{{ \Illuminate\Support\Str::limit($rec->product->name, 26) }}</div>
                                        <div style="font-size:0.74rem;color:var(--studio-muted);">SKU: {{ $rec->product->sku ?: '—' }}@if ($rec->variant) · {{ $rec->variant->name }}@endif @if ($rec->quantity > 1)· ×{{ $rec->quantity }}@endif</div>
                                    </div>
                                </div>
                            @else
                                <span class="zc-rc-none">Cart checkout</span>
                            @endif
                        </td>
                        <td>
                            <select class="zc-rc-status" data-status="{{ route('recoveries.status', $rec) }}">
                                <option value="" @selected(!array_key_exists($rec->status,$statuses))>{{ ucfirst(str_replace('_',' ',$rec->status)) }}</option>
                                @foreach ($statuses as $sv => $sl)<option value="{{ $sv }}" @selected($rec->status === $sv)>{{ $sl }}</option>@endforeach
                            </select>
                        </td>
                        <td style="color:var(--studio-muted);white-space:nowrap;" title="{{ optional($rec->created_at)->format('d M Y, g:i a') }}">{{ optional($rec->created_at)->diffForHumans() }}</td>
                        <td>
                            <div class="zc-sm-act">
                                @if ($rec->customer_phone)
                                    <a href="tel:{{ $rec->customer_phone }}" class="zc-rc-call zc-rc-call--tel" title="Call"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 2 6a2 2 0 0 1 2-2Z"/></svg></a>
                                    <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="zc-rc-call zc-rc-call--wa" title="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-1-.3-1.6-.6a9 9 0 0 1-3.6-3.2c-.3-.4-.7-1.1-.7-2 0-1 .5-1.4.7-1.6.2-.2.4-.2.6-.2h.4c.2 0 .4 0 .5.4l.7 1.6c.1.2 0 .4 0 .5l-.4.5c-.1.2-.3.3-.1.6.2.3.7 1.1 1.5 1.7.9.8 1.7 1 2 1.1.2.1.4.1.5-.1l.6-.7c.2-.2.3-.2.5-.1l1.5.8c.2.1.4.2.4.3.1.1.1.5 0 .9Z"/></svg></a>
                                @endif
                                <a href="{{ route('recoveries.show', $rec) }}" class="zc-sm-btn zc-sm-btn--view" title="View"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="zc-sm-empty">No incomplete orders in this view yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="zc-sm-pager">
            <span>Showing {{ $recoveries->firstItem() ?? 0 }} to {{ $recoveries->lastItem() ?? 0 }} of {{ $recoveries->total() }}</span>
            @if ($recoveries->hasPages())
                <span style="margin-left:auto;"></span>
                @if (!$recoveries->onFirstPage())<a href="{{ $recoveries->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif
                @if ($recoveries->hasMorePages())<a href="{{ $recoveries->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif
            @endif
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-rc-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-rc-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2400); }
        document.addEventListener('change', function(e){
            var sel=e.target.closest('[data-status]'); if(!sel||!sel.value) return;
            fetch(sel.getAttribute('data-status'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:JSON.stringify({status:sel.value})})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){ showToast(res.ok?(res.d.message||'Updated'):'Failed', !res.ok); })
                .catch(function(){ showToast('Failed',true); });
        });
    })();
</script>
@endpush
@endsection
