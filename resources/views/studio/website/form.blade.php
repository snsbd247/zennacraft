@extends('layouts.studio')
@section('title', $cfg['title'])
@section('subtitle', 'Website Setup')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cfg{max-width:960px;margin:0 auto;}
    .zc-cfg-sec{
        position:relative;overflow:hidden;margin-top:1.25rem;border-radius:18px;
        background:
            radial-gradient(circle at 100% 0%, rgba(212,180,131,0.06), transparent 42%),
            linear-gradient(180deg, rgba(255,253,247,0.02), rgba(255,253,247,0)),
            var(--studio-surface);
        border:1px solid var(--studio-border);
        box-shadow:0 1px 2px rgba(16,24,40,0.04), 0 20px 50px -40px rgba(16,24,40,0.35);
        transition:box-shadow .18s ease;
    }
    .zc-cfg-sec::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(212,180,131,0.55),transparent);}
    .zc-cfg-sec:hover{box-shadow:0 1px 2px rgba(16,24,40,0.05), 0 26px 56px -36px rgba(16,24,40,0.4);}
    .zc-cfg-sec > h3{margin:0;padding:1rem 1.25rem;background:var(--studio-surface-soft);font-size:0.92rem;font-weight:800;letter-spacing:.01em;color:var(--studio-text);border-bottom:1px solid var(--studio-border);text-align:center;}
    .zc-cfg-desc{padding:0.75rem 1.1rem 0;font-size:0.8rem;color:var(--studio-muted);line-height:1.55;}
    .zc-cfg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem 1.5rem;padding:1.35rem;}
    .zc-cfg-f label{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--studio-muted);margin-bottom:.5rem;}
    .zc-cfg-f.full{grid-column:1/-1;}
    .zc-cfg-help{margin-top:.5rem;font-size:.76rem;line-height:1.55;color:var(--studio-muted);}
    .zc-cfg-f .studio-form-control{transition:border-color .16s ease, box-shadow .16s ease;}
    .zc-cfg-f .studio-form-control:focus{border-color:rgba(201,154,59,0.55) !important;box-shadow:0 0 0 3px rgba(201,154,59,0.12) !important;outline:none;}
    /* Higher specificity than ".zc-cfg-f label" above — see form.blade.php
       for why the checkbox row needs this rather than the generic label rule. */
    .zc-cfg-f--checkbox{align-self:end;}
    .zc-cfg-f label.zc-cfg-check{display:inline-flex;align-items:center;gap:.65rem;margin-bottom:0;font-size:.85rem;font-weight:700;text-transform:none;letter-spacing:normal;color:var(--studio-text);cursor:pointer;}
    .zc-cfg-check input{width:1.2rem;height:1.2rem;flex:none;accent-color:var(--studio-accent);cursor:pointer;}
    .zc-color2{display:flex;align-items:stretch;gap:8px;}
    .zc-color2__pick{width:64px;height:40px;flex:none;border:1px solid var(--studio-border);border-radius:9px;background:none;cursor:pointer;padding:3px;}
    .zc-color2__pick::-webkit-color-swatch{border:none;border-radius:6px;} .zc-color2__pick::-webkit-color-swatch-wrapper{padding:0;}
    .zc-color2__hex{flex:1;font-family:ui-monospace,Menlo,monospace;font-weight:600;}
    .zc-img-prev{width:110px;height:70px;object-fit:contain;border:1px solid var(--studio-border);border-radius:9px;background:var(--studio-surface-soft);padding:5px;margin-bottom:7px;display:block;}
    .zc-tprev{position:relative;max-width:520px;margin:1.1rem auto 0;border:1px solid var(--studio-border);border-radius:16px;overflow:hidden;box-shadow:0 20px 44px -30px rgba(0,0,0,.4);background:#fff;}
    .zc-tprev__label{position:absolute;top:8px;right:12px;font-size:0.66rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--studio-muted);z-index:2;}
    .zc-tprev__nav{display:flex;align-items:center;gap:16px;padding:12px 16px;font-size:0.85rem;font-weight:600;}
    .zc-tprev__nav b{font-weight:900;margin-right:6px;}
    .zc-tprev__body{padding:20px 16px;background:#fff;}
    .zc-tprev__price{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
    .zc-tprev__price span:last-child{text-decoration:line-through;opacity:.85;}
    .zc-tprev__btns{display:flex;gap:10px;flex-wrap:wrap;}
    .zc-tprev__btns button{border:none;border-radius:10px;padding:11px 22px;font-weight:800;font-size:0.85rem;cursor:default;}
    .zc-tprev__foot{padding:12px 16px;font-size:0.78rem;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="studio-card" style="padding:1.5rem 1.75rem;">
        <h1 class="studio-section-title" style="text-align:center;">{{ $cfg['title'] }}</h1>

        @if ($page === 'theme')
            <div class="zc-tprev" id="zc-preview">
                <span class="zc-tprev__label">Live preview</span>
                <div class="zc-tprev__nav" data-tp data-bg="menu_bg_color" data-text="menu_text_color"><b>Zenna</b><span>Home</span><span>Shop</span><span data-tp data-text="menu_hover_color">Offers</span></div>
                <div class="zc-tprev__body">
                    <div class="zc-tprev__price"><span data-tp data-text="primary_color" style="font-weight:800;font-size:1.15rem;">৳1,650</span><span data-tp data-text="discount_price_color" style="font-weight:700;">৳990</span></div>
                    <div class="zc-tprev__btns">
                        <button type="button" data-tp data-bg="primary_color" data-text="primary_text_color">Order Now</button>
                        <button type="button" data-tp data-bg="cart_bg_color" data-text="cart_text_color" data-border="cart_border_color" style="border:2px solid;">Add to Cart</button>
                    </div>
                </div>
                <div class="zc-tprev__foot" data-tp data-bg="footer_bg_color" data-text="footer_text_color">© Zenna Craft — <span data-tp data-text="footer_hover_color">Privacy</span></div>
            </div>
        @elseif ($page === 'font')
            <div class="zc-tprev" style="padding:22px;"><span class="zc-tprev__label">Live preview</span>
                <div id="zc-fprev-text">
                    <div style="font-weight:800;font-size:1.7rem;line-height:1.2;color:var(--studio-text);">Zenna Craft — Your Online Store</div>
                    <div style="font-size:1rem;margin-top:8px;color:var(--studio-muted);">The quick brown fox jumps over the lazy dog. অর্ডার করতে ক্লিক করুন। 1234567890</div>
                </div>
            </div>
        @endif

        <form class="zc-cfg" method="POST" enctype="multipart/form-data" action="{{ route('website.'.$page.'.save') }}" style="margin-top:0.5rem;">
            @csrf @method('PUT')
            @foreach ($cfg['sections'] as $section)
                <div class="zc-cfg-sec">
                    <h3>{{ $section['title'] }}</h3>
                    @if (!empty($section['desc']))<div class="zc-cfg-desc">{{ $section['desc'] }}</div>@endif
                    <div class="zc-cfg-grid">
                        @foreach ($section['fields'] as $f)
                            @php $type = $f['type'] ?? 'text'; $val = $values[$f['key']] ?? ($f['default'] ?? ''); @endphp
                            @if ($type === 'textarea')
                                <div class="zc-cfg-f full"><label>{{ $f['label'] }}</label><textarea name="{{ $f['key'] }}" rows="3" class="studio-form-control">{{ $val }}</textarea>
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @elseif ($type === 'color')
                                @php $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $val) ? $val : ($f['default'] ?? '#000000'); @endphp
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label>
                                    <div class="zc-color2">
                                        <input type="color" class="zc-color2__pick" value="{{ $hex }}" data-sync aria-label="{{ $f['label'] }}">
                                        <input type="text" name="{{ $f['key'] }}" class="studio-form-control zc-color2__hex" value="{{ $val ?: ($f['default'] ?? '') }}" data-hexinput placeholder="#000000" autocomplete="off">
                                    </div>
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @elseif ($type === 'select')
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label>
                                    <select name="{{ $f['key'] }}" class="studio-form-control" style="font-family:'{{ $val ?: '' }}',inherit;">
                                        @foreach ($f['options'] as $opt)<option value="{{ $opt }}" @selected($val === $opt) style="font-family:'{{ $opt }}',sans-serif;">{{ $opt }}</option>@endforeach
                                    </select>
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @elseif ($type === 'image')
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label>
                                    @if (!empty($images[$f['key']]))<img src="{{ $images[$f['key']] }}" alt="" class="zc-img-prev">@endif
                                    <input type="file" name="{{ $f['key'] }}" accept="image/*" class="studio-form-control">
                                    @if (!empty($f['hint']))<div style="display:inline-flex;align-items:center;gap:7px;margin-top:8px;font-size:0.76rem;font-weight:600;color:var(--studio-muted);background:var(--studio-surface-soft);border:1px dashed var(--studio-border);border-radius:9px;padding:6px 10px;">📐 Recommended: <b style="color:var(--studio-text);font-weight:800;">{{ $f['hint'] }}</b></div>@endif
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @elseif ($type === 'checkbox')
                                <div class="zc-cfg-f zc-cfg-f--checkbox"><label class="zc-cfg-check"><input type="checkbox" name="{{ $f['key'] }}" value="1" @checked(filter_var($val, FILTER_VALIDATE_BOOLEAN))> {{ $f['label'] }}</label>
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @elseif ($type === 'datetime')
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label><input type="datetime-local" name="{{ $f['key'] }}" value="{{ $val }}" class="studio-form-control">
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @else
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label><input type="text" name="{{ $f['key'] }}" value="{{ $val }}" class="studio-form-control" autocomplete="off">
                                    @if (!empty($f['help']))<div class="zc-cfg-help">{{ $f['help'] }}</div>@endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div style="text-align:center;margin-top:1.5rem;"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:3rem;">{{ $cfg['button'] ?? 'Save' }}</button></div>
        </form>
    </div>
</div>
@push('studio-scripts')
<script>
    document.querySelectorAll('.zc-color2').forEach(function(box){
        var pick = box.querySelector('[data-sync]'), hex = box.querySelector('[data-hexinput]');
        if(!pick || !hex) return;
        pick.addEventListener('input', function(){ hex.value = pick.value; });
        hex.addEventListener('input', function(){ if(/^#[0-9a-fA-F]{6}$/.test(hex.value.trim())) pick.value = hex.value.trim(); });
    });

    // ---- Live theme preview ----
    (function(){
        var form = document.querySelector('form.zc-cfg'); if(!form) return;
        function val(key){ var el = form.querySelector('[name="'+key+'"]'); return el ? el.value.trim() : ''; }
        function renderPreview(){
            document.querySelectorAll('[data-tp]').forEach(function(el){
                var bg = el.getAttribute('data-bg'), text = el.getAttribute('data-text'), border = el.getAttribute('data-border');
                if(bg && val(bg)) el.style.background = val(bg);
                if(text && val(text)) el.style.color = val(text);
                if(border && val(border)) el.style.borderColor = val(border);
            });
        }
        if(document.getElementById('zc-preview')){ form.addEventListener('input', renderPreview); renderPreview(); }

        // ---- Live font preview ----
        var fontSel = form.querySelector('[name="font_family"]'), fprev = document.getElementById('zc-fprev-text');
        if(fontSel && fprev){
            var loaded = {};
            function applyFont(){
                var f = fontSel.value;
                if(!loaded[f]){ loaded[f] = 1; var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = 'https://fonts.googleapis.com/css2?family=' + f.replace(/ /g,'+') + ':wght@400;600;800&display=swap'; document.head.appendChild(l); }
                fprev.style.fontFamily = "'" + f + "', sans-serif";
            }
            fontSel.addEventListener('change', applyFont); applyFont();
        }
    })();
</script>
@endpush
@endsection
