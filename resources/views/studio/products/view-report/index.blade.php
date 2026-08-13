@extends('layouts.studio')
@section('title', 'Product View Report')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <form method="GET" action="{{ route('products.view-report.index') }}" class="zc-sm-filters studio-card" style="padding:1rem 1.25rem;">
        <div class="zc-sm-field"><label>From</label><input type="date" name="from" value="{{ $from }}" class="studio-form-control"></div>
        <div class="zc-sm-field"><label>To</label><input type="date" name="to" value="{{ $to }}" class="studio-form-control"></div>
        <div class="zc-sm-field" style="flex:1;min-width:200px;"><label>Search</label><input type="text" name="q" value="{{ $q }}" class="studio-form-control" placeholder="Product name"></div>
        <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
    </form>
    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Product</th><th>Views</th><th>Last viewed</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $loop->iteration + ($rows->firstItem() ? $rows->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-sm-prod">
                                <span class="zc-sm-thumb">@if(!empty($row->thumbnail_id) && ($thumbs[$row->thumbnail_id] ?? null))<img src="{{ $thumbs[$row->thumbnail_id] }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:9px;">@else<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"><path d="M5 21V9l7-5 7 5v12"/></svg>@endif</span>
                                <span class="zc-sm-name">{{ $row->product_name }}</span>
                            </div>
                        </td>
                        <td style="font-weight:800;color:#3b6ea5;">{{ number_format($row->views) }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->last_viewed)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="zc-sm-empty">No product views recorded for this range yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($rows->hasPages())<div class="zc-sm-pager">@if(!$rows->onFirstPage())<a href="{{ $rows->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
@endsection
