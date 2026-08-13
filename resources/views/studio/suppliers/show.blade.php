@extends('layouts.studio')
@section('title', $supplier->name)
@section('subtitle', 'Supplier')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-sp-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:1.1rem;align-items:start;}
    .zc-sp-stat3{display:grid;grid-template-columns:repeat(3,1fr);gap:0.9rem;margin-bottom:1.1rem;}
    .zc-sp-kpi{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:14px;padding:0.9rem 1.1rem;}
    .zc-sp-kpi__l{font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--studio-muted);}
    .zc-sp-kpi__v{margin-top:0.35rem;font-size:1.4rem;font-weight:800;font-variant-numeric:tabular-nums;}
    .zc-sp-due{color:#c0392b;} .zc-sp-paid{color:#1c8a4e;}
    .zc-sp-card{background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:16px;overflow:hidden;}
    .zc-sp-card__h{padding:0.85rem 1.15rem;border-bottom:1px solid var(--studio-border);font-weight:800;font-size:0.95rem;}
    .zc-sp-card__b{padding:1.1rem 1.15rem;}
    .zc-sp-field{margin-bottom:0.8rem;}
    .zc-sp-field label{display:block;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--studio-muted);margin-bottom:0.35rem;}
    @media(max-width:960px){.zc-sp-grid{grid-template-columns:1fr;}.zc-sp-stat3{grid-template-columns:1fr;}}
</style>@endpush
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="space-y-4">
    <div class="zc-sm-head">
        <a href="{{ route('suppliers.index') }}" class="studio-command-button">← Suppliers</a>
        <h1 class="studio-section-title" style="flex:1;text-align:center;">{{ $supplier->name }}</h1>
        <div style="width:120px;"></div>
    </div>

    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="zc-sp-stat3">
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Total purchased</div><div class="zc-sp-kpi__v">৳{{ $money($stats['purchased']) }}</div><div class="zc-sm-muted" style="font-size:0.72rem;">{{ $stats['count'] }} purchase{{ $stats['count'] === 1 ? '' : 's' }}</div></div>
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Total paid</div><div class="zc-sp-kpi__v zc-sp-paid">৳{{ $money($stats['paid']) }}</div></div>
        <div class="zc-sp-kpi"><div class="zc-sp-kpi__l">Payable (due)</div><div class="zc-sp-kpi__v zc-sp-due">৳{{ $money($stats['due']) }}</div></div>
    </div>

    <div class="zc-sp-grid">
        {{-- Main: purchase + payment history --}}
        <div class="space-y-4">
            <div class="zc-sp-card">
                <div class="zc-sp-card__h">Purchase history</div>
                <div class="studio-responsive-scroll">
                    <table class="zc-sm-tbl">
                        <thead><tr><th>Date</th><th>Invoice</th><th>Items</th><th>Amount</th><th>Paid</th><th>Due</th></tr></thead>
                        <tbody>
                            @forelse ($purchases as $p)
                                @php $pdue = max(0, (float) $p->total_amount - (float) $p->paid_amount); @endphp
                                <tr>
                                    <td class="zc-sm-name">{{ $p->purchase_date?->format('d-m-Y') }}</td>
                                    <td>{{ $p->invoice_no ?: '—' }}</td>
                                    <td>{{ $p->items_count }}</td>
                                    <td>৳{{ $money($p->total_amount) }}</td>
                                    <td class="zc-sp-paid" style="font-weight:700;">৳{{ $money($p->paid_amount) }}</td>
                                    <td class="zc-sp-due" style="font-weight:800;">৳{{ $money($pdue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="zc-sm-empty">No purchases recorded for this supplier.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="zc-sp-card">
                <div class="zc-sp-card__h">Payment history</div>
                <div class="studio-responsive-scroll">
                    <table class="zc-sm-tbl">
                        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Note</th><th>By</th></tr></thead>
                        <tbody>
                            @forelse ($payments as $pay)
                                <tr>
                                    <td class="zc-sm-name">{{ $pay->paid_on?->format('d-m-Y') }}</td>
                                    <td class="zc-sp-paid" style="font-weight:800;">৳{{ $money($pay->amount) }}</td>
                                    <td>{{ $pay->method ?: '—' }}</td>
                                    <td style="color:var(--studio-muted);">{{ $pay->note ?: '—' }}</td>
                                    <td>{{ $pay->created_by_name ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="zc-sm-empty">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Sidebar: record payment + edit supplier --}}
        <div class="space-y-4">
            <div class="zc-sp-card">
                <div class="zc-sp-card__h">Record a payment</div>
                <div class="zc-sp-card__b">
                    @if ($stats['due'] <= 0)
                        <div class="zc-sm-muted" style="font-size:0.85rem;">No outstanding due — this supplier is fully paid.</div>
                    @else
                        <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
                            @csrf
                            <div class="zc-sp-field"><label>Amount (due ৳{{ $money($stats['due']) }})</label><input type="number" name="amount" step="0.01" min="0.01" max="{{ $stats['due'] }}" class="studio-form-control" placeholder="0.00" required></div>
                            <div class="zc-sp-field"><label>Paid on</label><input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="studio-form-control"></div>
                            <div class="zc-sp-field"><label>Method (optional)</label><input type="text" name="method" class="studio-form-control" placeholder="Cash / bKash / Bank"></div>
                            <div class="zc-sp-field"><label>Note (optional)</label><input type="text" name="note" class="studio-form-control" placeholder="Reference / memo"></div>
                            <button type="submit" class="studio-command-button studio-command-button--primary" style="width:100%;justify-content:center;">Record payment</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="zc-sp-card">
                <div class="zc-sp-card__h">Supplier details</div>
                <div class="zc-sp-card__b">
                    <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                        @csrf @method('PUT')
                        <div class="zc-sp-field"><label>Name</label><input type="text" name="name" value="{{ $supplier->name }}" class="studio-form-control" required></div>
                        <div class="zc-sp-field"><label>Phone</label><input type="text" name="phone" value="{{ $supplier->phone }}" class="studio-form-control"></div>
                        <div class="zc-sp-field"><label>Email</label><input type="email" name="email" value="{{ $supplier->email }}" class="studio-form-control"></div>
                        <div class="zc-sp-field"><label>Address</label><input type="text" name="address" value="{{ $supplier->address }}" class="studio-form-control"></div>
                        <div class="zc-sp-field"><label>Status</label>
                            <select name="status" class="studio-form-control">
                                <option value="active" @selected($supplier->status === 'active')>Active</option>
                                <option value="inactive" @selected($supplier->status === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <button type="submit" class="studio-command-button studio-command-button--primary" style="width:100%;justify-content:center;">Save details</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
