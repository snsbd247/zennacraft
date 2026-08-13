@extends('layouts.studio')
@section('title', $purchase->exists ? 'Edit Purchase' : 'Add Purchase')
@section('subtitle', 'Purchase')
@push('studio-styles')
@include('studio.products.partials._submodule-styles')
<style>
    .zc-pu-grid{display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:1rem;align-items:end;}
    .zc-pu-entry{display:grid;grid-template-columns:1.6fr 1fr 1fr auto;gap:0.85rem;align-items:end;margin-top:1.4rem;padding-top:1.4rem;border-top:1px dashed var(--studio-border);}
    @media(max-width:820px){.zc-pu-grid,.zc-pu-entry{grid-template-columns:1fr 1fr;}}
    .zc-pu-grid .studio-command-button,.zc-pu-entry .studio-command-button{height:46px;padding-inline:1.3rem;white-space:nowrap;}
    .zc-pu-sup{display:flex;gap:0.5rem;align-items:end;min-width:0;}
    .zc-pu-sup select{flex:1;min-width:0;}
    .zc-pu-toast{position:fixed;right:20px;bottom:20px;z-index:120;background:#1c8a4e;color:#fff;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-pu-toast.show{opacity:1;transform:translateY(0);}
    .zc-pu-items{width:100%;border-collapse:collapse;margin-top:1rem;}
    .zc-pu-items th{text-align:left;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--studio-muted);padding:0.5rem;border-bottom:1px solid var(--studio-border);}
    .zc-pu-items td{padding:0.5rem;border-bottom:1px solid var(--studio-border);vertical-align:middle;}
    .zc-pu-items .rm{width:2rem;height:2rem;border:none;border-radius:8px;background:#e0483a;color:#fff;cursor:pointer;}
    .zc-pu-foot{display:grid;grid-template-columns:2fr 1fr auto;gap:1.25rem;margin-top:1.4rem;align-items:end;}
    .zc-pu-total{min-width:150px;padding:0.7rem 1.1rem;border:1px solid var(--studio-border);border-radius:12px;background:var(--studio-surface-soft);text-align:right;font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:var(--studio-muted);}
    .zc-pu-total b{display:block;color:var(--studio-accent);font-size:1.5rem;letter-spacing:0;margin-top:2px;}
    @media(max-width:820px){.zc-pu-foot{grid-template-columns:1fr;}}
    .zc-modal{position:fixed;inset:0;z-index:80;display:none;place-items:center;padding:1rem;}
    .zc-modal.open{display:grid;}
    .zc-modal__scrim{position:absolute;inset:0;background:rgba(16,24,40,.5);}
    .zc-modal__box{position:relative;z-index:2;width:min(440px,94vw);background:var(--studio-surface);border-radius:16px;box-shadow:0 30px 70px -30px rgba(16,24,40,.5);padding:1.5rem;}
</style>
@endpush
@section('content')
@php
    $existingItems = $purchase->exists ? $purchase->items->map(fn ($i) => ['code' => $i->product_code, 'price' => (float) $i->purchase_price, 'qty' => (int) $i->quantity])->values() : collect();
@endphp
<div class="space-y-4">
    <a href="{{ route('purchases.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}" id="zc-pu-form">
        @csrf @if($purchase->exists) @method('PUT') @endif
        <div class="studio-card" style="padding:1.5rem 1.75rem;">
            <h1 class="studio-section-title" style="text-align:center;margin-bottom:1.25rem;">{{ $purchase->exists ? 'Edit Purchase' : 'Add Purchase' }}</h1>

            <div class="zc-pu-grid">
                <div class="zc-sm-field" style="margin:0;"><label>Purchase Date</label><input type="date" name="purchase_date" class="studio-form-control" value="{{ old('purchase_date', optional($purchase->purchase_date)->toDateString() ?: now()->toDateString()) }}" required></div>
                <div class="zc-sm-field" style="margin:0;"><label>Supplier Invoice No</label><input name="invoice_no" class="studio-form-control" value="{{ old('invoice_no', $purchase->invoice_no) }}" placeholder="invoice"></div>
                <div class="zc-sm-field" style="margin:0;"><label>Supplier</label>
                    <div class="zc-pu-sup">
                        <select name="supplier_id" class="studio-form-control">
                            <option value="">Select Supplier</option>
                            @foreach ($suppliers as $sup)<option value="{{ $sup->id }}" @selected((int) old('supplier_id', $selectedSupplier) === $sup->id)>{{ $sup->name }}</option>@endforeach
                        </select>
                        <button type="button" class="studio-command-button studio-command-button--primary" id="zc-sup-open" style="white-space:nowrap;">+ Add Supplier</button>
                    </div>
                </div>
            </div>

            <div class="zc-pu-entry">
                <div class="zc-sm-field" style="margin:0;"><label>Product Code</label><input id="zc-i-code" class="studio-form-control" placeholder="type product code (SKU)"></div>
                <div class="zc-sm-field" style="margin:0;"><label>Purchase price</label><input id="zc-i-price" type="number" min="0" step="0.01" class="studio-form-control" placeholder="price"></div>
                <div class="zc-sm-field" style="margin:0;"><label>Quantity</label><input id="zc-i-qty" type="number" min="1" class="studio-form-control" placeholder="quantity"></div>
                <button type="button" class="studio-command-button studio-command-button--primary" id="zc-i-add">+ Add Item</button>
            </div>

            <table class="zc-pu-items">
                <thead><tr><th style="width:36%;">Product Code</th><th>Purchase Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead>
                <tbody id="zc-pu-rows"><tr id="zc-pu-empty"><td colspan="5" style="text-align:center;color:var(--studio-muted);padding:1.5rem;">No items added yet — enter a product code, price and quantity above.</td></tr></tbody>
            </table>

            <div class="zc-pu-foot">
                <div class="zc-sm-field" style="margin:0;"><label>Comment</label><input name="comment" class="studio-form-control" value="{{ old('comment', $purchase->comment) }}" placeholder="optional note"></div>
                <div class="zc-sm-field" style="margin:0;"><label>Paid Amount</label><input name="paid_amount" type="number" min="0" step="0.01" class="studio-form-control" value="{{ old('paid_amount', $purchase->exists ? $purchase->paid_amount : 0) }}"></div>
                <div class="zc-pu-total">Total<br><b id="zc-pu-total">0.00</b></div>
            </div>

            <div style="margin-top:1.25rem;"><button type="submit" class="studio-command-button studio-command-button--primary" id="zc-pu-save">Save Purchase</button></div>
        </div>
    </form>
</div>

{{-- Add Supplier modal (separate form; submitting reloads with the new supplier selected) --}}
<div class="zc-modal" id="zc-sup-modal">
    <div class="zc-modal__scrim" data-sup-close></div>
    <div class="zc-modal__box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;"><h2 class="studio-section-title" style="font-size:1.1rem;">Add Supplier</h2><button type="button" data-sup-close style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--studio-muted);">&times;</button></div>
        <form method="POST" action="{{ route('suppliers.store') }}" id="zc-sup-form">
            @csrf
            <div class="zc-sm-field"><label>Name</label><input name="name" class="studio-form-control" placeholder="Supplier name" required></div>
            <div class="zc-sm-field"><label>Phone</label><input name="phone" class="studio-form-control" placeholder="Optional"></div>
            <div class="zc-sm-field"><label>Email</label><input name="email" type="email" class="studio-form-control" placeholder="Optional"></div>
            <div class="zc-sm-field"><label>Address</label><input name="address" class="studio-form-control" placeholder="Optional"></div>
            <button type="submit" class="studio-command-button studio-command-button--primary">Save Supplier</button>
        </form>
    </div>
