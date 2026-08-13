<?php

namespace App\Modules\License\Http\Controllers;

use App\Modules\License\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function __construct(private LicenseService $license) {}

    public function index(): View
    {
        // Best-effort daily re-validation when an admin opens the page. It never
        // blocks (short timeout, failures keep the cached status via the grace
        // window), and it's a no-op when no panel is configured / no key is set.
        $checkedAt = $this->license->checkedAt();
        if ($this->license->key() && (! $checkedAt || $checkedAt->copy()->addDay()->isPast())) {
            $this->license->refresh();
        }

        return view('studio.license.index', ['state' => $this->license->state()]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:191']]);
        $result = $this->license->activate(trim($data['key']));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function refresh(): RedirectResponse
    {
        $result = $this->license->refresh();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function deactivate(): RedirectResponse
    {
        $this->license->deactivate();

        return back()->with('success', 'License removed from this installation.');
    }

    public function checkUpdate(): JsonResponse
    {
        return response()->json($this->license->checkUpdate());
    }

    public function autoUpdate(Request $request): RedirectResponse
    {
        $this->license->setAutoUpdate($request->boolean('auto_update'));

        return back()->with('success', 'Auto-update preference saved.');
    }
}
