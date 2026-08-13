<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return $next($request);
    }
}
