<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfStaffAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('staff')->check()) {
            return redirect('/'.config('admin.path'));
        }

        return $next($request);
    }
}
