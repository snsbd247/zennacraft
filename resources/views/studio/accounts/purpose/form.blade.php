@extends('layouts.studio')
@section('title', ($purpose->exists ? 'Edit' : 'Add').' Purpose')
@section('subtitle', 'Accounts')
@push('studio-styles')@include('studio.accounts.partials._accounts-styles')@endpush
@section('content')
<div class="space-y-4">
    <a href="{{ route('accounts.purpose.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif
    <div class="zc-ac-note"><b>Fixed expense</b> (Salary, Courier Charge, Website Service Charge, Boost/Advertising cost). &nbsp; <b>Not expense</b> (Supplier Bill, Office Stationary).</div>

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="zc-ac-title">{{ $purpose->exists ? 'Edit' : 'Add' }} purpose</h1>
        <form class="zc-cf" method="POST" action="{{ $purpose->exists ? route('accounts.purpose.update', $purpose) : route('accounts.purpose.store') }}" style="margin-top:1rem;">
            @csrf @if($purpose->exists) @method('PUT') @endif
            <div class="zc-cf-row"><label>Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $purpose->name) }}" placeholder="type purpose" required></div>
            <div class="zc-cf-row">
                <label>Type <span class="req">*</span></label>
                <select name="type" class="studio-form-control">
                    @foreach (\App\Modules\Finance\Models\AccountPurpose::TYPES as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $purpose->type) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="text-align:center;margin-top:1.4rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">Submit</button></div>
        </form>
    </div>
</div>
@endsection
