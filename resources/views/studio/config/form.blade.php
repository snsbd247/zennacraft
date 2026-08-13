@extends('layouts.studio')
@section('title', $cfg['title'])
@section('subtitle', 'Setting & Configuration')
@push('studio-styles')@include('studio.products.partials._submodule-styles')
<style>
    .zc-cfg{max-width:1080px;margin:0 auto;}

    .zc-cfg-hero{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding-bottom:.25rem;}
    .zc-cfg-hero h1{font-size:1.35rem;font-weight:800;letter-spacing:-.015em;margin:0;}
    .zc-cfg-hero p{font-size:.82rem;color:var(--studio-muted);margin:.3rem 0 0;}

    .zc-cfg-sec{margin-top:1.1rem;background:var(--studio-surface);border:1px solid var(--studio-border);border-radius:18px;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.05),0 22px 48px -38px rgba(15,23,42,.30);}
    .zc-cfg-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.95rem 1.25rem;border-bottom:1px solid var(--studio-border);}
    .zc-cfg-head__t{display:flex;align-items:center;gap:.7rem;min-width:0;}
    .zc-cfg-avatar{flex:none;width:34px;height:34px;border-radius:10px;display:grid;place-items:center;font-size:.78rem;font-weight:800;letter-spacing:-.02em;background:color-mix(in srgb, var(--studio-accent) 14%, transparent);color:var(--studio-accent);}
    .zc-cfg-head span.zc-cfg-title{font-size:.92rem;font-weight:800;letter-spacing:.01em;color:var(--studio-text);}

    .zc-switch{display:inline-flex;align-items:center;gap:9px;cursor:pointer;user-select:none;flex:none;}
    .zc-switch input{position:absolute;opacity:0;width:0;height:0;}
    .zc-switch__track{width:44px;height:25px;border-radius:999px;background:#d5d9dd;position:relative;transition:background .18s ease;flex:none;box-shadow:inset 0 1px 2px rgba(0,0,0,.08);}
    .zc-switch__dot{position:absolute;top:2.5px;left:2.5px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform .18s cubic-bezier(.2,.7,.3,1);}
    .zc-switch input:checked + .zc-switch__track{background:var(--studio-accent);}
    .zc-switch input:checked + .zc-switch__track .zc-switch__dot{transform:translateX(19px);}
    .zc-switch__label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--studio-muted);min-width:52px;}
    .zc-switch input:checked ~ .zc-switch__label{color:var(--studio-accent);}

    .zc-cfg-desc{display:flex;gap:.55rem;align-items:flex-start;padding:.85rem 1.25rem 0;font-size:.8rem;line-height:1.45;color:var(--studio-muted);}
    .zc-cfg-desc svg{flex:none;width:15px;height:15px;margin-top:.15rem;opacity:.7;}

    .zc-cfg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.15rem 1.4rem;padding:1.25rem;}
    .zc-cfg-f{min-width:0;}
    .zc-cfg-f label{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--studio-muted);margin-bottom:.45rem;}
    .zc-cfg-f.full{grid-column:1/-1;}
    .zc-cfg-f .studio-form-control{width:100%;}
    .zc-cfg-check{display:inline-flex;align-items:center;gap:.6rem;font-weight:700;font-size:.85rem;cursor:pointer;padding-top:.4rem;text-transform:none;letter-spacing:normal;color:var(--studio-text);}
    .zc-cfg-check input{width:1.15rem;height:1.15rem;accent-color:var(--studio-accent);}

    .zc-cfg-secret{position:relative;}
    .zc-cfg-secret .studio-form-control{padding-right:2.5rem;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.02em;}
    .zc-cfg-secret-toggle{position:absolute;top:50%;right:.4rem;transform:translateY(-50%);width:28px;height:28px;border-radius:8px;border:none;background:transparent;color:var(--studio-muted);display:grid;place-items:center;cursor:pointer;}
    .zc-cfg-secret-toggle:hover{background:var(--studio-surface-soft);color:var(--studio-text);}
    .zc-cfg-secret-toggle svg{width:16px;height:16px;}
    .zc-cfg-secret-toggle .zc-eye-off{display:none;}
    .zc-cfg-secret.is-visible .zc-eye{display:none;}
    .zc-cfg-secret.is-visible .zc-eye-off{display:block;}

    .zc-cfg-submit{display:flex;justify-content:center;margin-top:1.6rem;}
