@extends('layouts.studio')
@section('title', 'Update System')
@section('subtitle', 'System')
@push('studio-styles')
<style>
    .zc-dp{max-width:1000px;margin:0 auto;display:grid;gap:1.1rem;}
    .zc-dp-card{border:1px solid var(--studio-border);border-radius:16px;background:var(--studio-surface);padding:1.25rem 1.35rem;}
    .zc-dp-card h3{font-size:0.95rem;font-weight:800;color:var(--studio-text);margin:0 0 0.9rem;}
    .zc-dp-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
    .zc-dp-meta{display:flex;gap:1.5rem;flex-wrap:wrap;font-size:0.85rem;color:var(--studio-muted);}
    .zc-dp-meta b{color:var(--studio-text);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
    .zc-dp-badge{display:inline-flex;align-items:center;gap:6px;font-size:0.72rem;font-weight:800;letter-spacing:0.02em;padding:4px 11px;border-radius:999px;}
    .zc-dp-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;}
    .zc-dp-badge--ok{background:#e3f6ea;color:#1c8a4e;}
    .zc-dp-badge--warn{background:#fff5e0;color:#b7791f;}
    .zc-dp-badge--bad{background:#fdecea;color:#c0392b;}
    .zc-dp-commits{list-style:none;margin:0.8rem 0 0;padding:0;display:grid;gap:0.4rem;max-height:16rem;overflow-y:auto;}
    .zc-dp-commits li{display:flex;gap:0.6rem;align-items:baseline;font-size:0.82rem;padding:0.4rem 0.6rem;border-radius:9px;background:var(--studio-surface-soft);}
    .zc-dp-commits code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.75rem;color:var(--studio-muted);flex:none;}
    .zc-dp-tbl{width:100%;border-collapse:collapse;font-size:0.85rem;}
    .zc-dp-tbl th{text-align:left;font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:var(--studio-muted);padding:0.55rem 0.7rem;border-bottom:1px solid var(--studio-border);}
    .zc-dp-tbl td{padding:0.65rem 0.7rem;border-bottom:1px solid var(--studio-border);vertical-align:middle;}
    .zc-dp-health{display:grid;gap:0.5rem;max-height:20rem;overflow-y:auto;}
    .zc-dp-health__row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.55rem 0.75rem;border-radius:11px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);}
    .zc-dp-health__label{font-weight:700;font-size:0.83rem;}
    .zc-dp-health__value{font-size:0.76rem;color:var(--studio-muted);}
    .zc-dp-hint{font-size:0.75rem;color:var(--studio-muted);margin-top:6px;}
    .zc-dp-progress{display:none;margin-top:1rem;}
    .zc-dp-progress.show{display:block;}
    .zc-dp-progress__track{height:10px;border-radius:999px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);overflow:hidden;}
    .zc-dp-progress__fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--studio-accent),color-mix(in srgb,var(--studio-accent) 60%,#1c8a4e));transition:width .4s ease;width:0%;}
    .zc-dp-progress__fill.is-failed{background:#c0392b;}
    .zc-dp-progress__meta{display:flex;justify-content:space-between;align-items:baseline;margin-top:0.5rem;font-size:0.82rem;}
    .zc-dp-progress__pct{font-weight:800;color:var(--studio-text);font-variant-numeric:tabular-nums;}
    .zc-dp-progress__msg{color:var(--studio-muted);}
    .zc-dp-progress__done{color:#1c8a4e;font-weight:700;}
    .zc-dp-progress__fail{color:#c0392b;font-weight:700;}
</style>
@endpush
@section('content')
<div class="zc-dp">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    @if (! $isGitRepository)
        <div class="studio-callout studio-callout--danger">This installation isn't a git repository — "Update Now" is unavailable here, though history and health checks below still work.</div>
    @else
        <div class="zc-dp-card">
            <div class="zc-dp-head">
                <h3 style="margin:0;">Current version</h3>
                @if ($updateCheck['error'])
                    <span class="zc-dp-badge zc-dp-badge--bad">Could not check for updates</span>
                @elseif ($updateCheck['ahead'] > 0)
                    <span class="zc-dp-badge zc-dp-badge--warn">{{ $updateCheck['ahead'] }} update{{ $updateCheck['ahead'] === 1 ? '' : 's' }} available</span>
                @else
                    <span class="zc-dp-badge zc-dp-badge--ok">Up to date</span>
                @endif
            </div>
            <div class="zc-dp-meta" style="margin-top:0.8rem;">
                <span>Branch: <b>{{ $currentBranch }}</b></span>
                <span>Commit: <b>{{ $currentCommit ?? '—' }}</b></span>
            </div>

            @if ($updateCheck['error'])
                <p class="zc-dp-hint" style="color:#c0392b;">{{ $updateCheck['error'] }}</p>
            @elseif ($updateCheck['ahead'] > 0)
                <ul class="zc-dp-commits">
                    @foreach ($updateCheck['commits'] as $commit)
                        <li><code>{{ $commit['hash'] }}</code> <span>{{ $commit['message'] }}</span></li>
                    @endforeach
                </ul>
                <form data-update-form style="margin-top:1rem;">
                    @csrf
                    <button type="submit" class="studio-command-button studio-command-button--primary" data-update-submit>Update Now</button>
                </form>
                <p class="zc-dp-hint">Backs up the database, pulls from GitHub, runs <code>composer install</code> and migrations, then rebuilds caches — all in the background.</p>

                <div class="zc-dp-progress" data-update-progress>
                    <div class="zc-dp-progress__track"><div class="zc-dp-progress__fill" data-progress-fill></div></div>
                    <div class="zc-dp-progress__meta">
                        <span class="zc-dp-progress__msg" data-progress-msg>Starting…</span>
                        <span class="zc-dp-progress__pct" data-progress-pct>0%</span>
                    </div>
                </div>
            @else
                <p class="zc-dp-hint">Nothing to deploy right now.</p>
            @endif
        </div>
    @endif

    <div class="zc-dp-card">
        <h3>Production readiness ({{ $healthSummary['passing'] }}/{{ $healthSummary['total'] }})</h3>
        <div class="zc-dp-health">
            @foreach ($healthChecks as $check)
                <div class="zc-dp-health__row">
                    <div>
                        <div class="zc-dp-health__label">{{ $check['label'] }}</div>
                        <div class="zc-dp-health__value">{{ $check['details'] }}</div>
                    </div>
                    <span class="zc-dp-badge {{ $check['status'] === 'pass' ? 'zc-dp-badge--ok' : 'zc-dp-badge--warn' }}">{{ $check['value'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="zc-dp-card">
        <div class="zc-dp-head" style="margin-bottom:0.9rem;">
            <h3 style="margin:0;">Deployment history</h3>
            @if ($history->total() > 0)
                <form method="POST" action="{{ route('deployment.history.clear') }}" onsubmit="return confirm('Clear all finished deployment history? This cannot be undone.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="studio-command-button" style="font-size:0.78rem;padding:0.4rem 0.85rem;">Clear History</button>
                </form>
            @endif
        </div>
        <div class="studio-responsive-scroll">
        <table class="zc-dp-tbl">
            <thead><tr><th>#</th><th>Status</th><th>Commits</th><th>Migrations</th><th>By</th><th>When</th></tr></thead>
            <tbody>
                @forelse ($history as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td><span class="zc-dp-badge {{ $run->status === 'completed' ? 'zc-dp-badge--ok' : ($run->status === 'failed' ? 'zc-dp-badge--bad' : 'zc-dp-badge--warn') }}">{{ ucfirst($run->status) }}</span></td>
                        <td>{{ $run->from_commit }} &rarr; {{ $run->to_commit ?? '…' }} ({{ $run->commits_pulled }})</td>
                        <td>{{ $run->migrations_ran ? 'Yes' : 'No' }}</td>
                        <td>{{ $run->createdBy->name ?? 'System' }}</td>
                        <td>{{ $run->created_at?->format('d M, H:i') }}</td>
                    </tr>
                    @if ($run->error_message)
                        <tr><td colspan="6" style="color:#c0392b;font-size:0.78rem;padding-top:0;">{{ $run->error_message }}</td></tr>
                    @endif
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--studio-muted);padding:1.5rem;">No deployments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

@push('studio-scripts')
<script>
(function () {
    var form = document.querySelector('[data-update-form]');
    if (!form) return;

    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var submitBtn = document.querySelector('[data-update-submit]');
    var progress = document.querySelector('[data-update-progress]');
    var fill = document.querySelector('[data-progress-fill]');
    var pct = document.querySelector('[data-progress-pct]');
    var msg = document.querySelector('[data-progress-msg]');
    var poller = null;

    function setBar(percent, failed) {
        fill.style.width = Math.max(0, Math.min(100, percent)) + '%';
        fill.classList.toggle('is-failed', !!failed);
        pct.textContent = percent + '%';
    }

    function poll(runId) {
        fetch('{{ url("studio/deployment") }}/' + runId + '/status', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                setBar(d.progress || 0, d.status === 'failed');
                msg.textContent = d.message || '…';

                if (d.status === 'completed') {
                    clearInterval(poller);
                    setBar(100, false);
                    msg.innerHTML = '<span class="zc-dp-progress__done">Update complete — reloading…</span>';
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else if (d.status === 'failed') {
                    clearInterval(poller);
                    msg.innerHTML = '<span class="zc-dp-progress__fail">Update failed: ' + (d.error_message || 'unknown error') + '</span>';
                    submitBtn.disabled = false;
                }
            })
            .catch(function () { /* transient network hiccup — next tick retries */ });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!confirm('Pull and deploy the latest code now? This backs up the database, runs migrations, and rebuilds caches.')) return;

        submitBtn.disabled = true;
        progress.classList.add('show');
        setBar(0, false);
        msg.textContent = 'Starting…';

        fetch('{{ route('deployment.run') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.d.run_id) {
                    msg.innerHTML = '<span class="zc-dp-progress__fail">' + (res.d.message || 'Could not start the update.') + '</span>';
                    submitBtn.disabled = false;
                    return;
                }
                poller = setInterval(function () { poll(res.d.run_id); }, 2000);
                poll(res.d.run_id);
            })
            .catch(function () {
                msg.innerHTML = '<span class="zc-dp-progress__fail">Could not reach the server.</span>';
                submitBtn.disabled = false;
            });
    });
})();
</script>
@endpush
@endsection
