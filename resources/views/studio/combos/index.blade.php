@extends('layouts.studio')
@section('title', 'Combo Products')
@section('subtitle', 'Campaign / Offer')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cb-toolbar{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}
    .zc-cb-toolbar .zc-cb-left{display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;}
    .zc-cb-search{display:flex;gap:0.5rem;margin-left:auto;}
    .zc-cb-search input{min-width:230px;}
    .zc-cb-refresh{display:inline-grid;place-items:center;width:2.4rem;height:2.4rem;border-radius:10px;border:1px solid var(--studio-border);background:var(--studio-surface);color:var(--studio-muted);cursor:pointer;}
    .zc-cb-refresh:hover{color:var(--studio-text);}
    .zc-cb-refresh svg{width:1.05rem;height:1.05rem;}
    .zc-cb-show{width:auto;min-width:5rem;}
    .zc-cb-items{display:flex;flex-wrap:wrap;gap:0.3rem;max-width:280px;}
    .zc-cb-chip{display:inline-flex;align-items:center;gap:5px;padding:0.15rem 0.55rem;border-radius:999px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);font-size:0.72rem;font-weight:700;color:var(--studio-text);}
    .zc-cb-chip b{color:var(--studio-muted);font-weight:800;}
    .zc-cb-none{color:var(--studio-muted);font-style:italic;font-size:0.8rem;}
    .zc-cb-price{line-height:1.5;font-size:0.8rem;}
    .zc-cb-price .p{color:var(--studio-muted);text-decoration:line-through;}
    .zc-cb-price .s{font-weight:800;color:var(--studio-text);}
    .zc-cb-price .d{font-weight:800;color:#1c8a4e;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <form method="GET" action="{{ route('combos.index') }}" class="zc-cb-toolbar" style="margin-bottom:1.25rem;">
            <div class="zc-cb-left">
                <a href="{{ route('combos.create') }}" class="studio-command-button studio-command-button--primary">+ Add</a>
                <select name="per_page" class="studio-form-control zc-cb-show" onchange="this.form.submit()">
                    @foreach ([15, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
                </select>
                <a href="{{ route('combos.index') }}" class="zc-cb-refresh" title="Refresh"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg></a>
            </div>
            <h1 class="studio-section-title" style="justify-content:center;flex:1;text-align:center;">Combo Products</h1>
            <div class="zc-cb-search">
                <input type="search" name="q" value="{{ $term }}" class="studio-form-control" placeholder="search by product name || code">
                <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
            </div>
        </form>

        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Product Name</th><th>Items</th><th>Stock</th><th>Price</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($combos as $combo)
                    <tr>
                        <td>{{ $loop->iteration + ($combos->firstItem() ? $combos->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-sm-prod">
                                @php $img = $mediaUrl($combo->image); @endphp
                                @if ($img)<img src="{{ $img }}" alt="" class="zc-sm-thumb">
                                @else<span class="zc-sm-thumb"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 8v9a1 1 0 0 1-.6.9l-6.6 3a2 2 0 0 1-1.6 0l-6.6-3A1 1 0 0 1 4 17V8"/><path d="M2.5 7 12 3l9.5 4-9.5 4z"/></svg></span>@endif
                                <div style="min-width:0;">
                                    <div class="zc-sm-name">{{ $combo->name }}</div>
                                    <div style="font-size:0.74rem;color:var(--studio-muted);">{{ $combo->code ? 'Code : '.$combo->code : 'Slug : '.$combo->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($combo->products->isEmpty())
                                <span class="zc-cb-none">No items</span>
                            @else
                                <div class="zc-cb-items">
                                    @foreach ($combo->products as $p)<span class="zc-cb-chip">{{ \Illuminate\Support\Str::limit($p->name, 18) }} <b>×{{ (int) $p->pivot->quantity }}</b></span>@endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="zc-sm-pill {{ $combo->status === 'active' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ ucfirst($combo->status) }}</span>
                        </td>
                        <td>
                            <div class="zc-cb-price">
                                <div class="p">Price : ৳{{ number_format($combo->regularPriceValue()) }}</div>
                                <div class="s">Sale Price : ৳{{ number_format((float) $combo->price) }}</div>
                                <div class="d">Discount : ৳{{ number_format($combo->discountAmount()) }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('combos.edit', $combo) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Remove" data-delete="{{ route('combos.destroy', $combo) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="zc-sm-empty">No combo products yet. Click <b>+ Add</b> to bundle products together.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="zc-sm-pager">
            <span>Showing {{ $combos->firstItem() ?? 0 }} to {{ $combos->lastItem() ?? 0 }} of total {{ $combos->total() }} entries</span>
            @if ($combos->hasPages())
                <span style="margin-left:auto;"></span>
                @if (!$combos->onFirstPage())<a href="{{ $combos->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif
                @if ($combos->hasMorePages())<a href="{{ $combos->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif
            @endif
        </div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-cb-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-cb-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2400); }
        document.addEventListener('click', function(e){
            var del=e.target.closest('[data-delete]');
            if(del){
                if(!confirm('Remove this combo product?')) return;
                fetch(del.getAttribute('data-delete'),{method:'DELETE',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}})
                    .then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});})
                    .then(function(res){ if(res.ok){ var tr=del.closest('tr'); tr.style.transition='opacity .2s'; tr.style.opacity='0'; setTimeout(function(){tr.remove();},200); showToast(res.d.message||'Removed'); } else showToast(res.d.message||'Failed',true); });
            }
        });
    })();
</script>
@endpush
@endsection