</style>@endpush
@section('content')
<div class="space-y-4">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="zc-cfg">
        <div class="zc-cfg-hero">
            <div>
                <h1>{{ $cfg['title'] }}</h1>
                <p>Toggle a provider on, fill in its credentials, then Submit — the toggle is what the rest of the system reads, so turning it off here turns it off everywhere.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('config.'.$page.'.save') }}">
            @csrf @method('PUT')
            @foreach ($cfg['sections'] as $section)
                <div class="zc-cfg-sec">
                    <div class="zc-cfg-head">
                        <div class="zc-cfg-head__t">
                            <span class="zc-cfg-avatar">{{ Illuminate\Support\Str::substr($section['title'], 0, 1) }}</span>
                            <span class="zc-cfg-title">{{ $section['title'] }}</span>
                        </div>
                        @if (!empty($section['enable']))
                            <label class="zc-switch">
                                <input type="checkbox" name="{{ $section['enable'] }}" value="1" @checked(filter_var($values[$section['enable']] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                <span class="zc-switch__track"><span class="zc-switch__dot"></span></span>
                                <span class="zc-switch__label">{{ filter_var($values[$section['enable']] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Disabled' }}</span>
                            </label>
                        @endif
                    </div>
                    @if (!empty($section['desc']))
                        <div class="zc-cfg-desc">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/></svg>
                            <span>{{ $section['desc'] }}</span>
                        </div>
                    @endif
                    <div class="zc-cfg-grid">
                        @foreach ($section['fields'] as $f)
                            @php $type = $f['type'] ?? 'text'; $val = $values[$f['key']] ?? ''; @endphp
                            @if ($type === 'checkbox')
                                <div class="zc-cfg-f"><label>&nbsp;</label><label class="zc-cfg-check"><input type="checkbox" name="{{ $f['key'] }}" value="1" @checked(filter_var($val, FILTER_VALIDATE_BOOLEAN))> {{ $f['label'] }}</label></div>
                            @elseif ($type === 'textarea')
                                <div class="zc-cfg-f full"><label>{{ $f['label'] }}</label><textarea name="{{ $f['key'] }}" rows="4" class="studio-form-control">{{ $val }}</textarea></div>
                            @elseif ($type === 'select')
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label>
                                    <select name="{{ $f['key'] }}" class="studio-form-control">
                                        @foreach ($f['options'] as $ov => $ol)<option value="{{ $ov }}" @selected((string) $val === (string) $ov)>{{ $ol }}</option>@endforeach
                                    </select>
                                </div>
                            @elseif ($type === 'secret')
                                <div class="zc-cfg-f">
                                    <label>{{ $f['label'] }}</label>
                                    <div class="zc-cfg-secret" data-secret-field>
                                        <input type="password" name="{{ $f['key'] }}" value="{{ $val }}" class="studio-form-control" autocomplete="off" spellcheck="false">
                                        <button type="button" class="zc-cfg-secret-toggle" data-secret-toggle title="Show/hide">
                                            <svg class="zc-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            <svg class="zc-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.24 4.24"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 10.5 8 10.5 8a13.4 13.4 0 0 1-2.16 3.19M6.6 6.6C3.4 8.6 1.5 12 1.5 12s3.5 8 10.5 8a10.7 10.7 0 0 0 4.24-.86"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="zc-cfg-f"><label>{{ $f['label'] }}</label><input type="{{ $type === 'number' ? 'number' : 'text' }}" @if($type==='number') step="0.01" min="0" @endif name="{{ $f['key'] }}" value="{{ $val }}" class="studio-form-control" autocomplete="off"></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="zc-cfg-submit"><button type="submit" class="studio-command-button studio-command-button--primary" style="padding-inline:3rem;">Save changes</button></div>
        </form>
    </div>

    @if ($page === 'sms')
        <div class="zc-cfg-sec zc-cfg" style="margin-top:1.4rem;">
            <div class="zc-cfg-head"><div class="zc-cfg-head__t"><span class="zc-cfg-avatar">T</span><span class="zc-cfg-title">Send a test SMS</span></div></div>
            <div class="zc-cfg-desc" style="padding-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/></svg>
                <span>Save your provider &amp; API key above first, then send a live test to confirm it works. The provider's exact response is shown below — so if delivery fails (wrong key, no balance, etc.) you'll see why.</span>
            </div>
            <div class="zc-cfg-grid" style="padding-top:1rem;">
                <form data-ajax-form method="POST" action="{{ route('config.sms.test') }}" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;grid-column:1/-1;">
                    @csrf
                    <div class="zc-cfg-f" style="flex:1 1 240px;">
                        <label>Test phone number</label>
                        <input type="text" name="phone" class="studio-form-control" placeholder="017XXXXXXXX" autocomplete="off" required>
                    </div>
                    <button type="submit" data-ajax-submit class="studio-command-button studio-command-button--primary">Send test SMS</button>
                </form>
            </div>
            <div data-region="sms-test-result" style="padding:0 1.25rem 1.25rem;"></div>
        </div>
    @endif
</div>
@push('studio-scripts')
<script>
    document.querySelectorAll('.zc-switch input').forEach(function(cb){
        cb.addEventListener('change', function(){
            var lbl = cb.parentElement.querySelector('.zc-switch__label');
            if (lbl) lbl.textContent = cb.checked ? 'Enabled' : 'Disabled';
        });
    });
    document.querySelectorAll('[data-secret-toggle]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var wrap = btn.closest('[data-secret-field]');
            var input = wrap.querySelector('input');
            var visible = wrap.classList.toggle('is-visible');
            input.type = visible ? 'text' : 'password';
        });
    });
</script>
@endpush
@endsection
