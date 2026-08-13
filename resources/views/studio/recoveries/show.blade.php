@extends('layouts.studio')
@section('title', 'Incomplete Order')
@section('subtitle', 'Customer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-rd-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:1.3rem;align-items:start;}
    @media (max-width:900px){.zc-rd-grid{grid-template-columns:1fr;}}
    .zc-rd-card{border:1px solid var(--studio-border);border-radius:14px;background:var(--studio-surface);overflow:hidden;}
    .zc-rd-card h3{margin:0;padding:0.85rem 1.1rem;font-size:0.9rem;font-weight:800;color:var(--studio-text);border-bottom:1px solid var(--studio-border);background:var(--studio-surface-soft);}
    .zc-rd-body{padding:1.1rem;}
    .zc-rd-row{display:flex;gap:0.8rem;padding:0.5rem 0;border-bottom:1px dashed var(--studio-border);font-size:0.88rem;}
    .zc-rd-row:last-child{border-bottom:none;}
    .zc-rd-row .k{width:120px;flex:none;color:var(--studio-muted);font-weight:700;}
    .zc-rd-row .v{color:var(--studio-text);font-weight:600;}
    .zc-rd-actions{display:flex;gap:0.5rem;flex-wrap:wrap;padding:1.1rem;border-top:1px solid var(--studio-border);}
    .zc-rd-btn{display:inline-flex;align-items:center;gap:6px;padding:0.5rem 0.9rem;border-radius:9px;font-weight:800;font-size:0.82rem;text-decoration:none;color:#fff;}
    .zc-rd-btn--tel{background:#2aa564;} .zc-rd-btn--wa{background:#25d366;}
    .zc-rd-status{border:1px solid var(--studio-border);border-radius:9px;padding:0.5rem 0.7rem;font-weight:700;background:var(--studio-surface);color:var(--studio-text);}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
@php $waDigits = preg_replace('/\D/', '', (string) $recovery->customer_phone); @endphp
<div class="space-y-4">
    <div class="zc-sm-head" style="margin-bottom:0.3rem;">
        <a href="{{ route('recoveries.index') }}" class="studio-command-button">← Incomplete orders</a>
        <h1 class="studio-section-title" style="flex:1;text-align:center;justify-content:center;">{{ $recovery->customer_name ?: 'Unknown customer' }}</h1>
        <div style="width:120px;"></div>
    </div>

    <div class="zc-rd-grid">
        <div class="zc-rd-card">
            <h3>What the customer entered</h3>
            <div class="zc-rd-body">
                <div class="zc-rd-row"><span class="k">Name</span><span class="v">{{ $recovery->customer_name ?: '—' }}</span></div>
                <div class="zc-rd-row"><span class="k">Phone</span><span class="v">{{ $recovery->customer_phone ?: '—' }}</span></div>
                <div class="zc-rd-row"><span class="k">Email</span><span class="v">{{ $recovery->customer_email ?: '—' }}</span></div>
                <div class="zc-rd-row"><span class="k">Address</span><span class="v">{{ $recovery->address ?: '—' }}</span></div>
                <div class="zc-rd-row"><span class="k">Status</span><span class="v">{{ ucfirst(str_replace('_',' ',$recovery->status)) }}</span></div>
                <div class="zc-rd-row"><span class="k">Started</span><span class="v">{{ optional($recovery->created_at)->format('d M Y, g:i a') }}</span></div>
                <div class="zc-rd-row"><span class="k">Last update</span><span class="v">{{ optional($recovery->updated_at)->diffForHumans() }}</span></div>
            </div>
            <div class="zc-rd-actions">
                @if ($recovery->customer_phone)
                    <a href="tel:{{ $recovery->customer_phone }}" class="zc-rd-btn zc-rd-btn--tel">📞 Call now</a>
                    <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener" class="zc-rd-btn zc-rd-btn--wa">WhatsApp</a>
                @endif
                <select class="zc-rd-status" id="zc-rd-status" data-status="{{ route('recoveries.status', $recovery) }}" style="margin-left:auto;">
                    <option value="">Set status…</option>
                    @foreach ($statuses as $sv => $sl)<option value="{{ $sv }}" @selected($recovery->status === $sv)>{{ $sl }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="zc-rd-card">
            <h3>Product they wanted</h3>
            <div class="zc-rd-body" style="text-align:center;">
                @if ($recovery->product)
                    @php $pimg = $mediaUrl($recovery->product->thumbnail); @endphp
                    @if ($pimg)
                        <img src="{{ $pimg }}" alt="" style="width:100%;max-width:240px;height:210px;object-fit:contain;border:1px solid var(--studio-border);border-radius:12px;background:var(--studio-surface-soft);padding:8px;margin:0 auto 1rem;display:block;">
                    @else
                        <div style="height:170px;display:grid;place-items:center;color:var(--studio-muted);border:1px dashed var(--studio-border);border-radius:12px;margin-bottom:1rem;">No image</div>
                    @endif
                    <div style="font-weight:800;font-size:1.05rem;color:var(--studio-text);">{{ $recovery->product->name }}</div>
                    <div style="margin-top:8px;"><span style="display:inline-flex;align-items:center;padding:0.25rem 0.8rem;border-radius:999px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);font-weight:800;font-size:0.82rem;color:var(--studio-text);">SKU: {{ $recovery->product->sku ?: '—' }}</span></div>
                    @if ($recovery->variant)<div style="margin-top:8px;color:var(--studio-muted);font-size:0.85rem;">Variant: <b style="color:var(--studio-text);">{{ $recovery->variant->name }}</b></div>@endif
                    <div style="margin-top:6px;color:var(--studio-muted);font-size:0.85rem;">Quantity: <b style="color:var(--studio-text);">{{ $recovery->quantity }}</b></div>
                @else
                    <div style="padding:1.5rem;color:var(--studio-muted);font-style:italic;">This was a multi-item cart checkout — no single product to show.</div>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-rc-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-rc-toast'), sel=document.getElementById('zc-rd-status');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2400); }
        sel && sel.addEventListener('change', function(){
            if(!sel.value) return;
            fetch(sel.getAttribute('data-status'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:JSON.stringify({status:sel.value})})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){ showToast(res.ok?(res.d.message||'Updated'):'Failed', !res.ok); })
                .catch(function(){ showToast('Failed',true); });
        });
    })();
</script>
@endpush
@endsection
