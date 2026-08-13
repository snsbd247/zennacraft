@extends('layouts.app')
@section('title', 'Sign in — '.$storeName)
@section('content')
<section class="zc-sec zc-wrap" style="max-width:460px;">
    <div class="zc-card" style="padding:32px;">
        <div style="font-size:12px;letter-spacing:.2em;text-transform:uppercase;color:var(--honey-deep);font-weight:800;">Customer account</div>
        <h1 style="font-size:26px;margin:6px 0 8px;">Sign in</h1>
        <p class="zc-muted" style="font-size:14px;margin-bottom:18px;">Enter your phone number and we'll send a one-time code.</p>
        @if (session('status'))<div class="zc-note" style="margin-bottom:14px;">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="zc-note zc-note--warn" style="margin-bottom:14px;">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('customer.otp.request') }}">
            @csrf
            <div class="zc-field"><label for="phone">Phone number</label><input id="phone" class="zc-input" name="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX"></div>
            <button type="submit" class="zc-btn zc-btn--primary zc-btn--block">Send code</button>
        </form>
        <div style="margin-top:16px;display:flex;gap:10px;">
            <a href="{{ route('tracking.form') }}" class="zc-btn zc-btn--outline zc-btn--block zc-btn--sm">Track order</a>
            <a href="{{ route('storefront.products') }}" class="zc-btn zc-btn--outline zc-btn--block zc-btn--sm">Keep shopping</a>
        </div>
    </div>
</section>
@endsection
