@extends('layouts.studio')
@section('title', 'License Verification')
@section('subtitle', 'System')
@push('studio-styles')
<style>
    .zc-lic{max-width:720px;margin:0 auto;display:grid;gap:1.1rem;}
    .zc-lic-hero{border:1px solid var(--studio-border);border-radius:16px;overflow:hidden;background:var(--studio-surface);}
    .zc-lic-hero__top{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1.15rem 1.35rem;background:var(--studio-surface-soft);border-bottom:1px solid var(--studio-border);}
    .zc-lic-hero__id{display:flex;align-items:center;gap:.85rem;}
    .zc-lic-hero__ico{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:color-mix(in srgb, var(--studio-accent) 14%, transparent);color:var(--studio-accent);flex:none;}
    .zc-lic-hero__ico svg{width:22px;height:22px;}
    .zc-lic-hero__t{font-size:1.02rem;font-weight:800;color:var(--studio-text);}
    .zc-lic-hero__s{font-size:.8rem;color:var(--studio-muted);margin-top:2px;}
    .zc-badge{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:5px 12px;border-radius:999px;flex:none;}
    .zc-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;}
    .zc-badge--active{background:#e3f6ea;color:#1c8a4e;}
    .zc-badge--grace{background:#fff5e0;color:#b7791f;}
    .zc-badge--blocked{background:#fdecea;color:#c0392b;}
    .zc-lic-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1px;background:var(--studio-border);}
    .zc-lic-cell{background:var(--studio-surface);padding:.95rem 1.35rem;}
    .zc-lic-cell__l{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--studio-muted);}
    .zc-lic-cell__v{font-size:.92rem;font-weight:700;color:var(--studio-text);margin-top:4px;word-break:break-word;}
    .zc-lic-card{border:1px solid var(--studio-border);border-radius:16px;background:var(--studio-surface);padding:1.25rem 1.35rem;}
    .zc-lic-card h3{font-size:.95rem;font-weight:800;color:var(--studio-text);margin:0 0 .35rem;}
    .zc-lic-card p.hint{font-size:.8rem;color:var(--studio-muted);line-height:1.55;margin:0 0 1rem;}
    .zc-lic-row{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;}
    .zc-lic-row .studio-form-control{flex:1 1 260px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.04em;}
    .zc-lic-blocked{border:1px solid #f3b3ac;background:#fdecea;border-radius:12px;padding:1rem 1.15rem;color:#8a2a20;font-size:.88rem;line-height:1.55;}
    .zc-lic-blocked b{display:block;font-size:.95rem;margin-bottom:.25rem;}
    .zc-toast{position:fixed;top:1.2rem;right:1.2rem;z-index:1200;display:grid;gap:.5rem;}
    .zc-toast__item{min-width:220px;max-width:340px;padding:.75rem 1rem;border-radius:10px;font-size:.85rem;font-weight:600;box-shadow:0 14px 30px -10px rgba(0,0,0,.35);animation:zcToastIn .2s ease both;}
    .zc-toast__item.ok{background:#1c8a4e;color:#fff;}
    .zc-toast__item.err{background:#c0392b;color:#fff;}
    @keyframes zcToastIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:none;}}
</style>
@endpush
@section('content')
@php
    $badgeClass = in_array($status, ['active'], true) ? 'zc-badge--active' : ($status === 'grace' ? 'zc-badge--grace' : 'zc-badge--blocked');
    $badgeLabel = match ($status) {
        'active' => 'Active', 'grace' => 'Grace period', 'expired' => 'Expired',
        'suspended' => 'Suspended', 'revoked' => 'Revoked', 'denied' => 'Denied',
        'unreachable' => 'Unverified', default => 'Not activated',
    };
@endphp
<div class="zc-lic" data-license-page data-status="{{ $status }}">
    <div class="zc-lic-hero">
        <div class="zc-lic-hero__top">
            <div class="zc-lic-hero__id">
                <span class="zc-lic-hero__ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5 4 6v5.5c0 4.6 3.2 7.5 8 9 4.8-1.5 8-4.4 8-9V6z"/><circle cx="12" cy="10.5" r="2"/><path d="M12 12.5v3"/></svg>
                </span>
                <div>
                    <div class="zc-lic-hero__t">License</div>
                    <div class="zc-lic-hero__s">This installation, on <b>{{ request()->getHost() }}</b></div>
                </div>
            </div>
            <span class="zc-badge {{ $badgeClass }}" data-status-badge>{{ $badgeLabel }}</span>
        </div>
        <div class="zc-lic-grid">
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">License key</div><div class="zc-lic-cell__v" data-masked-key>{{ $masked_key ?: '—' }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Domain</div><div class="zc-lic-cell__v" data-licensed-domain>{{ $licensed_domain ?: '—' }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Activated</div><div class="zc-lic-cell__v" data-activated-at>{{ $activated_at ? \Illuminate\Support\Carbon::parse($activated_at)->toFormattedDateString() : '—' }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Expires</div><div class="zc-lic-cell__v" data-expires-at>{{ $expires_at ? \Illuminate\Support\Carbon::parse($expires_at)->toFormattedDateString() : '—' }}</div></div>
            <div class="zc-lic-cell"><div class="zc-lic-cell__l">Days left</div><div class="zc-lic-cell__v" data-days-left>{{ $days_until_expiry ?? '—' }}</div></div>
        </div>
    </div>

    <div class="zc-lic-blocked" data-blocked-banner @if (! $blocked) hidden @endif>
        <b>This installation is currently blocked.</b>
        <span data-block-message>{{ $message ?: 'Enter a valid license key below to continue.' }}</span>
    </div>

    <div class="zc-lic-card">
        <h3>{{ $has_key ? 'Re-activate / renew' : 'Activate this installation' }}</h3>
        <p class="hint">Paste the license key you received after purchase. Activating binds it to this exact domain.</p>
        <form data-activate-form class="zc-lic-row">
            <input type="text" name="license_key" class="studio-form-control" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" required>
            <button type="submit" class="studio-command-button studio-command-button--primary" data-activate-submit>Activate</button>
        </form>
    </div>

    <div class="zc-lic-card">
        <h3>Current status</h3>
        <p class="hint" data-status-message>{{ $message ?: 'No issues to report.' }}</p>
        <button type="button" class="studio-command-button" data-recheck-btn>Recheck now</button>
    </div>
</div>

<div class="zc-toast" data-toast-host></div>
@endsection
@push('studio-scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var page = document.querySelector('[data-license-page]');
    var toastHost = document.querySelector('[data-toast-host]');

    function toast(message, ok) {
        var el = document.createElement('div');
        el.className = 'zc-toast__item ' + (ok ? 'ok' : 'err');
        el.textContent = message;
        toastHost.appendChild(el);
        setTimeout(function () { el.remove(); }, 4000);
    }

    var badgeClasses = { active: 'zc-badge--active', grace: 'zc-badge--grace' };
    var badgeLabels = { active: 'Active', grace: 'Grace period', expired: 'Expired', suspended: 'Suspended', revoked: 'Revoked', denied: 'Denied', unreachable: 'Unverified', unactivated: 'Not activated' };

    function applyState(d) {
        page.dataset.status = d.status;
        var badge = document.querySelector('[data-status-badge]');
        badge.className = 'zc-badge ' + (badgeClasses[d.status] || 'zc-badge--blocked');
        badge.textContent = badgeLabels[d.status] || d.status;
        document.querySelector('[data-masked-key]').textContent = d.masked_key || '—';
        document.querySelector('[data-licensed-domain]').textContent = d.licensed_domain || '—';
        document.querySelector('[data-activated-at]').textContent = d.activated_at ? new Date(d.activated_at).toDateString() : '—';
        document.querySelector('[data-expires-at]').textContent = d.expires_at ? new Date(d.expires_at).toDateString() : '—';
        document.querySelector('[data-days-left]').textContent = (d.days_until_expiry ?? '—');
        var statusMsg = document.querySelector('[data-status-message]');
        if (statusMsg) statusMsg.textContent = d.message || 'No issues to report.';
        var banner = document.querySelector('[data-blocked-banner]');
        if (banner) {
            banner.hidden = !d.blocked;
            var blockMsg = banner.querySelector('[data-block-message]');
            if (blockMsg) blockMsg.textContent = d.message || 'Enter a valid license key below to continue.';
        }
    }

    // ---- Activate ----
    var activateForm = document.querySelector('[data-activate-form]');
    if (activateForm) {
        activateForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.querySelector('[data-activate-submit]');
            var original = btn.textContent;
            btn.disabled = true; btn.textContent = 'Activating…';

            fetch(@json(route('license.activate')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ license_key: activateForm.license_key.value.trim() }),
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    applyState(res.d);
                    toast(res.d.message || (res.ok ? 'Activated.' : 'Activation failed.'), res.d.ok);
                    if (res.d.ok && !res.d.blocked) {
                        toast('Redirecting…', true);
                        setTimeout(function () { window.location.href = @json(route('studio.dashboard')); }, 900);
                    }
                })
                .catch(function () { toast('Could not reach the server.', false); })
                .finally(function () { btn.disabled = false; btn.textContent = original; });
        });
    }

    // ---- Recheck (30s client-side cooldown, matches the server-side throttle) ----
    var recheckBtn = document.querySelector('[data-recheck-btn]');
    if (recheckBtn) {
        recheckBtn.addEventListener('click', function () {
            var original = recheckBtn.textContent;
            recheckBtn.disabled = true; recheckBtn.textContent = 'Checking…';

            fetch(@json(route('license.recheck')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    if (res.ok) { applyState(res.d); toast('Updated.', true); }
                    else { toast(res.d.message || 'Could not recheck right now.', false); }
                })
                .catch(function () { toast('Could not reach the server.', false); })
                .finally(function () {
                    recheckBtn.textContent = original;
                    var remaining = 30;
                    var t = setInterval(function () {
                        remaining -= 1;
                        if (remaining <= 0) { clearInterval(t); recheckBtn.disabled = false; recheckBtn.textContent = original; }
                        else { recheckBtn.textContent = original + ' (' + remaining + 's)'; }
                    }, 1000);
                });
        });
    }
})();
</script>
@endpush
