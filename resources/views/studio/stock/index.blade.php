@extends('layouts.studio')
@section('title', 'Stock')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-st-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.9rem;margin-bottom:1.2rem;}
    .zc-st-stat{border:1px solid var(--studio-border);border-radius:13px;padding:0.85rem 1rem;background:var(--studio-surface);}
    .zc-st-stat .k{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--studio-muted);}
    .zc-st-stat .v{font-size:1.5rem;font-weight:800;margin-top:2px;color:var(--studio-text);}
    .zc-st-toolbar{display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;margin-bottom:1rem;}
    .zc-st-toolbar .grow{flex:1;min-width:180px;}
    .zc-st-pill{display:inline-flex;align-items:center;gap:5px;padding:0.15rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:800;}
    .zc-st-pill--in{background:rgba(52,199,123,.15);color:#1c8a4e;} .zc-st-pill--low{background:rgba(242,162,12,.16);color:#a5700a;} .zc-st-pill--out{background:rgba(224,90,74,.15);color:#c0392b;}
    .zc-step{display:inline-flex;align-items:center;border:1px solid var(--studio-border);border-radius:10px;overflow:hidden;background:var(--studio-surface);}
    .zc-step button{width:2.1rem;height:2.1rem;border:none;background:transparent;font-size:1.1rem;font-weight:800;color:var(--studio-muted);cursor:pointer;}
    .zc-step button:hover{color:var(--studio-text);background:var(--studio-surface-soft);}
    .zc-step input{width:3.6rem;text-align:center;border:none;border-inline:1px solid var(--studio-border);height:2.1rem;font-weight:800;background:transparent;color:var(--studio-text);font-variant-numeric:tabular-nums;}
    .zc-st-total{font-weight:800;font-size:1.05rem;font-variant-numeric:tabular-nums;color:var(--studio-text);}
    .zc-st-vtoggle{display:inline-flex;align-items:center;gap:5px;border:1px solid var(--studio-border);border-radius:8px;padding:0.25rem 0.6rem;font-size:0.74rem;font-weight:700;color:var(--studio-muted);cursor:pointer;background:var(--studio-surface);}
    .zc-st-vrow{background:var(--studio-surface-soft) !important;}
    .zc-st-vrow td{border-top:none !important;}
    .zc-st-vname{display:flex;align-items:center;gap:0.5rem;padding-left:2.2rem;font-size:0.84rem;color:var(--studio-text);}
    .zc-st-vname::before{content:"↳";color:var(--studio-muted);}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
@php
    $statusOf = fn ($s) => $s <= 0 ? 'out' : ($s <= $lowThreshold ? 'low' : 'in');
@endphp
<div class="space-y-4">
    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h1 class="studio-section-title" style="justify-content:center;margin-bottom:1.1rem;">Stock</h1>
        <p style="text-align:center;font-size:0.82rem;color:var(--studio-muted);margin:-0.4rem 0 1.2rem;">Adjust a product's stock here with <b>−</b> / <b>+</b> — it updates the product page and storefront instantly.</p>

        <div class="zc-st-stats">
            <div class="zc-st-stat"><div class="k">Products</div><div class="v">{{ $products->total() }}</div></div>
            <div class="zc-st-stat"><div class="k">Low stock (≤{{ $lowThreshold }})</div><div class="v" style="color:#a5700a;">{{ $lowStockCount }}</div></div>
            <div class="zc-st-stat"><div class="k">Out of stock</div><div class="v" style="color:#c0392b;">{{ $outStockCount }}</div></div>
        </div>

        <form method="GET" action="{{ route('stock.index') }}" class="zc-st-toolbar">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="studio-form-control grow" placeholder="Search product name or SKU">
            <select name="stock" class="studio-form-control" style="max-width:170px;" onchange="this.form.submit()">
                @foreach (['' => 'All stock', 'in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'] as $v => $l)<option value="{{ $v }}" @selected(($filters['stock'] ?? '') === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
        </form>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Stock</th><th style="text-align:right;">Status</th></tr></thead>
            <tbody>
                @forelse ($products as $product)
                    @php
                        $hasVariants = $product->variants->isNotEmpty();
                        $total = (int) ($product->effective_stock ?? $product->stock);
                        $thumb = $mediaUrl($product->thumbnail);
                    @endphp
                    <tr data-product="{{ $product->id }}">
                        <td>{{ $loop->iteration + ($products->firstItem() ? $products->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-sm-prod">
                                @if ($thumb)<img src="{{ $thumb }}" alt="" class="zc-sm-thumb">
                                @else<span class="zc-sm-thumb"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 8v9a1 1 0 0 1-.6.9l-6.6 3a2 2 0 0 1-1.6 0l-6.6-3A1 1 0 0 1 4 17V8"/><path d="M2.5 7 12 3l9.5 4-9.5 4z"/></svg></span>@endif
                                <div style="min-width:0;">
                                    <div class="zc-sm-name">{{ $product->name }}</div>
                                    <div style="font-size:0.74rem;color:var(--studio-muted);">SKU: {{ $product->sku ?: '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--studio-muted);">{{ $product->category->name ?? '—' }}</td>
                        <td>
                            @if ($hasVariants)
                                <span class="zc-st-total" data-total="{{ $product->id }}">{{ number_format($total) }}</span>
                                <button type="button" class="zc-st-vtoggle" data-vtoggle="{{ $product->id }}" style="margin-left:0.6rem;">▸ {{ $product->variants->count() }} variants</button>
                            @else
                                <div class="zc-step" data-stepper data-product-id="{{ $product->id }}">
                                    <button type="button" data-dec>−</button>
                                    <input type="text" inputmode="numeric" value="{{ $total }}" data-stock>
                                    <button type="button" data-inc>+</button>
                                </div>
                            @endif
                        </td>
                        <td style="text-align:right;"><span class="zc-st-pill zc-st-pill--{{ $statusOf($total) }}" data-pill="{{ $product->id }}">{{ ['in'=>'In stock','low'=>'Low','out'=>'Out'][$statusOf($total)] }}</span></td>
                    </tr>
                    @if ($hasVariants)
                        @foreach ($product->variants as $variant)
                            <tr class="zc-st-vrow" data-vparent="{{ $product->id }}" style="display:none;">
                                <td></td>
                                <td colspan="2"><div class="zc-st-vname">{{ $variant->name ?: 'Variant' }}@if ($variant->sku) <span style="color:var(--studio-muted);font-size:0.74rem;">· {{ $variant->sku }}</span>@endif</div></td>
                                <td>
                                    <div class="zc-step" data-stepper data-product-id="{{ $product->id }}" data-variant-id="{{ $variant->id }}">
                                        <button type="button" data-dec>−</button>
                                        <input type="text" inputmode="numeric" value="{{ (int) $variant->stock }}" data-stock>
                                        <button type="button" data-inc>+</button>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="zc-sm-pager">
            <span>Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products</span>
            @if ($products->hasPages())
                <span style="margin-left:auto;"></span>
                @if (!$products->onFirstPage())<a href="{{ $products->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif
                @if ($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif
            @endif
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-st-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var url="{{ route('stock.update') }}", toast=document.getElementById('zc-st-toast');
        var labels={in:'In stock',low:'Low',out:'Out'};
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2000); }

        // expand/collapse variant rows
        document.addEventListener('click', function(e){
            var t=e.target.closest('[data-vtoggle]'); if(!t) return;
            var pid=t.getAttribute('data-vtoggle');
            var rows=document.querySelectorAll('tr[data-vparent="'+pid+'"]');
            var open=rows.length && rows[0].style.display!=='none';
            rows.forEach(function(r){ r.style.display=open?'none':'table-row'; });
            t.textContent=(open?'▸ ':'▾ ')+rows.length+' variants';
        });

        function applyPill(pid,total){
            var pill=document.querySelector('[data-pill="'+pid+'"]'); if(!pill) return;
            var st=total<=0?'out':(total<={{ $lowThreshold }}?'low':'in');
            pill.className='zc-st-pill zc-st-pill--'+st; pill.textContent=labels[st];
        }

        var timers={};
        function save(stepper){
            var pid=stepper.getAttribute('data-product-id'), vid=stepper.getAttribute('data-variant-id')||null;
            var input=stepper.querySelector('[data-stock]');
            var val=Math.max(0, parseInt(input.value||'0',10)||0); input.value=val;
            var key=pid+':'+(vid||'p');
            clearTimeout(timers[key]);
            timers[key]=setTimeout(function(){
                var body={product_id:parseInt(pid,10),stock:val}; if(vid) body.variant_id=parseInt(vid,10);
                fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)})
                    .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                    .then(function(res){
                        if(!res.ok){ showToast((res.d&&res.d.message)||'Failed',true); return; }
                        var totalEl=document.querySelector('[data-total="'+pid+'"]');
                        if(totalEl) totalEl.textContent=Number(res.d.total).toLocaleString();
                        applyPill(pid, res.d.total);
                        showToast(res.d.message||'Saved');
                    }).catch(function(){ showToast('Failed',true); });
            },500);
        }

        document.addEventListener('click', function(e){
            var inc=e.target.closest('[data-inc]'), dec=e.target.closest('[data-dec]');
            if(!inc&&!dec) return;
            var stepper=(inc||dec).closest('[data-stepper]'); var input=stepper.querySelector('[data-stock]');
            var v=parseInt(input.value||'0',10)||0; v=inc?v+1:Math.max(0,v-1); input.value=v; save(stepper);
        });
        document.addEventListener('change', function(e){
            var input=e.target.closest('[data-stock]'); if(!input) return; save(input.closest('[data-stepper]'));
        });
    })();
</script>
@endpush
@endsection
