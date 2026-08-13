@extends('layouts.studio')
@section('title', 'Damage Record')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('products.damages.index') }}" class="studio-command-button">&larr; Back</a>
    <div class="studio-card" style="padding:1.5rem 1.75rem;">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <div><div class="zc-sm-field" style="margin:0;"><label>Damage Date</label><div class="zc-sm-name">{{ $damage->damage_date?->format('Y-m-d') }}</div></div></div>
            <div style="text-align:right;"><div class="zc-sm-field" style="margin:0;"><label>Total Amount</label><div style="font-weight:800;font-size:1.3rem;color:#c0392b;">{{ number_format((float) $damage->total_amount, 2) }}</div></div></div>
        </div>
        @if ($damage->note)<div class="zc-sm-field"><label>Note</label><div>{{ $damage->note }}</div></div>@endif
        <table class="zc-sm-tbl" style="margin-top:0.5rem;">
            <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th></tr></thead>
            <tbody>
                @foreach ($damage->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="zc-sm-name">{{ $item->product_name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td style="font-weight:800;">{{ number_format((float) $item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
