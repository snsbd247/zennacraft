<?php

namespace App\Http\Middleware;

use App\Modules\License\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;

/**
 * Primary license gate — applied to the whole Studio-authenticated route
 * group (see routes/web.php). The /license-verification page and its
 * status/activate/recheck endpoints are registered OUTSIDE this middleware
 * so a blocked installation can still reach them.
 *
 * This is one of several independent enforcement points — see
 * App\Modules\Shared\Support\OperationalStanding for why this file being
 * deleted or commented out on its own does not, by itself, unlock the app.
 */
class EnsureLicenseIsValid
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next)
    {
        // Must stay reachable even while blocked, so staff can submit a
        // new/renewed key without ever being locked out of the one page
        // that fixes the block.
        if ($request->routeIs('license.verification', 'license.status', 'license.activate', 'license.recheck')) {
            return $next($request);
        }

        $result = $this->license->getEffectiveStatus();

        if (! $result['blocked']) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'error_code' => 'LICENSE_BLOCKED',
                'message' => $result['message'],
            ], 403);
        }

        return redirect()->route('license.verification');
    }
}
