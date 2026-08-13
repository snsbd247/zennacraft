@extends('layouts.studio')
@section('title', 'Add Damage')
@section('subtitle', 'Products')
@push('studio-styles')
@include('studio.products.partials._submodule-styles')
<style>
    .zc-dm-items{width:100%;border-collapse:collapse;margin-top:0.5rem;}
    .zc-dm-items th{text-align:left;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--studio-muted);padding:0.45rem 0.5rem;border-bottom:1px solid var(--studio-border);}
    .zc-dm-items td{padding:0.45rem 0.5rem;border-bottom:1px solid var(--studio-border);vertical-align:middle;}
    .zc-dm-sub{font-weight:800;}
    .zc-dm-del{width:2rem;height:2rem;border:none;border-radius:8px;background:#e0483a;color:#fff;cursor:pointer;}
    .zc-dm-total{display:flex;justify-content:flex-end;gap:1rem;align-items:center;margin-top:1rem;font-size:1.1rem;font-weight:800;}
    .zc-dm-total b{color:#c0392b;font-size:1.35rem;}
</style>
@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('products.damages.index') }}" class="studio-command-button">&larr; Back</a>
    <form method="POST" action="{{ route('products.damages.store') }}" id="zc-dm-form">
        @csrf
        <div class="studio-card" style="padding:1.5rem 1.75rem;">
            <h1 class="studio-section-title" style="text-align:center;margin-bottom:1.25rem;">Add Damage Record</h1>
            @if ($errors->any())<div class="studio-callout studio-callout--danger" style="margin-bottom:1rem;">{{ $errors->first() }}</div>@endif
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;">
                <div class="zc-sm-field"><label for="damage_date">Damage Date</label><input type="date" id="damage_date" name="damage_date" class="studio-form-control" value="{{ old('damage_date', now()->toDateString()) }}" required></div>
                <div class="zc-sm-field"><label for="note">Note</label><input id="note" name="note" class="studio-form-control" value="{{ old('note') }}" placeholder="Optional reason / reference"></div>
            </div>

            <table class="zc-dm-items">
                <thead><tr><th style="width:44%;">Product</th><th style="width:14%;">Qty</th><th style="width:18%;">Unit Cost</th><th style="width:18%;">Subtotal</th><th></th></tr></thead>
                <tbody id="zc-dm-rows"></tbody>
            </table>
            <div style="margin-top:0.75rem;"><button type="button" id="zc-dm-add" class="studio-command-button">+ Add Item</button></div>

            <div class="zc-dm-total">Total: <b id="zc-dm-total">0.00</b></div>
            <div style="margin-top:1.25rem;"><button type="submit" class="studio-command-button studio-command-button--primary">Save Damage</button></div>
        </div>
    </form>
</div>

@push('studio-scripts')
<script>
    (function(){
        var products = @json($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'cost' => (float) $p->cost_price]));
        var rows = document.getElementById('zc-dm-rows'), totalEl = document.getElementById('zc-dm-total');
        var opts = '<option value="">-- Select product --</option>' + products.map(function(p){ return '<option value="'+p.id+'" data-cost="'+p.cost+'" data-name="'+p.name.replace(/"/g,'&quot;')+'">'+p.name+'</option>'; }).join('');
        var i = 0;
        function addRow(){
            var idx = i++;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><select name="items['+idx+'][product_id]" class="studio-form-control zc-dm-prod" required>'+opts+'</select><input type="hidden" name="items['+idx+'][product_name]" class="zc-dm-name"></td>'
                + '<td><input type="number" min="1" value="1" name="items['+idx+'][quantity]" class="studio-form-control zc-dm-qty"></td>'
                + '<td><input type="number" min="0" step="0.01" value="0" name="items['+idx+'][unit_cost]" class="studio-form-control zc-dm-cost"></td>'
                + '<td class="zc-dm-sub">0.00</td>'
                + '<td><button type="button" class="zc-dm-del">&times;</button></td>';
            rows.appendChild(tr); recompute();
        }
        function recompute(){
            var total = 0;
            rows.querySelectorAll('tr').forEach(function(tr){
                var q = parseFloat(tr.querySelector('.zc-dm-qty').value)||0, c = parseFloat(tr.querySelector('.zc-dm-cost').value)||0, s = q*c;
                tr.querySelector('.zc-dm-sub').textContent = s.toFixed(2); total += s;
            });
            totalEl.textContent = total.toFixed(2);
        }
        rows.addEventListener('input', recompute);
        rows.addEventListener('change', function(e){
            if(e.target.classList.contains('zc-dm-prod')){
                var opt = e.target.selectedOptions[0], tr = e.target.closest('tr');
                if(opt && opt.dataset.cost){ tr.querySelector('.zc-dm-cost').value = opt.dataset.cost; }
                tr.querySelector('.zc-dm-name').value = opt ? (opt.dataset.name||'') : '';
                recompute();
            }
        });
        rows.addEventListener('click', function(e){
            if(e.target.classList.contains('zc-dm-del')){ if(rows.children.length>1){ e.target.closest('tr').remove(); recompute(); } }
        });
        document.getElementById('zc-dm-add').addEventListener('click', addRow);
        addRow();
    })();
</script>
@endpush
@endsection
