@extends('layouts.studio')
@section('title', 'Damage Products')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route('products.damages.create') }}" class="studio-command-button studio-command-button--primary">+ Damage Add</a>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">Damage Products</h1>
        <div style="width:130px;"></div>
    </div>
    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Damage Date</th><th>Items</th><th>Total Amount</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($damages as $damage)
                    <tr>
                        <td>{{ $loop->iteration + ($damages->firstItem() ? $damages->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $damage->damage_date?->format('Y-m-d') }}</td>
                        <td>{{ $damage->items_count }}</td>
                        <td style="font-weight:800;color:#c0392b;">{{ number_format((float) $damage->total_amount, 2) }}</td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('products.damages.show', $damage) }}" class="zc-sm-btn zc-sm-btn--view" title="View"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <form method="POST" action="{{ route('products.damages.destroy', $damage) }}" onsubmit="return confirm('Delete this damage record?')">@csrf @method('DELETE')<button class="zc-sm-btn zc-sm-btn--del" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="zc-sm-empty">No damage records yet. Click <b>+ Damage Add</b> to log damaged/lost stock.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($damages->hasPages())<div class="zc-sm-pager">@if(!$damages->onFirstPage())<a href="{{ $damages->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $damages->currentPage() }} / {{ $damages->lastPage() }}</span>@if($damages->hasMorePages())<a href="{{ $damages->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
@endsection
