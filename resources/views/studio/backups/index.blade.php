@extends('layouts.studio')
@section('title', 'Backup')
@section('subtitle', 'System')
@push('studio-styles')
<style>
    .zc-bk{max-width:1000px;margin:0 auto;display:grid;gap:1.1rem;}
    .zc-bk-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.7rem;}
    .zc-bk-stat{border:1px solid var(--studio-border);border-radius:14px;background:var(--studio-surface-soft);padding:0.85rem 1rem;}
    .zc-bk-stat__l{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:var(--studio-muted);}
    .zc-bk-stat__v{font-size:1.35rem;font-weight:800;color:var(--studio-text);margin-top:4px;}
    .zc-bk-card{border:1px solid var(--studio-border);border-radius:16px;background:var(--studio-surface);padding:1.25rem 1.35rem;}
    .zc-bk-card h3{font-size:0.95rem;font-weight:800;color:var(--studio-text);margin:0 0 0.9rem;}
    .zc-bk-row{display:grid;grid-template-columns:180px 1fr;gap:1.1rem;align-items:start;margin-bottom:1rem;}
    .zc-bk-row>label{font-weight:700;font-size:0.85rem;padding-top:0.65rem;color:var(--studio-text);}
    .zc-bk-row>label small{display:block;font-weight:500;color:var(--studio-muted);font-size:0.72rem;margin-top:2px;}
    @media(max-width:640px){.zc-bk-row{grid-template-columns:1fr;gap:0.35rem;}.zc-bk-row>label{padding-top:0;}}
    .zc-bk-toggle{display:inline-flex;align-items:center;gap:0.65rem;cursor:pointer;font-weight:700;font-size:0.9rem;}
    .zc-bk-toggle input{width:2.6rem;height:1.5rem;accent-color:var(--studio-accent);cursor:pointer;}
    .zc-bk-badge{display:inline-flex;align-items:center;gap:6px;font-size:0.72rem;font-weight:800;letter-spacing:0.02em;padding:4px 11px;border-radius:999px;}
    .zc-bk-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;}
    .zc-bk-badge--ok{background:#e3f6ea;color:#1c8a4e;}
    .zc-bk-badge--warn{background:#fff5e0;color:#b7791f;}
    .zc-bk-badge--bad{background:#fdecea;color:#c0392b;}
    .zc-bk-hint{font-size:0.75rem;color:var(--studio-muted);margin-top:6px;}
    .zc-bk-tbl{width:100%;border-collapse:collapse;font-size:0.85rem;}
    .zc-bk-tbl th{text-align:left;font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:var(--studio-muted);padding:0.55rem 0.7rem;border-bottom:1px solid var(--studio-border);}
    .zc-bk-tbl td{padding:0.65rem 0.7rem;border-bottom:1px solid var(--studio-border);vertical-align:middle;}
    .zc-bk-health{display:grid;gap:0.55rem;}
    .zc-bk-health__row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.6rem 0.8rem;border-radius:11px;background:var(--studio-surface-soft);border:1px solid var(--studio-border);}
    .zc-bk-health__label{font-weight:700;font-size:0.85rem;}
    .zc-bk-health__value{font-size:0.78rem;color:var(--studio-muted);}
</style>
@endpush
@section('content')
<div class="zc-bk">
    @if (session('success'))<div class="studio-callout studio-callout--success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="studio-callout studio-callout--danger">{{ $errors->first() }}</div>@endif

    <div class="zc-bk-stats">
        <div class="zc-bk-stat"><div class="zc-bk-stat__l">Total backups</div><div class="zc-bk-stat__v">{{ $summary['total'] }}</div></div>
        <div class="zc-bk-stat"><div class="zc-bk-stat__l">Completed</div><div class="zc-bk-stat__v">{{ $summary['completed'] }}</div></div>
        <div class="zc-bk-stat"><div class="zc-bk-stat__l">Restore-ready</div><div class="zc-bk-stat__v">{{ $summary['restore_ready'] }}</div></div>
        <div class="zc-bk-stat"><div class="zc-bk-stat__l">Storage used</div><div class="zc-bk-stat__v">{{ number_format($summary['storage_used'] / 1048576, 1) }} MB</div></div>
    </div>

    <div class="zc-bk-card">
        <h3>Run a backup now</h3>
        <p class="zc-bk-hint" style="margin-bottom:0.9rem;">Backs up the database and media files, then uploads to Dropbox if configured below.</p>
        <form method="POST" action="{{ route('backups.run') }}">
            @csrf
            <button type="submit" class="studio-command-button studio-command-button--primary">Run backup now</button>
        </form>
    </div>

    <div class="zc-bk-card">
        <h3>Automatic daily backup</h3>
        <form method="POST" action="{{ route('backups.settings.update') }}">
            @csrf @method('PUT')
            <div class="zc-bk-row">
                <label>Enabled</label>
                <label class="zc-bk-toggle"><input type="checkbox" name="enabled" value="1" @checked($enabled)> Run automatically every day</label>
            </div>
            <div class="zc-bk-row">
                <label>Time <small>server time, 24h</small></label>
                <input type="time" name="schedule_time" class="studio-form-control" value="{{ $scheduleTime }}" style="max-width:12rem;">
            </div>
            <div class="zc-bk-row">
                <label>Keep locally <small>days, then deleted from the server</small></label>
                <input type="number" name="local_retention_days" min="1" max="365" class="studio-form-control" value="{{ $localRetentionDays }}" style="max-width:8rem;">
            </div>
            <div class="zc-bk-row">
                <label>Keep on Dropbox <small>days, then deleted from Dropbox</small></label>
                <input type="number" name="dropbox_retention_days" min="1" max="365" class="studio-form-control" value="{{ $dropboxRetentionDays }}" style="max-width:8rem;">
            </div>
            <div class="zc-bk-row">
                <label>Dropbox access token
                    <small>{{ $hasDropboxConfigured ? 'A token is already saved — leave blank to keep it' : 'No token saved yet' }}</small>
                </label>
                <div>
                    <input type="password" name="dropbox_token" class="studio-form-control" placeholder="{{ $hasDropboxConfigured ? '•••••••••••••••• (saved)' : 'Paste the Dropbox app access token' }}" autocomplete="off">
                    <div class="zc-bk-hint">
                        <span class="zc-bk-badge {{ $hasDropboxConfigured ? 'zc-bk-badge--ok' : 'zc-bk-badge--warn' }}">{{ $hasDropboxConfigured ? 'Dropbox connected' : 'Dropbox not connected' }}</span>
                        — from a Dropbox App Console app with the <b>files.content.write</b> scope.
                    </div>
                </div>
            </div>
            <button type="submit" class="studio-command-button studio-command-button--primary">Save settings</button>
        </form>
    </div>

    <div class="zc-bk-card">
        <h3>Health checks</h3>
        <div class="zc-bk-health">
            @foreach ($healthChecks as $check)
                <div class="zc-bk-health__row">
                    <div>
                        <div class="zc-bk-health__label">{{ $check['label'] }}</div>
                        <div class="zc-bk-health__value">{{ $check['details'] }}</div>
                    </div>
                    <span class="zc-bk-badge {{ $check['status'] === 'pass' ? 'zc-bk-badge--ok' : 'zc-bk-badge--warn' }}">{{ $check['value'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="zc-bk-card">
        <h3>Recent backups</h3>
        <div class="studio-responsive-scroll">
        <table class="zc-bk-tbl">
            <thead><tr><th>#</th><th>Type</th><th>Status</th><th>Size</th><th>Dropbox</th><th>Created</th></tr></thead>
            <tbody>
                @forelse ($history as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td>{{ ucfirst($run->backup_type) }} &middot; {{ $run->backup_scope }}</td>
                        <td>
                            <span class="zc-bk-badge {{ $run->status === 'completed' ? 'zc-bk-badge--ok' : ($run->status === 'failed' ? 'zc-bk-badge--bad' : 'zc-bk-badge--warn') }}">{{ ucfirst($run->status) }}</span>
                        </td>
                        <td>{{ $run->total_size ? number_format($run->total_size / 1048576, 1).' MB' : '—' }}</td>
                        <td>
                            @if ($run->offsite_status === 'uploaded')
                                <span class="zc-bk-badge zc-bk-badge--ok">Uploaded</span>
                            @elseif ($run->offsite_status)
                                <span class="zc-bk-badge zc-bk-badge--bad">{{ ucfirst($run->offsite_status) }}</span>
                            @else
                                <span class="zc-bk-badge zc-bk-badge--warn">—</span>
                            @endif
                        </td>
                        <td>{{ $run->created_at?->format('d M, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--studio-muted);padding:1.5rem;">No backups yet — click "Run backup now" above.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
