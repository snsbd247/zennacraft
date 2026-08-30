<?php

namespace App\Modules\Deployment\Http\Controllers;

use App\Modules\Deployment\Jobs\RunUpdateJob;
use App\Modules\Deployment\Models\DeploymentRun;
use App\Modules\Deployment\Services\DeploymentHealthService;
use App\Modules\Deployment\Services\UpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DeploymentController extends Controller
{
    public function __construct(protected UpdateService $updateService, protected DeploymentHealthService $healthService) {}

    public function index(): View
    {
        return view('studio.deployment.index', [
            'isGitRepository' => $this->updateService->isGitRepository(),
            'currentCommit' => $this->updateService->currentCommit(),
            'currentBranch' => $this->updateService->currentBranch(),
            'updateCheck' => $this->updateService->checkForUpdates(),
            'history' => $this->updateService->history(10),
            'healthSummary' => $this->healthService->summary(),
            'healthChecks' => $this->healthService->flattenedChecks(),
        ]);
    }

    public function checkUpdates(): JsonResponse
    {
        return response()->json($this->updateService->checkForUpdates());
    }

    /**
     * The DeploymentRun row is created here — synchronously, a fast DB
     * insert — rather than inside the queued job, so the page has an id to
     * poll immediately. The queue worker only picks the job up on its next
     * cron tick (up to ~60s on this host), and without a pending row to
     * point at, the progress bar would have nothing to show for that
     * entire window.
     */
    public function runNow(Request $request): JsonResponse|RedirectResponse
    {
        $deployment = $this->updateService->createPendingRun($request->user('staff')?->id);

        RunUpdateJob::dispatch($deployment);

        $message = 'Update started — it runs in the background and usually finishes within a couple of minutes.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'run_id' => $deployment->id, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /** Polled by the progress bar on the Studio page while a run is in flight. */
    public function status(DeploymentRun $run): JsonResponse
    {
        $latestLog = $run->logs()->first();

        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'progress' => $run->progress,
            'message' => $latestLog?->message,
            'to_commit' => $run->to_commit,
            'commits_pulled' => $run->commits_pulled,
            'error_message' => $run->error_message,
        ]);
    }
}
