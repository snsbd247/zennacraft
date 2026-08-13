@extends('layouts.studio')
@section('title', 'Cities')
@section('subtitle', 'Setting & Configuration')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cat-toast{position:fixed;right:20px;bottom:20px;z-index:120;padding:0.75rem 1.1rem;border-radius:10px;font-weight:700;font-size:0.85rem;color:#fff;background:#1c8a4e;box-shadow:0 16px 34px -16px rgba(0,0,0,.4);opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;}
    .zc-cat-toast.show{opacity:1;transform:translateY(0);} .zc-cat-toast.err{background:#c0392b;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('cities.create') }}" class="studio-command-button studio-command-button--primary">+ Add City</a></div>
    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Cities</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Name</th><th>Sub-cities</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($cities as $city)
                    <tr>
                        <td>{{ $loop->iteration + ($cities->firstItem() ? $cities->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $city->name }}</td>
                        <td>{{ $city->sub_cities_count }}</td>
                        <td><span class="zc-sm-pill zc-st {{ $city->isActive() ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ ucfirst($city->status) }}</span></td>
                        <td><div class="zc-sm-act">
                            <a href="{{ route('cities.edit', $city) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                            <button type="button" class="zc-sm-btn zc-sm-btn--tog" data-toggle="{{ route('cities.toggle', $city) }}" title="Toggle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/></svg></button>
                            <button type="button" class="zc-sm-btn zc-sm-btn--del" data-delete="{{ route('cities.destroy', $city) }}" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No cities yet. Click <b>+ Add City</b>.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($cities->hasPages())<div class="zc-sm-pager">@if(!$cities->onFirstPage())<a href="{{ $cities->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>{{ $cities->currentPage() }} / {{ $cities->lastPage() }}</span>@if($cities->hasMorePages())<a href="{{ $cities->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
<div class="zc-cat-toast" id="zc-toast" role="status" aria-live="polite"></div>
@include('studio.cities._js')
@endsection
