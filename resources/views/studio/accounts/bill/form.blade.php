@extends('layouts.studio')
@section('title', ($bill->exists ? 'Edit' : 'Add').' Bill')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('accounts.bill.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="zc-ac-title">{{ $bill->exists ? 'Edit' : 'Add' }} Bill</h1>
        <form class="zc-cf" method="POST" action="{{ $bill->exists ? route('accounts.bill.update', $bill) : route('accounts.bill.store') }}" style="margin-top:1rem;">
            @csrf @if($bill->exists) @method('PUT') @endif
            <div class="zc-cf-row"><label>Bill Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $bill->name) }}" placeholder="e.g. Electricity, Office Rent" required></div>
            <div class="zc-cf-row"><label>Status</label>
                <select name="status" class="studio-form-control">
                    <option value="active" @selected(old('status', $bill->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $bill->status) === 'inactive')>Disabled</option>
                </select>
            </div>
            <div style="text-align:center;margin-top:1.4rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@endsection