</div>

<div class="zc-pu-toast" id="zc-pu-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var rows=document.getElementById('zc-pu-rows'), empty=document.getElementById('zc-pu-empty'), totalEl=document.getElementById('zc-pu-total');
        var code=document.getElementById('zc-i-code'), price=document.getElementById('zc-i-price'), qty=document.getElementById('zc-i-qty');
        var i=0;
        function esc(s){return (s||'').replace(/"/g,'&quot;');}
        function addItem(c, p, q){
            p=parseFloat(p)||0; q=parseInt(q)||0;
            if(q<1){ alert('Enter a quantity of at least 1.'); return; }
            if(empty) empty.remove();
            var idx=i++, tr=document.createElement('tr');
            tr.innerHTML='<td>'+(c?('<b>'+esc(c)+'</b>'):'<span style="color:var(--studio-muted)">— (free item)</span>')+'<input type="hidden" name="items['+idx+'][product_code]" value="'+esc(c)+'"></td>'
                +'<td><input type="hidden" name="items['+idx+'][purchase_price]" value="'+p+'">'+p.toFixed(2)+'</td>'
                +'<td><input type="hidden" name="items['+idx+'][quantity]" value="'+q+'">'+q+'</td>'
                +'<td class="sub" style="font-weight:800;">'+(p*q).toFixed(2)+'</td>'
                +'<td><button type="button" class="rm">&times;</button></td>';
            rows.appendChild(tr); recompute();
        }
        function recompute(){
            var t=0; rows.querySelectorAll('tr').forEach(function(tr){ var s=tr.querySelector('.sub'); if(s) t+=parseFloat(s.textContent)||0; });
            totalEl.textContent=t.toFixed(2);
        }
        document.getElementById('zc-i-add').addEventListener('click', function(){
            addItem(code.value.trim(), price.value, qty.value);
            code.value=''; price.value=''; qty.value=''; code.focus();
        });
        qty.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); document.getElementById('zc-i-add').click(); } });
        rows.addEventListener('click', function(e){ if(e.target.classList.contains('rm')){ e.target.closest('tr').remove(); if(!rows.children.length){ rows.innerHTML='<tr id="zc-pu-empty"><td colspan="5" style="text-align:center;color:var(--studio-muted);padding:1.5rem;">No items added yet.</td></tr>'; empty=document.getElementById('zc-pu-empty'); } recompute(); } });
        document.getElementById('zc-pu-form').addEventListener('submit', function(e){ if(!rows.querySelector('input[name^="items"]')){ e.preventDefault(); alert('Add at least one item before saving.'); } });

        // preload existing items (edit)
        var existing=@json($existingItems);
        existing.forEach(function(it){ addItem(it.code||'', it.price, it.qty); });

        // supplier modal + AJAX add (no page reload — keeps the items you've entered)
        var modal=document.getElementById('zc-sup-modal');
        document.getElementById('zc-sup-open').addEventListener('click', function(){ modal.classList.add('open'); });
        document.querySelectorAll('[data-sup-close]').forEach(function(b){ b.addEventListener('click', function(){ modal.classList.remove('open'); }); });

        var toast=document.getElementById('zc-pu-toast');
        function showToast(msg){ if(!toast)return; toast.textContent=msg; toast.classList.add('show'); setTimeout(function(){ toast.classList.remove('show'); }, 2600); }

        var supForm=document.getElementById('zc-sup-form');
        supForm.addEventListener('submit', function(e){
            e.preventDefault();
            var btn=supForm.querySelector('button[type="submit"]'); btn.disabled=true;
            fetch(supForm.action, { method:'POST', body:new FormData(supForm), headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
                .then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
                .then(function(res){
                    btn.disabled=false;
                    if(res.ok && res.d.supplier){
                        var sel=document.querySelector('select[name="supplier_id"]');
                        var opt=new Option(res.d.supplier.name, res.d.supplier.id, true, true);
                        sel.add(opt);
                        modal.classList.remove('open'); supForm.reset();
                        showToast(res.d.message || 'Supplier added.');
                    } else {
                        alert((res.d && res.d.message) || 'Could not add supplier.');
                    }
                })
                .catch(function(){ btn.disabled=false; alert('Network error — could not add supplier.'); });
        });
    })();
</script>
@endpush
@endsection
