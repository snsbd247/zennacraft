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
                <form method="POST" action="{{ route('deployment.run') }}" style="margin-top:1rem;" onsubmit="return confirm('Pull and deploy the latest code now? This backs up the database, runs migrations, and rebuilds caches.');">
                    @csrf
                    <button type="submit" class="studio-command-button studio-command-button--primary">Update Now</button>
                </form>
                <p class="zc-dp-hint">Backs up the database, pulls from GitHub, runs <code>composer install</code> and migrations, then rebuilds caches — all in the background.</p>
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
        <h3>Deployment history</h3>
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
@endsection
