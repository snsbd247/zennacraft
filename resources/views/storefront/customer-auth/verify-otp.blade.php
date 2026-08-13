@extends('layouts.app')
@section('title', 'Verify code — '.$storeName)

@push('storefront-styles')
<style>
    .zc-otp{display:flex;gap:10px;justify-content:space-between;margin:8px 0 6px;}
    .zc-otp__box{width:100%;max-width:54px;aspect-ratio:1/1.12;text-align:center;font-size:24px;font-weight:800;color:var(--ink);
        border:1.6px solid var(--line);border-radius:13px;background:var(--surface);outline:none;font-family:inherit;
        transition:border-color .15s ease,box-shadow .15s ease,transform .08s ease;-webkit-appearance:none;appearance:none;}
    .zc-otp__box:focus{border-color:var(--leaf);box-shadow:0 0 0 4px var(--leaf-soft);transform:translateY(-1px);}
    .zc-otp__box.is-filled{border-color:var(--leaf);background:var(--leaf-soft);}
    .zc-otp.is-verifying .zc-otp__box{border-color:var(--leaf);opacity:.7;}
    .zc-otp__hint{font-size:12.5px;color:var(--muted);margin:10px 0 18px;text-align:center;}
    .zc-otp__hint b{color:var(--leaf-deep);}
    @media(max-width:400px){.zc-otp{gap:7px;}.zc-otp__box{font-size:20px;border-radius:11px;}}
    /* No-JS: hide the fancy boxes, show the plain fallback input. */
    .zc-otp-fallback{display:none;}
</style>
<noscript><style>.zc-otp{display:none;}.zc-otp-fallback{display:block;}</style></noscript>
@endpush

@section('content')
<section class="zc-sec zc-wrap" style="max-width:460px;">
    <div class="zc-card" style="padding:32px;">
        <div style="font-size:12px;letter-spacing:.2em;text-transform:uppercase;color:var(--honey-deep);font-weight:800;">Verify</div>
        <h1 style="font-size:26px;margin:6px 0 8px;">Enter your code</h1>
        <p class="zc-muted" style="font-size:14px;margin-bottom:18px;">We sent a 6-digit code{!! !empty($phone) ? ' to <b>'.e($phone).'</b>' : '' !!}.</p>
        @if (session('status'))<div class="zc-note" style="margin-bottom:14px;">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="zc-note zc-note--warn" style="margin-bottom:14px;">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('customer.otp.verify') }}" data-otp-form>
            @csrf
            @if(!empty($phone))<input type="hidden" name="phone" value="{{ $phone }}">@endif

            <label style="display:block;font-size:12.5px;font-weight:700;color:var(--ink);margin-bottom:6px;">Verification code</label>

            {{-- Premium 6-box input (progressive enhancement) --}}
            <div class="zc-otp" data-otp-boxes role="group" aria-label="6-digit verification code">
                @for ($i = 0; $i < 6; $i++)
                    <input class="zc-otp__box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           @if($i === 0) autocomplete="one-time-code" autofocus @else autocomplete="off" @endif
                           aria-label="Digit {{ $i + 1 }}">
                @endfor
            </div>
            <input type="hidden" name="otp" data-otp-value>

            {{-- No-JS fallback --}}
            <input class="zc-input zc-otp-fallback" type="text" name="otp" inputmode="numeric" maxlength="6"
                   placeholder="000000" style="letter-spacing:.35em;text-align:center;font-size:20px;margin-bottom:6px;">

            <div class="zc-otp__hint" data-otp-hint>The code confirms <b>automatically</b> once you enter all 6 digits.</div>

            <button type="submit" class="zc-btn zc-btn--primary zc-btn--block" data-otp-submit>Verify &amp; sign in</button>
        </form>
        <a href="{{ route('customer.login') }}" class="zc-btn zc-btn--outline zc-btn--block zc-btn--sm" style="margin-top:12px;">Use a different number</a>
    </div>
</section>
@endsection

@push('storefront-scripts')
<script>
(function () {
    var form = document.querySelector('[data-otp-form]');
    if (!form) return;
    var wrap = form.querySelector('[data-otp-boxes]');
    var boxes = [].slice.call(form.querySelectorAll('.zc-otp__box'));
    var hidden = form.querySelector('[data-otp-value]');
    var btn = form.querySelector('[data-otp-submit]');
    if (!boxes.length || !hidden) return;

    // The visible boxes drive a hidden `otp` field; remove the no-JS fallback
    // input so it can't submit a second empty value.
    var fallback = form.querySelector('.zc-otp-fallback');
    if (fallback) fallback.parentNode.removeChild(fallback);

    var submitted = false;

    function submit() {
        if (submitted) return;
        submitted = true;
        wrap.classList.add('is-verifying');
        if (btn) { btn.disabled = true; btn.textContent = 'Verifying…'; }
        form.submit();
    }

    function sync() {
        var code = boxes.map(function (b) { return b.value; }).join('');
        hidden.value = code;
        boxes.forEach(function (b) { b.classList.toggle('is-filled', b.value !== ''); });
        if (/^\d{6}$/.test(code)) submit();
    }

    // Distribute a pasted / SMS-autofilled string across the boxes.
    function fill(str) {
        var digits = (String(str).match(/\d/g) || []).slice(0, 6);
        boxes.forEach(function (b, i) { b.value = digits[i] || ''; });
        (boxes[Math.min(digits.length, 5)] || boxes[5]).focus();
        sync();
    }

    boxes.forEach(function (box, i) {
        box.addEventListener('input', function () {
            var v = box.value.replace(/\D/g, '');
            if (v.length > 1) { fill(v); return; }   // multi-char (autofill/paste)
            box.value = v;
            if (v && i < 5) boxes[i + 1].focus();
            sync();
        });
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !box.value && i > 0) { e.preventDefault(); boxes[i - 1].focus(); boxes[i - 1].value = ''; sync(); }
            else if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); boxes[i - 1].focus(); }
            else if (e.key === 'ArrowRight' && i < 5) { e.preventDefault(); boxes[i + 1].focus(); }
        });
        box.addEventListener('paste', function (e) {
            e.preventDefault();
            fill((e.clipboardData || window.clipboardData).getData('text'));
        });
        box.addEventListener('focus', function () { box.select(); });
    });

    if (boxes[0]) boxes[0].focus();
})();
</script>
@endpush
