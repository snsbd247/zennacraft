@extends('layouts.studio')
@section('title', 'Suppliers')
@section('subtitle', 'Purchase')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-sp-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:0.9rem;margin-bottom:1.1rem;}
    .zc-sp-kpi{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:14px;padding:0.9rem 1.1rem;}
    .zc-sp-kpi__l{font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--studio-muted);}
    .zc-sp-kpi__v{margin-top:0.35rem;font-size:1.35rem;font-weight:800;font-variant-numeric:tabular-nums;}
    .zc-sp-due{color:#c0392b;} .zc-sp-paid{color:#1c8a4e;}
    .zc-sp-pill{font-size:0.66rem;font-weight:800;padding:3px 9px;border-radius:999px;text-transform:uppercase;}
    .zc-sp-pill.on{background:#e3f6ea;color:#1c8a4e;} .zc-sp-pill.off{background:#eceff3;color:#64748b;}
    @media(max-width:760px){.zc-sp-kpis{grid-template-columns:repeat(2,1fr);}}
</style>@endpush
@section('content')
<div class="space-y-4">
    <div class="zc-sm-head">
        <div style="width:150px;"></div>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">Suppliers</h1>
        <a href="{{ route('purchases.create') }}" class="studio-command-button studio-command-button--primary">+ Add Purchase</a>
    </div>

    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="zc-sp-kpis">
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Suppliers</div><div class="zc-sp-kpi__v">{{ $overview['suppliers'] }}</div></div>
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Total purchased</div><div class="zc-sp-kpi__v">৳{{ number_format($overview['purchased'], 0) }}</div></div>
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Total paid</div><div class="zc-sp-kpi__v zc-sp-paid">৳{{ number_format($overview['paid'], 0) }}</div></div>
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Payable (due)</div><div class="zc-sp-kpi__v zc-sp-due">৳{{ number_format($overview['payable'], 0) }}</div></div>
    </div>

    <div class="studio-card" style="padding:1.15rem 1.35rem;">
        <form method="GET" action="{{ route('suppliers.index') }}" style="display:flex;gap:0.7rem;">
            <input name="search" value="{{ $search }}" class="studio-form-control" placeholder="Search supplier name or phone" style="flex:1;">
            <button type="submit" class="studio-command-button studio-command-button--primary">Search</button>
        </form>
    </div>

    <div class="studio-card" style="padding:1rem 1.25rem;">
        <div class="studio-responsive-scroll">
            <table class="zc-sm-tbl">
                <thead><tr><th>Supplier</th><th>Phone</th><th>Purchases</th><th>Purchased</th><th>Paid</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        @php
                            $purchased = (float) ($supplier->purchased_sum ?? 0);
                            $paid = (float) ($supplier->paid_sum ?? 0);
                            $due = max(0, $purchased - $paid);
                        @endphp
                        <tr>
                            <td class="zc-sm-name"><a href="{{ route('suppliers.show', $supplier) }}" style="color:var(--studio-accent);font-weight:700;">{{ $supplier->name }}</a></td>
                            <td>{{ $supplier->phone ?: '—' }}</td>
                            <td>{{ $supplier->purchases_count }}</td>
                            <td>৳{{ number_format($purchased, 0) }}</td>
                            <td class="zc-sp-paid" style="font-weight:700;">৳{{ number_format($paid, 0) }}</td>
                            <td class="zc-sp-due" style="font-weight:800;">৳{{ number_format($due, 0) }}</td>
                            <td><span class="zc-sp-pill {{ $supplier->status === 'active' ? 'on' : 'off' }}">{{ $supplier->status }}</span></td>
                            <td><a href="{{ route('suppliers.show', $supplier) }}" class="studio-command-button" style="padding:0.35rem 0.8rem;font-size:0.75rem;">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="zc-sm-empty">No suppliers yet. Suppliers are created when you add a purchase.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $suppliers->links() }}</div>
    </div>
</div>
@endsection
