@extends('layouts.studio')
@section('title', 'Purchase #'.$purchase->id)
@section('subtitle', 'Purchase')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route('purchases.index') }}" class="studio-command-button">&larr; Back</a>
        <a href="{{ route('purchases.edit', $purchase) }}" class="studio-command-button studio-command-button--primary">Edit</a>
    </div>
    <div class="studio-card" style="padding:1.5rem 1.75rem;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem;">
            <div class="zc-sm-field" style="margin:0;"><label>Date</label><div class="zc-sm-name">{{ $purchase->purchase_date?->format('d-m-Y') }}</div></div>
            <div class="zc-sm-field" style="margin:0;"><label>Supplier</label><div class="zc-sm-name">{{ $purchase->supplier->name ?? '—' }}</div></div>
            <div class="zc-sm-field" style="margin:0;"><label>Invoice</label><div class="zc-sm-name">{{ $purchase->invoice_no ?: '—' }}</div></div>
            <div class="zc-sm-field" style="margin:0;"><label>Purchase By</label><div class="zc-sm-name">{{ $purchase->created_by_name ?: '—' }}</div></div>
        </div>
        @if ($purchase->comment)<div class="zc-sm-field"><label>Comment</label><div>{{ $purchase->comment }}</div></div>@endif
        <table class="zc-sm-tbl" style="margin-top:0.5rem;">
            <thead><tr><th>#</th><th>Product</th><th>Code</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr><td>{{ $loop->iteration }}</td><td class="zc-sm-name">{{ $item->product_name }}</td><td>{{ $item->product_code ?: '—' }}</td><td>{{ number_format((float) $item->purchase_price, 2) }}</td><td>{{ $item->quantity }}</td><td style="font-weight:800;">{{ number_format((float) $item->subtotal, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div style="display:flex;justify-content:flex-end;gap:2rem;margin-top:1rem;font-size:0.95rem;">
            <div>Total: <b>{{ number_format((float) $purchase->total_amount, 2) }}</b></div>
            <div style="color:#1c8a4e;">Paid: <b>{{ number_format((float) $purchase->paid_amount, 2) }}</b></div>
            <div style="color:#c0392b;">Due: <b>{{ number_format($purchase->due_amount, 2) }}</b></div>
        </div>
    </div>
</div>
@endsection
