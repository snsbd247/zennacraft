@extends('layouts.studio')
@section('title', 'Free Delivery Products')
@section('subtitle', 'Campaign / Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-fd-toolbar{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.25rem;}
    .zc-fd-left{display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;}
    .zc-fd-search{display:flex;gap:0.5rem;margin-left:auto;}
    .zc-fd-search input{min-width:230px;}
    .zc-fd-show{width:auto;min-width:5rem;}
    .zc-fd-refresh{display:inline-grid;place-items:center;width:2.4rem;height:2.4rem;border-radius:10px;border:1px solid var(--studio-border);background:var(--studio-surface);color:var(--studio-muted);cursor:pointer;}
    .zc-fd-refresh:hover{color:var(--studio-text);}
    .zc-fd-refresh svg{width:1.05rem;height:1.05rem;}
    .zc-fd-code{display:inline-flex;align-items:center;padding:0.2rem 0.7rem;border-radius:999px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);font-weight:800;font-size:0.78rem;color:var(--studio-text);}
    /* modal */
    .zc-fd-modal{position:fixed;inset:0;z-index:200;background:rgba(15,23,42,.5);display:none;align-items:flex-start;justify-content:center;padding:6vh 1rem;}
    .zc-fd-modal.show{display:flex;}
    .zc-fd-box{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:16px;width:min(560px,100%);box-shadow:0 30px 60px -25px rgba(0,0,0,.5);overflow:hidden;}
    .zc-fd-box header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--studio-border);}
    .zc-fd-box header b{font-size:0.95rem;color:var(--studio-text);}
    .zc-fd-box header button{border:none;background:none;font-size:1.3rem;line-height:1;color:var(--studio-muted);cursor:pointer;}
    .zc-fd-box .body{padding:1.1rem 1.2rem;}
    .zc-fd-res{margin-top:0.8rem;max-height:320px;overflow:auto;display:grid;gap:0.4rem;}
    .zc-fd-r{display:flex;align-items:center;gap:0.7rem;padding:0.5rem 0.7rem;border:1px solid var(--studio-border);border-radius:11px;cursor:pointer;}
    .zc-fd-r:hover{background:var(--studio-surface-soft);}
    .zc-fd-r img,.zc-fd-r .ph{width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid var(--studio-border);flex:none;background:var(--studio-surface-soft);}
    .zc-fd-r .m{min-width:0;flex:1;}
    .zc-fd-r .m b{display:block;font-size:0.83rem;color:var(--studio-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .zc-fd-r .m span{font-size:0.72rem;color:var(--studio-muted);}
    .zc-fd-r .tag{font-size:0.68rem;font-weight:800;color:#1c8a4e;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:300;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <form method="GET" action="{{ route('free-delivery.index') }}" class="zc-fd-toolbar">
            <div class="zc-fd-left">
                <button type="button" class="studio-command-button studio-command-button--primary" id="zc-fd-add">+ Add</button>
                <select name="per_page" class="studio-form-control zc-fd-show" onchange="this.form.submit()">
                    @foreach ([15, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
                </select>
                <a href="{{ route('free-delivery.index') }}" class="zc-fd-refresh" title="Refresh"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg></a>
            </div>
            <h1 class="studio-section-title" style="justify-content:center;flex:1;text-align:center;">Free Delivery Products</h1>
            <div class="zc-fd-search">
                <input type="search" name="q" value="{{ $term }}" class="studio-form-control" placeholder="search by product name || code">
                <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
            </div>
        </form>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Image</th><th>Product</th><th>Code</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody id="zc-fd-body">
                @forelse ($products as $product)
                    <tr data-row="{{ $product->id }}">
                        <td>{{ $loop->iteration + ($products->firstItem() ? $products->firstItem() - 1 : 0) }}</td>
                        <td>
                            @php $img = $mediaUrl($product->thumbnail); @endphp
                            @if ($img)<img src="{{ $img }}" alt="" class="zc-sm-thumb">
                            @else<span class="zc-sm-thumb"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/></svg></span>@endif
                        </td>
                        <td class="zc-sm-name">{{ $product->name }}</td>
                        <td><span class="zc-fd-code">{{ $product->sku ?: '—' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <button type="button" class="studio-command-button" style="color:#c0392b;border-color:rgba(224,90,74,.4);" data-remove="{{ route('free-delivery.destroy', $product) }}">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr id="zc-fd-empty"><td colspan="5" class="zc-sm-empty">No free-delivery products yet. Click <b>+ Add</b> to choose products that ship free.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="zc-sm-pager">
            <span>Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of total {{ $products->total() }} entries</span>
            @if ($products->hasPages())
                <span style="margin-left:auto;"></span>
                @if (!$products->onFirstPage())<a href="{{ $products->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif
                @if ($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif
            @endif
        </div>
    </div>
</div>

<div class="zc-fd-modal" id="zc-fd-modal">
    <div class="zc-fd-box">
        <header><b>Add a free-delivery product</b><button type="button" id="zc-fd-close">✕</button></header>
        <div class="body">
            <input type="text" class="studio-form-control" id="zc-fd-q" placeholder="Type product name or SKU…" autocomplete="off">
            <div class="zc-fd-res" id="zc-fd-results"></div>
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-fd-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var searchUrl="{{ route('free-delivery.products.search') }}", storeUrl="{{ route('free-delivery.store') }}";
        var modal=document.getElementById('zc-fd-modal'), q=document.getElementById('zc-fd-q'), results=document.getElementById('zc-fd-results');
        var body=document.getElementById('zc-fd-body'), toast=document.getElementById('zc-fd-toast');
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2400); }
        function open(){ modal.classList.add('show'); q.value=''; results.innerHTML=''; q.focus(); }
        function close(){ modal.classList.remove('show'); }
        document.getElementById('zc-fd-add').addEventListener('click', open);
        document.getElementById('zc-fd-close').addEventListener('click', close);
        modal.addEventListener('click', function(e){ if(e.target===modal) close(); });

        var t;
        q.addEventListener('input', function(){
            clearTimeout(t); var term=q.value.trim();
            if(term.length<1){results.innerHTML='';return;}
            t=setTimeout(function(){
                fetch(searchUrl+'?q='+encodeURIComponent(term),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
                    .then(function(r){return r.json();}).then(function(d){
                        results.innerHTML='';
                        (d.results||[]).forEach(function(p){
                            var el=document.createElement('div'); el.className='zc-fd-r';
                            el.innerHTML=(p.thumb?'<img src="'+esc(p.thumb)+'" alt="">':'<div class="ph"></div>')+
                                '<div class="m"><b>'+esc(p.name)+'</b><span>SKU: '+esc(p.sku||'—')+'</span></div>'+(p.already?'<span class="tag">✓ already free</span>':'');
                            if(!p.already) el.addEventListener('click', function(){ addProduct(p.id); });
                            results.appendChild(el);
                        });
                        if(!(d.results||[]).length) results.innerHTML='<div class="zc-sm-empty" style="padding:1rem;">No products found.</div>';
                    });
            },220);
        });
        function addProduct(id){
            fetch(storeUrl,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/json'},body:JSON.stringify({product_id:id})})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){
                    if(!res.ok){ showToast((res.d&&res.d.message)||'Failed',true); return; }
                    var p=res.d.product, ex=document.getElementById('zc-fd-empty'); if(ex) ex.remove();
                    if(document.querySelector('[data-row="'+p.id+'"]')){ showToast('Already added'); close(); return; }
                    var tr=document.createElement('tr'); tr.setAttribute('data-row',p.id);
                    var n=body.querySelectorAll('tr').length+1;
                    tr.innerHTML='<td>'+n+'</td><td>'+(p.thumb?'<img src="'+esc(p.thumb)+'" alt="" class="zc-sm-thumb">':'<span class="zc-sm-thumb"></span>')+'</td>'+
                        '<td class="zc-sm-name">'+esc(p.name)+'</td><td><span class="zc-fd-code">'+esc(p.sku||'—')+'</span></td>'+
                        '<td><div class="zc-sm-act"><button type="button" class="studio-command-button" style="color:#c0392b;border-color:rgba(224,90,74,.4);" data-remove="'+p.remove_url+'">Remove</button></div></td>';
                    body.appendChild(tr); showToast(res.d.message||'Added'); close();
                });
        }
        document.addEventListener('click', function(e){
            var rm=e.target.closest('[data-remove]'); if(!rm) return;
            if(!confirm('Remove this product from Free Delivery?')) return;
            fetch(rm.getAttribute('data-remove'),{method:'DELETE',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}})
                .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                .then(function(res){ if(res.ok){ var tr=rm.closest('tr'); tr.style.transition='opacity .2s'; tr.style.opacity='0'; setTimeout(function(){tr.remove();},200); showToast(res.d.message||'Removed'); } else showToast('Failed',true); });
        });
    })();
</script>
@endpush
@endsection
