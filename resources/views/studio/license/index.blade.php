@extends('layouts.studio')
@section('title', 'License & Updates')
@section('subtitle', 'System')
@push('studio-styles')
<style>
    .zc-lic{max-width:960px;margin:0 auto;display:grid;gap:1.1rem;}
    .zc-lic-hero{border:1px solid var(--studio-border);border-radius:16px;overflow:hidden;background:var(--studio-surface);}
    .zc-lic-hero__top{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1.15rem 1.35rem;background:var(--studio-surface-soft);border-bottom:1px solid var(--studio-border);}
    .zc-lic-hero__id{display:flex;align-items:center;gap:.85rem;}
    .zc-lic-hero__ico{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:var(--studio-accent-soft,#eef2ff);color:var(--studio-accent,#4338ca);flex:none;}
    .zc-lic-hero__ico svg{width:22px;height:22px;}
    .zc-lic-hero__t{font-size:1.02rem;font-weight:800;color:var(--studio-text);}
    .zc-lic-hero__s{font-size:.8rem;color:var(--studio-muted);margin-top:2px;}
    .zc-badge{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:5px 12px;border-radius:999px;}
    .zc-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;}
    .zc-badge--active{background:#e3f6ea;color:#1c8a4e;}
    .zc-badge--grace{background:#fff5e0;color:#b7791f;}
    .zc-badge--expired,.zc-badge--invalid{background:#fdecea;color:#c0392b;}
    .zc-badge--unlicensed{background:#eef2f7;color:#5b6675;}
    .zc-lic-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;background:var(--studio-border);}
    .zc-lic-cell{background:var(--studio-surface);padding:.95rem 1.35rem;}
    .zc-lic-cell__l{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--studio-muted);}
    .zc-lic-cell__v{font-size:.95rem;font-weight:700;color:var(--studio-text);margin-top:4px;word-break:break-word;}
    .zc-lic-card{border:1px solid var(--studio-border);border-radius:16px;background:var(--studio-surface);padding:1.25rem 1.35rem;}
    .zc-lic-card h3{font-size:.95rem;font-weight:800;color:var(--studio-text);margin:0 0 .35rem;}
    .zc-lic-card p.hint{font-size:.8rem;color:var(--studio-muted);line-height:1.55;margin:0 0 1rem;}
    .zc-lic-row{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;}
    .zc-lic-row .studio-form-control{flex:1 1 260px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.04em;}
    .zc-key-shown{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.95rem;font-weight:700;color:var(--studio-text);background:var(--studio-surface-soft);border:1px solid var(--studio-border);border-radius:10px;padding:.6rem .85rem;letter-spacing:.08em;}
    .zc-up{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:.9rem 0;border-top:1px solid var(--studio-border);}
    .zc-up:first-of-type{border-top:none;}
    .zc-up__v{font-size:1.35rem;font-weight:800;color:var(--studio-text);line-height:1;}
    .zc-up__v small{font-size:.72rem;font-weight:800;color:var(--studio-muted);display:block;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
    .zc-up-note{font-size:.78rem;color:var(--studio-muted);margin-top:.6rem;line-height:1.5;}
    .zc-up-log{display:none;margin-top:.85rem;border:1px solid var(--studio-border);border-radius:12px;background:var(--studio-surface-soft);padding:.85rem 1rem;font-size:.82rem;color:var(--studio-text);line-height:1.6;white-space:pre-wrap;}
    .zc-up-log.is-open{display:block;}
    .zc-lic-steps{display:grid;gap:.55rem;margin:0;padding:0;list-style:none;}
    .zc-lic-steps li{display:flex;gap:.6rem;align-items:flex-start;font-size:.82rem;color:var(--studio-muted);line-height:1.5;}
    .zc-lic-steps li b{color:var(--studio-text);font-weight:700;}
    .zc-lic-steps li svg{width:16px;height:16px;color:var(--studio-accent,#4338ca);flex:none;margin-top:2px;}
    .zc-lic-warn{border:1px solid #f3d9a0;background:#fffbf0;border-radius:12px;padding:.85rem 1.05rem;font-size:.82rem;color:#8a6d1f;line-height:1.55;}
    .zc-lic-warn code{background:rgba(0,0,0,.06);padding:1px 6px;border-radius:5px;font-size:.78rem;}
</style>
@endpush
@section('content')
@php
    $badge = match ($state['status']) {
        'active'   => ['zc-badge--active', 'Active'],
        'grace'    => ['zc-badge--grace', 'Grace period'],
        'expired'  => ['zc-badge--expired', 'Expired'],
        'invalid'  => ['zc-badge--invalid', 'Invalid'],
        default    => ['zc-badge--unlicensed', $state['configured'] ? 'Not activated' : 'Developer mode'],
    };
@endphp
<div class="zc-lic">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="studio-callout studio-callout--danger">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    {{-- Status hero --}}
    <div class="zc-lic-hero">
        <div class="zc-lic-hero__top">
            <div class="zc-lic-hero__id">
                <span class="zc-lic-hero__ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5 4 6v5.5c0 4.6 3.2 7.5 8 9 4.8-1.5 8-4.4 8-9V6z"/><circle cx="12" cy="10.5" r="2"/><path d="M12 12.5v3"/></svg>
                </span>
                <div>
                    <div class="zc-lic-hero__t">License &amp; Updates</div>
                    <div class="zc-lic-hero__s">This installation of <b>{{ $state['product'] }}</b> on <b>{{ $state['domain'] }}</b></div>
                </div>
            </div>
            <span class="zc-badge {{ $badge[0] }}">{{ $badge[1] }}</span>
        </div>
        <div class="zc-lic-grid">
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Product</div><div class="zc-lic-cell__v">{{ $state['product'] }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Domain</div><div class="zc-lic-cell__v">{{ $state['domain'] }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Expires</div><div class="zc-lic-cell__v">{{ $state['expires_at'] ?: ($state['status'] === 'unlicensed' ? '—' : 'Lifetime') }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Last checked</div><div class="zc-lic-cell__v">{{ $state['checked_at'] ?: 'Never' }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Version</div><div class="zc-lic-cell__v">{{ $state['current_version'] }}</div></div>
        </div>
    </div>

    {{-- Panel not configured yet --}}
    @unless ($state['configured'])
        <div class="zc-lic-warn">
            Licensing is <b>not enabled</b> on this installation — it's running in full-access developer mode and nothing is contacting a license server.
            To switch it on, set <code>LICENSE_SERVER_URL</code> (your panel's address) in the server <code>.env</code>, then activate a key below.
        </div>
    @endunless

    {{-- Activation --}}
    <div class="zc-lic-card">
        <h3>License key</h3>
        @if ($state['has_key'])
            <p class="hint">This installation is activated. Removing the key returns it to unlicensed (updates stop; the store keeps running).</p>
            <div class="zc-lic-row">
                <span class="zc-key-shown">{{ $state['key_masked'] }}</span>
                <form method="POST" action="{{ route('license.refresh') }}">@csrf
                    <button type="submit" class="studio-command-button">Re-validate now</button>
                </form>
                <form method="POST" action="{{ route('license.deactivate') }}" onsubmit="return confirm('Remove this license key from this installation?');">@csrf
                    <button type="submit" class="studio-command-button studio-command-button--ghost">Remove key</button>
                </form>
            </div>
        @else
            <p class="hint">Paste the license key issued from your panel to activate this domain.</p>
            <form method="POST" action="{{ route('license.activate') }}" class="zc-lic-row">@csrf
                <input type="text" name="key" class="studio-form-control" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" required @unless($state['configured']) disabled @endunless>
                <button type="submit" class="studio-command-button studio-command-button--primary" @unless($state['configured']) disabled @endunless>Activate</button>
            </form>
        @endif
    </div>

    {{-- Updates --}}
    <div class="zc-lic-card">
        <h3>Software updates</h3>
        <p class="hint">Check your panel for a newer published release. When the one-click installer ships (panel phase), updates run: <b>Backup → Download → Verify → Install → Migrate → Clear cache</b>, with automatic rollback if any step fails.</p>

        <div class="zc-up">
            <div class="zc-up__v"><small>Installed</small>{{ $state['current_version'] }}</div>
            <div class="zc-up__v" data-latest><small>Latest</small>{{ $state['latest_version'] }}</div>
            <div class="zc-lic-row">
                <button type="button" class="studio-command-button" data-check-update @unless($state['configured']) disabled @endunless>Check for updates</button>
                <button type="button" class="studio-command-button studio-command-button--primary" data-install-update disabled title="Activates once your panel is serving signed release packages">Update now</button>
            </div>
        </div>
        <div class="zc-up-log" data-update-log></div>
        <p class="zc-up-note" data-update-status></p>

        <div class="zc-up" style="margin-top:.4rem;">
            <div>
                <div style="font-size:.86rem;font-weight:800;color:var(--studio-text);">Automatic updates</div>
                <div style="font-size:.78rem;color:var(--studio-muted);margin-top:2px;">When ON, this install applies published updates on its own daily check.</div>
            </div>
            <form method="POST" action="{{ route('license.auto-update') }}">@csrf
                <input type="hidden" name="auto_update" value="0">
                <label class="zc-switch" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="auto_update" value="1" onchange="this.form.submit()" @checked($state['auto_update']) style="position:absolute;opacity:0;width:0;height:0;">
                    <span class="zc-switch__track" style="width:42px;height:23px;border-radius:999px;background:{{ $state['auto_update'] ? '#1c8a4e' : '#c9ccce' }};position:relative;flex:none;">
                        <span style="position:absolute;top:2.5px;left:{{ $state['auto_update'] ? '21px' : '2.5px' }};width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .18s;"></span>
                    </span>
                    <span style="font-size:.78rem;font-weight:800;color:{{ $state['auto_update'] ? '#1c8a4e' : 'var(--studio-muted)' }};">{{ $state['auto_update'] ? 'On' : 'Off' }}</span>
                </label>
            </form>
        </div>
    </div>

    {{-- How it works --}}
    <div class="zc-lic-card">
        <h3>How licensing works here</h3>
        <ul class="zc-lic-steps">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span><b>Activation</b> — the key binds this domain to your panel with an expiry date. Multiple domains are controlled from the panel.</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span><b>Validation</b> — re-checks daily (and when this page opens). If your panel is briefly unreachable, it trusts the last good check for <b>{{ $state['grace_days'] }} days</b> so the store never breaks.</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span><b>On expiry</b> — updates stop and a renewal notice shows, but the storefront keeps selling. Nothing hard-stops.</span></li>
        </ul>
    </div>
</div>
@endsection
@push('studio-scripts')
<script>
(function () {
    var btn = document.querySelector('[data-check-update]');
    if (!btn) return;
    var statusEl = document.querySelector('[data-update-status]');
    var logEl = document.querySelector('[data-update-log]');
    var latestEl = document.querySelector('[data-latest]');
    var installBtn = document.querySelector('[data-install-update]');

    btn.addEventListener('click', function () {
        btn.disabled = true;
        var original = btn.textContent;
        btn.textContent = 'Checking…';
        statusEl.textContent = '';
        logEl.classList.remove('is-open');

        fetch(@json(route('license.check-update')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (latestEl && d.latest) { latestEl.innerHTML = '<small>Latest</small>' + d.latest; }
                if (!d.ok) {
                    statusEl.textContent = d.message || 'Could not check right now.';
                } else if (d.has_update) {
                    statusEl.textContent = 'Update available: ' + d.current + ' → ' + d.latest + (d.mandatory ? ' (required)' : '') + '.';
                    if (d.changelog) { logEl.textContent = d.changelog; logEl.classList.add('is-open'); }
                } else {
                    statusEl.textContent = 'You are on the latest version (' + d.current + ').';
                }
            })
            .catch(function () { statusEl.textContent = 'Could not reach the update server.'; })
            .finally(function () { btn.disabled = false; btn.textContent = original; });
    });
})();
</script>
@endpush
