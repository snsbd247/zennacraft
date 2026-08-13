<?php

namespace App\Modules\RBAC\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $staff = auth()->guard('staff')->user();

        if (!$staff || !$staff->canAccess($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
