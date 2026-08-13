@extends('layouts.studio')
@section('title', 'Account Purpose')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <div><a href="{{ route('accounts.purpose.create') }}" class="studio-command-button studio-command-button--primary">+ Add Purpose</a></div>
    <div class="zc-ac-note"><b>Fixed expense</b> (Salary, Courier Charge, Website Service Charge, Boost/Advertising cost). &nbsp; <b>Not expense</b> (Supplier Bill, Office Stationary).</div>

    <div class="studio-card" style="padding:1.25rem 1.5rem;">
        <h2 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Purposes</h2>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Debit purpose</th><th>Type</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
                @forelse ($purposes as $p)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="zc-sm-name">{{ $p->name }}</td>
                        <td><span class="zc-sm-pill {{ $p->type === 'fixed_expense' ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $p->type_label }}</span></td>
                        <td><div class="zc-sm-act"><a href="{{ route('accounts.purpose.edit', $p) }}" class="zc-sm-btn zc-sm-btn--edit" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h4L18 10l-4-4L4 16z"/></svg></a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="zc-sm-empty">No purposes yet. Click <b>+ Add Purpose</b>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
