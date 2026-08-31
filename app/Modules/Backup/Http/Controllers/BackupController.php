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
            'dropboxAppKey' => $this->service->dropboxAppKey() ?? '',
            'hasDropboxAppSecret' => $this->service->dropboxAppSecret() !== null,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'local_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'dropbox_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'dropbox_app_key' => ['nullable', 'string', 'max:255'],
            'dropbox_app_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->updateSettings($data);

        return back()->with('success', 'Backup settings saved.');
    }

    /** Step 1 of "Connect Dropbox": send the browser to Dropbox's consent screen. */
    public function dropboxConnect(): RedirectResponse
    {
        $appKey = $this->service->dropboxAppKey();

        if (! $appKey) {
            return back()->withErrors(['dropbox' => 'Save the Dropbox App key and App secret first, then click Connect Dropbox.']);
        }

        $params = http_build_query([
            'client_id' => $appKey,
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'redirect_uri' => route('backups.dropbox.callback'),
        ]);

        return redirect('https://www.dropbox.com/oauth2/authorize?'.$params);
    }

    /** Step 3: Dropbox redirects back here with a code — trade it for a refresh token. */
    public function dropboxCallback(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()->route('backups.index')->withErrors(['dropbox' => 'Dropbox authorization was cancelled or failed.']);
        }

        try {
            $this->service->connectDropbox($code, route('backups.dropbox.callback'));
        } catch (\Throwable $exception) {
            return redirect()->route('backups.index')->withErrors(['dropbox' => 'Could not complete Dropbox connection: '.$exception->getMessage()]);
        }

        return redirect()->route('backups.index')->with('success', 'Dropbox connected — backups will now upload automatically, and the connection renews itself.');
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
