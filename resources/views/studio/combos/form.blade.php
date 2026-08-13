@extends('layouts.studio')
@section('title', $combo->exists ? 'Edit Combo' : 'Add Combo')
@section('subtitle', 'Campaign / Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf-grid{display:grid;grid-template-columns:1.3fr 1fr;gap:1.5rem;align-items:start;}
    @media (max-width:900px){.zc-cf-grid{grid-template-columns:1fr;}}
    .zc-cf-card{border:1px solid var(--studio-border);border-radius:14px;padding:1.25rem;background:var(--studio-surface);}
    .zc-cf-card h3{margin:0 0 1rem;font-size:0.9rem;font-weight:800;color:var(--studio-text);}
    .zc-cf-row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .zc-cf-pick{position:relative;}
    .zc-cf-results{position:absolute;z-index:20;left:0;right:0;top:calc(100% + 4px);background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:12px;box-shadow:0 20px 40px -20px rgba(0,0,0,.35);max-height:280px;overflow:auto;display:none;}
    .zc-cf-results.show{display:block;}
    .zc-cf-res{display:flex;align-items:center;gap:0.7rem;padding:0.55rem 0.8rem;cursor:pointer;border-bottom:1px solid var(--studio-border);}
    .zc-cf-res:hover{background:var(--studio-surface-soft);}
    .zc-cf-res img,.zc-cf-res .ph{width:36px;height:36px;border-radius:8px;object-fit:cover;border:1px solid var(--studio-border);flex:none;background:var(--studio-surface-soft);}
    .zc-cf-res .m{min-width:0;flex:1;}
    .zc-cf-res .m b{display:block;font-size:0.82rem;color:var(--studio-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .zc-cf-res .m span{font-size:0.72rem;color:var(--studio-muted);}
    .zc-cf-items{display:grid;gap:0.6rem;margin-top:0.9rem;}
    .zc-cf-item{display:flex;align-items:center;gap:0.7rem;padding:0.55rem 0.7rem;border:1px solid var(--studio-border);border-radius:11px;background:var(--studio-surface-soft);}
    .zc-cf-item img,.zc-cf-item .ph{width:40px;height:40px;border-radius:8px;object-fit:cover;border:1px solid var(--studio-border);flex:none;background:var(--studio-surface);}
    .zc-cf-item .m{min-width:0;flex:1;}
    .zc-cf-item .m b{display:block;font-size:0.82rem;color:var(--studio-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .zc-cf-item .m span{font-size:0.72rem;color:var(--studio-muted);}
    .zc-cf-qty{display:inline-flex;align-items:center;border:1px solid var(--studio-border);border-radius:9px;overflow:hidden;background:var(--studio-surface);}
    .zc-cf-qty button{width:1.9rem;height:1.9rem;border:none;background:transparent;font-size:1rem;font-weight:800;color:var(--studio-muted);cursor:pointer;}
    .zc-cf-qty button:hover{color:var(--studio-text);}
    .zc-cf-qty input{width:2.4rem;text-align:center;border:none;border-inline:1px solid var(--studio-border);height:1.9rem;font-weight:800;background:transparent;color:var(--studio-text);}
    .zc-cf-rm{border:none;background:none;color:#e0483a;cursor:pointer;font-size:1.1rem;line-height:1;padding:0.2rem;}
    .zc-cf-empty{color:var(--studio-muted);font-size:0.82rem;font-style:italic;padding:0.5rem 0;}
    .zc-cf-calc{margin-top:1rem;border-top:1px dashed var(--studio-border);padding-top:0.9rem;display:grid;gap:0.35rem;font-size:0.85rem;}
    .zc-cf-calc div{display:flex;justify-content:space-between;}
    .zc-cf-calc .save{font-weight:800;color:#1c8a4e;}
    .zc-cf-img{width:120px;height:120px;border-radius:12px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:block;margin-bottom:0.6rem;}
    .zc-cf-toggle{display:inline-flex;align-items:center;gap:0.55rem;font-weight:700;cursor:pointer;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.5rem 1.75rem;">
        <div class="zc-sm-head">
            <a href="{{ route('combos.index') }}" class="studio-command-button">← Back</a>
            <h1 class="studio-section-title" style="flex:1;text-align:center;justify-content:center;">{{ $combo->exists ? 'Edit Combo Product' : 'Add Combo Product' }}</h1>
            <div style="width:90px;"></div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ $combo->exists ? route('combos.update', $combo) : route('combos.store') }}" id="zc-combo-form">
            @csrf
            @if ($combo->exists) @method('PUT') @endif

            <div class="zc-cf-grid">
                <div class="zc-cf-card">
                    <h3>Combo details</h3>
                    <div class="zc-sm-field"><label>Combo name *</label><input type="text" name="name" value="{{ old('name', $combo->name) }}" class="studio-form-control" required placeholder="e.g. Winter Hoodie Combo"></div>
                    <div class="zc-cf-row2">
                        <div class="zc-sm-field"><label>Code / SKU</label><input type="text" name="code" value="{{ old('code', $combo->code) }}" class="studio-form-control" placeholder="e.g. COMBO-1838"></div>
                        <div class="zc-sm-field"><label>Status</label>
                            <select name="status" class="studio-form-control">
                                @foreach (\App\Modules\Combo\Models\Combo::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $combo->status ?? 'active') === $s)>{{ ucfirst($s) }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="zc-cf-row2">
                        <div class="zc-sm-field"><label>Regular price (৳)</label><input type="number" step="0.01" min="0" name="regular_price" value="{{ old('regular_price', $combo->regular_price ? (float) $combo->regular_price : '') }}" class="studio-form-control" placeholder="e.g. 1250"></div>
                        <div class="zc-sm-field"><label>Sale price (৳) *</label><input type="number" step="0.01" min="0" name="price" value="{{ old('price', $combo->price ? (float) $combo->price : '') }}" class="studio-form-control" required placeholder="e.g. 1050"></div>
                    </div>
                    <div class="zc-sm-field"><label>Description</label><textarea name="description" rows="3" class="studio-form-control" placeholder="Optional short description">{{ old('description', $combo->description) }}</textarea></div>
                    <div class="zc-sm-field" style="margin-bottom:0.4rem;">
                        <label class="zc-cf-toggle"><input type="checkbox" name="feature_on_home" value="1" @checked(old('feature_on_home', $combo->feature_on_home)) style="width:1.1rem;height:1.1rem;accent-color:var(--studio-accent);"> Feature this combo on the homepage</label>
                    </div>
                </div>

                <div class="zc-cf-card">
                    <h3>Thumbnail</h3>
                    @if ($mediaUrl($combo->image))<img src="{{ $mediaUrl($combo->image) }}" alt="" class="zc-cf-img" id="zc-cf-imgprev">@else<div class="zc-cf-img" id="zc-cf-imgprev" style="display:grid;place-items:center;color:var(--studio-muted);"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/></svg></div>@endif
                    <input type="file" name="image" accept="image/*" class="studio-form-control" id="zc-cf-imginput">
                    <div style="margin-top:8px;font-size:0.76rem;color:var(--studio-muted);">📐 Recommended: <b style="color:var(--studio-text);">600 × 520 px</b></div>

                    <h3 style="margin-top:1.4rem;">Products in this combo *</h3>
                    <div class="zc-cf-pick">
                        <input type="text" class="studio-form-control" id="zc-cf-search" placeholder="Type product name or SKU…" autocomplete="off">
                        <div class="zc-cf-results" id="zc-cf-results"></div>
                    </div>
                    <div class="zc-cf-items" id="zc-cf-list"></div>
                    <div class="zc-cf-empty" id="zc-cf-empty">No products added yet.</div>

                    <div class="zc-cf-calc">
                        <div><span>Items total</span><span id="zc-cf-itemstotal">৳0</span></div>
                        <div><span>Sale price</span><span id="zc-cf-saleview">৳0</span></div>
                        <div class="save"><span>Customer saves</span><span id="zc-cf-savings">৳0</span></div>
                    </div>
                </div>
            </div>

            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:3rem;">{{ $combo->exists ? 'Update Combo' : 'Save Combo' }}</button></div>
        </form>
    </div>
</div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var searchUrl="{{ route('combos.products.search') }}";
        var search=document.getElementById('zc-cf-search'), results=document.getElementById('zc-cf-results');
        var list=document.getElementById('zc-cf-list'), empty=document.getElementById('zc-cf-empty');
        var priceInput=document.querySelector('[name="price"]');
        var items=@json($items);
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};

        function render(){
            list.innerHTML='';
            empty.style.display=items.length?'none':'block';
            items.forEach(function(it,idx){
                var row=document.createElement('div'); row.className='zc-cf-item';
                var thumb=it.thumb?'<img src="'+esc(it.thumb)+'" alt="">':'<div class="ph"></div>';
                row.innerHTML=thumb+
                    '<div class="m"><b>'+esc(it.name)+'</b><span>SKU: '+esc(it.sku||'—')+' · ৳'+(+it.price).toLocaleString()+'</span></div>'+
                    '<div class="zc-cf-qty"><button type="button" data-dec="'+idx+'">−</button><input type="text" value="'+(it.quantity||1)+'" data-qv="'+idx+'" readonly><button type="button" data-inc="'+idx+'">+</button></div>'+
                    '<button type="button" class="zc-cf-rm" data-rm="'+idx+'" title="Remove">✕</button>'+
                    '<input type="hidden" name="items['+idx+'][product_id]" value="'+it.id+'">'+
                    '<input type="hidden" name="items['+idx+'][quantity]" value="'+(it.quantity||1)+'">';
                list.appendChild(row);
            });
            calc();
        }
        function calc(){
            var it=items.reduce(function(a,x){return a+(+x.price)*(x.quantity||1);},0);
            var sale=parseFloat(priceInput.value||'0')||0;
            document.getElementById('zc-cf-itemstotal').textContent='৳'+it.toLocaleString();
            document.getElementById('zc-cf-saleview').textContent='৳'+sale.toLocaleString();
            document.getElementById('zc-cf-savings').textContent='৳'+Math.max(0,it-sale).toLocaleString();
        }
        function add(p){
            if(items.some(function(x){return x.id===p.id;})) return;
            items.push({id:p.id,name:p.name,sku:p.sku,price:p.price,quantity:1,thumb:p.thumb}); render();
        }

        var t;
        search.addEventListener('input', function(){
            clearTimeout(t); var q=search.value.trim();
            if(q.length<1){results.classList.remove('show');results.innerHTML='';return;}
            t=setTimeout(function(){
                fetch(searchUrl+'?q='+encodeURIComponent(q),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){return r.json();}).then(function(d){
                        results.innerHTML='';
                        (d.results||[]).forEach(function(p){
                            var el=document.createElement('div'); el.className='zc-cf-res';
                            el.innerHTML=(p.thumb?'<img src="'+esc(p.thumb)+'" alt="">':'<div class="ph"></div>')+'<div class="m"><b>'+esc(p.name)+'</b><span>SKU: '+esc(p.sku||'—')+' · ৳'+(+p.price).toLocaleString()+'</span></div>';
                            el.addEventListener('click', function(){ add(p); results.classList.remove('show'); search.value=''; search.focus(); });
                            results.appendChild(el);
                        });
                        results.classList.toggle('show',(d.results||[]).length>0);
                    });
            },220);
        });
        document.addEventListener('click', function(e){
            if(!e.target.closest('.zc-cf-pick')) results.classList.remove('show');
            var inc=e.target.closest('[data-inc]'), dec=e.target.closest('[data-dec]'), rm=e.target.closest('[data-rm]');
            if(inc){ var i=+inc.getAttribute('data-inc'); items[i].quantity=(items[i].quantity||1)+1; render(); }
            if(dec){ var j=+dec.getAttribute('data-dec'); items[j].quantity=Math.max(1,(items[j].quantity||1)-1); render(); }
            if(rm){ items.splice(+rm.getAttribute('data-rm'),1); render(); }
        });
        priceInput.addEventListener('input', calc);
        document.getElementById('zc-combo-form').addEventListener('submit', function(e){
            if(items.length===0){ e.preventDefault(); alert('Add at least one product to the combo.'); }
        });
        var imgInput=document.getElementById('zc-cf-imginput'), imgPrev=document.getElementById('zc-cf-imgprev');
        imgInput.addEventListener('change', function(){ if(imgInput.files&&imgInput.files[0]){ var u=URL.createObjectURL(imgInput.files[0]); imgPrev.outerHTML='<img src="'+u+'" alt="" class="zc-cf-img" id="zc-cf-imgprev">'; } });
        render();
    })();
</script>
@endpush
@endsection
