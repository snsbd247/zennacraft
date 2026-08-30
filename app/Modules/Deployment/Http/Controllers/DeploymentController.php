<?php

namespace App\Modules\Deployment\Http\Controllers;

use App\Modules\Deployment\Jobs\RunUpdateJob;
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

    public function runNow(Request $request): JsonResponse|RedirectResponse
    {
        RunUpdateJob::dispatch($request->user('staff')?->id);

        $message = 'Update started — it runs in the background and usually finishes within a couple of minutes. Refresh this page to see progress in the history below.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
