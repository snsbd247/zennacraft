@extends('layouts.studio')
@section('title', 'Purchase')
@section('subtitle', 'Purchase')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-pu-filters{display:grid;grid-template-columns:1.6fr 1fr 1fr 0.8fr auto;gap:0.8rem;align-items:end;}
    .zc-pu-filters .zc-sm-field{margin:0;}
    .zc-pu-filters .studio-command-button{height:46px;padding-inline:1.6rem;}
    @media(max-width:900px){.zc-pu-filters{grid-template-columns:1fr 1fr;}.zc-pu-filters .studio-command-button{grid-column:1 / -1;}}
    .zc-pu-due{font-weight:800;color:#c0392b;} .zc-pu-paid{font-weight:800;color:#1c8a4e;}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route('purchases.create') }}" class="studio-command-button studio-command-button--primary">+ Add Purchase</a>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">Purchase</h1>
        <div style="width:150px;"></div>
    </div>
    <div class="studio-card" style="padding:1.15rem 1.35rem;">
        <form method="GET" action="{{ route('purchases.index') }}" class="zc-pu-filters">
            <div class="zc-sm-field"><label>Invoice No</label><input name="invoice" value="{{ $filters['invoice'] }}" class="studio-form-control" placeholder="enter invoice no"></div>
            <div class="zc-sm-field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="studio-form-control"></div>
            <div class="zc-sm-field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="studio-form-control"></div>
            <div class="zc-sm-field"><label>Show</label>
                <select name="per_page" class="studio-form-control">@foreach ($perPageOptions as $n)<option value="{{ $n }}" @selected($filters['per_page'] === $n)>{{ $n }}</option>@endforeach</select>
            </div>
            <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
        </form>
    </div>
    <div class="studio-card" style="padding:1rem 1.25rem;">
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Date</th><th>Supplier</th><th>Invoice</th><th>Comment</th><th>Paid</th><th>Amount</th><th>Due</th><th>Purchase By</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>{{ $loop->iteration + ($purchases->firstItem() ? $purchases->firstItem() - 1 : 0) }}</td>
                        <td class="zc-sm-name">{{ $purchase->purchase_date?->format('d-m-Y') }}</td>
                        <td>{{ $purchase->supplier->name ?? '—' }}</td>
                        <td>{{ $purchase->invoice_no ?: '—' }}</td>
                        <td style="max-width:180px;color:var(--studio-muted);">{{ \Illuminate\Support\Str::limit($purchase->comment, 30) ?: 'empty' }}</td>
                        <td class="zc-pu-paid">{{ number_format((float) $purchase->paid_amount) }}</td>
                        <td style="font-weight:800;">{{ number_format((float) $purchase->total_amount) }}</td>
                        <td class="zc-pu-due">{{ number_format($purchase->due_amount) }}</td>
                        <td>{{ $purchase->created_by_name ?: '—' }}</td>
                        <td>
                            <div class="zc-sm-act">
                                <a href="{{ route('purchases.show', $purchase) }}" class="zc-sm-btn zc-sm-btn--view" title="View"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                <a href="{{ route('purchases.edit', $purchase) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a>
                                <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" onsubmit="return confirm('Delete this purchase? Stock added by it will be reversed.')">@csrf @method('DELETE')<button class="zc-sm-btn zc-sm-btn--del" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="zc-sm-empty">No purchases yet. Click <b>+ Add Purchase</b> to record stock you've bought in.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($purchases->hasPages())<div class="zc-sm-pager">@if(!$purchases->onFirstPage())<a href="{{ $purchases->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $purchases->currentPage() }} / {{ $purchases->lastPage() }}</span>@if($purchases->hasMorePages())<a href="{{ $purchases->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
@endsection
