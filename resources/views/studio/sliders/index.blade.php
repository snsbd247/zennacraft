@extends('layouts.studio')
@php $seg = str_replace('home_', '', $placement); @endphp
@section('title', $meta['label'])
@section('subtitle', 'Slider')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-sl-thumb{width:120px;height:60px;border-radius:9px;object-fit:cover;border:1px solid var(--studio-border);background:var(--studio-surface-soft);display:grid;place-items:center;color:var(--studio-muted);font-size:0.6rem;font-weight:800;overflow:hidden;}
    .zc-sl-note{display:flex;align-items:center;gap:8px;padding:0.6rem 0.9rem;border-radius:10px;background:rgba(242,162,12,0.1);border:1px solid rgba(242,162,12,0.3);color:#8a5a00;font-size:0.8rem;font-weight:600;margin-bottom:0.9rem;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route("sliders.$seg.create") }}" class="studio-command-button studio-command-button--primary">+ Add {{ $meta['label'] }}</a>
        <div style="flex:1;text-align:center;">
            <h1 class="studio-section-title" style="justify-content:center;">{{ $meta['label'] }}</h1>
            <div style="font-size:0.8rem;color:var(--studio-muted);margin-top:2px;">{{ $meta['desc'] }}</div>
        </div>
        <div style="width:150px;"></div>
    </div>

    <div class="studio-card" style="padding:1rem 1.25rem;">
        @if (! $meta['multiple'])
            <div class="zc-sl-note"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg> This spot shows a single banner — the top <b>Active</b> one (by position) is used. Add more only to swap easily.</div>
        @endif
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Image</th><th>Title</th><th>Position</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($sliders as $slider)
                    <tr>
                        <td>{{ $loop->iteration + ($sliders->firstItem() ? $sliders->firstItem() - 1 : 0) }}</td>
                        <td>
                            @php $img = $mediaUrl($slider->image); @endphp
                            <span class="zc-sl-thumb">@if ($img)<img src="{{ $img }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else NO IMAGE @endif</span>
                        </td>
                        <td class="zc-sm-name">{{ $slider->title }}</td>
                        <td style="font-weight:700;">{{ $slider->sort_order }}</td>
                        <td><span class="zc-sm-pill zc-sl-status {{ $slider->active ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $slider->active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('sliders.edit', $slider) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <button type="button" class="zc-sm-btn zc-sm-btn--tog" title="Toggle status" data-toggle="{{ route('sliders.toggle', $slider) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                                <button type="button" class="zc-sm-btn zc-sm-btn--del" title="Delete" data-delete="{{ route('sliders.destroy', $slider) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="zc-sm-empty">No banner here yet. Click <b>+ Add {{ $meta['label'] }}</b> to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($sliders->hasPages())<div class="zc-sm-pager">@if(!$sliders->onFirstPage())<a href="{{ $sliders->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $sliders->currentPage() }} / {{ $sliders->lastPage() }}</span>@if($sliders->hasMorePages())<a href="{{ $sliders->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
        <div style="margin-top:1rem;color:var(--studio-muted);font-size:0.8rem;">Showing {{ $sliders->firstItem() ?? 0 }} to {{ $sliders->lastItem() ?? 0 }} of total {{ $sliders->total() }} entries</div>
    </div>
</div>
<div class="zc-cat-toast" id="zc-sl-toast" role="status" aria-live="polite"></div>

@push('studio-scripts')
<script>
    (function(){
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        var toast = document.getElementById('zc-sl-toast');
        function showToast(msg, err){ toast.textContent = msg; toast.classList.toggle('err', !!err); toast.classList.add('show'); setTimeout(function(){ toast.classList.remove('show'); }, 2600); }
        function ajax(url, method){ return fetch(url, { method: method, headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } }).then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); }); }
        document.addEventListener('click', function(e){
            var tog = e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var pill=tog.closest('tr').querySelector('.zc-sl-status'); pill.textContent=res.d.active?'Active':'Inactive'; pill.className='zc-sm-pill zc-sl-status '+(res.d.active?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed', true); }); return; }
            var del = e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this banner?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed', true); }); return; }
        });
    })();
</script>
@endpush
@endsection
