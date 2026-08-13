@extends('layouts.studio')
@section('title', ($coupon->exists ? 'Edit' : 'Add').' Coupon')
@section('subtitle', 'Coupon')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cf{max-width:780px;margin:0 auto;}
    .zc-cf-row{display:grid;grid-template-columns:190px 1fr;gap:1.1rem;align-items:start;margin-bottom:1rem;}
    .zc-cf-row > label{font-weight:700;font-size:0.85rem;padding-top:0.6rem;color:var(--studio-text);}
    .zc-cf-row > label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;}
    @media(max-width:640px){.zc-cf-row{grid-template-columns:1fr;gap:0.35rem;}.zc-cf-row>label{padding-top:0;}}
    .zc-cf-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    @media(max-width:640px){.zc-cf-2{grid-template-columns:1fr;}}
    .zc-cf .req{color:#e0483d;font-weight:800;}
    .zc-cf-affix{display:flex;align-items:stretch;}
    .zc-cf-affix span{display:grid;place-items:center;padding:0 0.9rem;background:var(--studio-surface-soft);border:1px solid var(--studio-border);border-right:none;border-radius:10px 0 0 10px;font-weight:800;color:var(--studio-muted);}
    .zc-cf-affix input{border-radius:0 10px 10px 0 !important;}
    .zc-cf-check{display:inline-flex;align-items:center;gap:0.6rem;font-weight:700;cursor:pointer;}
    .zc-cf-check input{width:1.15rem;height:1.15rem;accent-color:var(--studio-accent);}
</style>@endpush
@section('content')
@php
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : '';
@endphp
<div class="space-y-4">
    <a href="{{ route('coupons.index') }}" class="studio-command-button">&larr; Back</a>
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.75rem 2rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $coupon->exists ? 'Edit' : 'Add' }} Coupon</h1>

        <form class="zc-cf" method="POST" action="{{ $coupon->exists ? route('coupons.update', $coupon) : route('coupons.store') }}" style="margin-top:1rem;">
            @csrf @if($coupon->exists) @method('PUT') @endif

            <div class="zc-cf-row"><label>Coupon Code <span class="req">*</span> <small>customers type this at checkout</small></label><input name="code" class="studio-form-control" value="{{ old('code', $coupon->code) }}" placeholder="e.g. EID500" style="text-transform:uppercase;font-family:ui-monospace,monospace;font-weight:700;" required></div>
            <div class="zc-cf-row"><label>Name <span class="req">*</span></label><input name="name" class="studio-form-control" value="{{ old('name', $coupon->name) }}" placeholder="Internal label, e.g. Eid ৳500 off" required></div>
            <div class="zc-cf-row"><label>Description</label><input name="description" class="studio-form-control" value="{{ old('description', $coupon->description) }}" placeholder="optional"></div>

            <div class="zc-cf-row">
                <label>Discount <span class="req">*</span></label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Type</label>
                        <select name="discount_type" id="zc-cp-type" class="studio-form-control">
                            <option value="percentage" @selected(old('discount_type', $coupon->discount_type)==='percentage')>Percentage (%)</option>
                            <option value="fixed" @selected(old('discount_type', $coupon->discount_type)==='fixed')>Flat amount (৳)</option>
                            <option value="free_shipping" @selected(old('discount_type', $coupon->discount_type)==='free_shipping')>Free shipping</option>
                        </select>
                    </div>
                    <div class="zc-sm-field" style="margin:0;" id="zc-cp-value-wrap"><label style="font-size:0.72rem;">Value</label>
                        <div class="zc-cf-affix"><span id="zc-cp-affix">%</span><input type="number" step="0.01" min="0" name="discount_value" id="zc-cp-value" class="studio-form-control" value="{{ old('discount_value', $coupon->discount_value ? (float) $coupon->discount_value : '') }}" placeholder="0"></div>
                    </div>
                </div>
            </div>

            <div class="zc-cf-row" id="zc-cp-max-wrap">
                <label>Max Discount <small>cap for percentage (optional)</small></label>
                <div class="zc-cf-affix" style="max-width:16rem;"><span>৳</span><input type="number" step="0.01" min="0" name="max_discount_amount" class="studio-form-control" value="{{ old('max_discount_amount', $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : '') }}" placeholder="e.g. 500"></div>
            </div>

            <div class="zc-cf-row">
                <label>Minimum Order <small>coupon works only above this</small></label>
                <div class="zc-cf-affix" style="max-width:16rem;"><span>৳</span><input type="number" step="0.01" min="0" name="min_order_amount" class="studio-form-control" value="{{ old('min_order_amount', (float) $coupon->min_order_amount ?: '') }}" placeholder="0"></div>
            </div>

            <div class="zc-cf-row">
                <label>Usage Limits <small>leave blank for unlimited</small></label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Total uses</label><input type="number" min="1" name="usage_limit" class="studio-form-control" value="{{ old('usage_limit', $coupon->usage_limit) }}" placeholder="∞"></div>
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Per customer</label><input type="number" min="1" name="usage_limit_per_customer" class="studio-form-control" value="{{ old('usage_limit_per_customer', $coupon->usage_limit_per_customer) }}" placeholder="∞"></div>
                </div>
            </div>

            <div class="zc-cf-row">
                <label>Schedule <small>optional start / end</small></label>
                <div class="zc-cf-2">
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Starts</label><input type="datetime-local" name="starts_at" class="studio-form-control" value="{{ old('starts_at', $fmt($coupon->starts_at)) }}"></div>
                    <div class="zc-sm-field" style="margin:0;"><label style="font-size:0.72rem;">Ends</label><input type="datetime-local" name="ends_at" class="studio-form-control" value="{{ old('ends_at', $fmt($coupon->ends_at)) }}"></div>
                </div>
            </div>

            <div class="zc-cf-row"><label>Status</label>
                <select name="status" class="studio-form-control" style="max-width:16rem;">
                    <option value="active" @selected(old('status', $coupon->status ?: 'active')==='active')>Active (usable at checkout)</option>
                    <option value="inactive" @selected(old('status', $coupon->status)==='inactive')>Inactive</option>
                </select>
            </div>

            <div style="text-align:center;margin-top:1.4rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:2.5rem;">{{ $coupon->exists ? 'Update' : 'Create' }} Coupon</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    (function(){
        var type=document.getElementById('zc-cp-type'), affix=document.getElementById('zc-cp-affix'),
            valueWrap=document.getElementById('zc-cp-value-wrap'), maxWrap=document.getElementById('zc-cp-max-wrap'),
            value=document.getElementById('zc-cp-value');
        function sync(){
            var t=type.value;
            if(t==='free_shipping'){ valueWrap.style.display='none'; maxWrap.style.display='none'; value.removeAttribute('required'); }
            else { valueWrap.style.display=''; value.setAttribute('required','required');
                affix.textContent = t==='percentage' ? '%' : '৳';
                maxWrap.style.display = t==='percentage' ? '' : 'none';
            }
        }
        type.addEventListener('change', sync); sync();
    })();
</script>
@endpush
@endsection
