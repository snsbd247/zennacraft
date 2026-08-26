<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\OperationalStanding;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $guard = Auth::guard('staff');

        if (! $guard->check()) {
            return redirect('/'.config('admin.path').'/login');
        }

        $user = $guard->user();

        if ($user?->status !== 'active') {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/'.config('admin.path').'/login');
        }

        // Every authenticated Studio request already funnels through here,
        // which makes it a natural second checkpoint alongside
        // EnsureLicenseIsValid — see OperationalStanding's docblock. The
        // license-verification page + its own endpoints stay reachable
        // (skipped here by name) — a blocked install must still be able to
        // open that one page and submit a new key.
        if (! $request->routeIs('license.verification', 'license.status', 'license.activate', 'license.recheck')) {
            try {
                OperationalStanding::assertActive();
            } catch (HttpException $exception) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'LICENSE_BLOCKED',
                        'message' => $exception->getMessage(),
                    ], 403);
                }

                return redirect()->route('license.verification');
            }
        }

        return $next($request);
    }
}
