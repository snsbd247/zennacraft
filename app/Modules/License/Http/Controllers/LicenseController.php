<?php

namespace App\Modules\License\Http\Controllers;

use App\Modules\License\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Any authenticated staff member can reach these — not gated behind a
 * settings permission, and (see routes/web.php) registered outside
 * EnsureLicenseIsValid — a blocked installation must still let whoever is
 * logged in fix it, not just an admin with the right permission.
 */
class LicenseController extends Controller
{
    public function __construct(private LicenseService $license) {}

    public function verification(): View
    {
        return view('studio.license-verification', $this->pageData());
    }

    public function status(): JsonResponse
    {
        return response()->json($this->pageData());
    }

    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate(['license_key' => ['required', 'string', 'max:191']]);

        $result = $this->license->activate($validated['license_key']);

        return response()->json(array_merge($result, $this->pageData()), $result['ok'] ? 200 : 422);
    }

    public function recheck(): JsonResponse
    {
        $result = $this->license->verify(force: true);

        return response()->json(array_merge($result, $this->pageData()));
    }

    private function pageData(): array
    {
        $status = $this->license->getEffectiveStatus();

        return [
            'status' => $status['status'],
            'blocked' => $status['blocked'],
            'message' => $status['message'],
            'expires_at' => $status['expires_at'],
            'has_key' => $status['has_key'],
            'masked_key' => $this->license->maskedKey(),
            'days_until_expiry' => $this->license->daysUntilExpiry(),
        ];
    }
}
