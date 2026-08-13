@extends('layouts.studio')
@section('title', 'Pages')
@section('subtitle', 'Website Setup')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-lp-link{display:inline-flex;align-items:center;gap:6px;color:var(--studio-accent);font-weight:600;font-size:0.85rem;text-decoration:none;}
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('pages.create') }}" class="studio-command-button studio-command-button--primary">+ Add Page</a></div>
    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Pages</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Title</th><th>Link</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr>
                        <td>{{ $loop->iteration + ($pages->firstItem() ? $pages->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $page->title }}</td>
                        <td><a href="{{ url('/pages/'.$page->slug) }}" target="_blank" class="zc-lp-link">/pages/{{ $page->slug }}</a></td>
                        <td><span class="zc-sm-pill zc-st {{ $page->active ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $page->active ? 'Active' : 'Inactive' }}</span></td>
                        <td><div class="zc-sm-act">
                            <a href="{{ route('pages.edit', $page) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                            <button type="button" class="zc-sm-btn zc-sm-btn--tog" data-toggle="{{ route('pages.toggle', $page) }}" title="Toggle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                            <button type="button" class="zc-sm-btn zc-sm-btn--del" data-delete="{{ route('pages.destroy', $page) }}" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No pages yet. Click <b>+ Add Page</b> (e.g. About us, Privacy Policy, Terms).</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($pages->hasPages())<div class="zc-sm-pager">@if(!$pages->onFirstPage())<a href="{{ $pages->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>{{ $pages->currentPage() }} / {{ $pages->lastPage() }}</span>@if($pages->hasMorePages())<a href="{{ $pages->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
<div class="zc-cat-toast" id="zc-toast" role="status" aria-live="polite"></div>
@include('studio.cities._js')
@endsection
