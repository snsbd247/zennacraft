<?php

namespace App\Modules\Backup\Http\Controllers;

use App\Modules\Backup\Jobs\RunBackupJob;
use App\Modules\Backup\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function __construct(private BackupService $service) {}

    public function index(): View
    {
        return view('studio.backups.index', [
            'summary' => $this->service->summary(),
            'history' => $this->service->history(10),
            'healthChecks' => $this->service->healthChecks(),
            'enabled' => $this->service->isScheduleEnabled(),
            'scheduleTime' => $this->service->scheduleTime(),
            'localRetentionDays' => $this->service->localRetentionDays(),
            'dropboxRetentionDays' => $this->service->dropboxRetentionDays(),
            'hasDropboxConfigured' => $this->service->hasDropboxConfigured(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'local_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'dropbox_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'dropbox_token' => ['nullable', 'string'],
        ]);

        $this->service->updateSettings($data);

        return back()->with('success', 'Backup settings saved.');
    }

    /**
     * Dispatches on the queue rather than running inline — a full backup
     * (database dump + media zip) can comfortably outlast a web request's
     * connection timeout on shared hosting, which previously left the
     * BackupRun stuck at status=running forever with no way to know it had
     * died. The queue worker (already polled every minute via cron) has no
     * such limit.
     */
    public function runNow(Request $request): JsonResponse|RedirectResponse
    {
        $scopes = $request->filled('scopes') ? (array) $request->input('scopes') : ['database', 'files'];

        RunBackupJob::dispatch($scopes, $request->user('staff')?->id);

        $message = 'Backup started — it runs in the background and usually finishes within a minute or two. Refresh this page to see it in the history below.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
