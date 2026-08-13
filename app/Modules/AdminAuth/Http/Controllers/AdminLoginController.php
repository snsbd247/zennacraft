<?php

namespace App\Modules\AdminAuth\Http\Controllers;

use App\Modules\AdminAuth\Http\Requests\AdminLoginRequest;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;

class AdminLoginController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    public function showLogin(): View
    {
        return view('studio.auth.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $key = $this->throttleKey($request);
        $maxAttempts = 5;
        $decaySeconds = 900;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            return back()->withErrors([
                'email' => 'Too many login attempts. Please try again in 15 minutes.',
            ]);
        }

        $credentials = array_merge($request->only('email', 'password'), ['status' => 'active']);

        if (Auth::guard('staff')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            $staff = Auth::guard('staff')->user();

            $this->logAudit(
                'auth',
                'login',
                'auth',
                'Staff member logged in',
                ['staff_id' => $staff?->id, 'name' => $staff?->name]
            );

            return redirect()->intended('/'.config('admin.path'));
        }

        RateLimiter::hit($key, $decaySeconds);

        $loginIdentifierHash = $this->privacyHash(Str::lower(trim((string) $request->input('email'))));

        $this->logAudit(
            'auth',
            'login_failed',
            'auth',
            'Failed login attempt',
            ['login_identifier_hash' => $loginIdentifierHash]
        );
        logger()->warning('Admin login failed', [
            'login_identifier_hash' => $loginIdentifierHash,
            'ip_hash' => $this->privacyHash($request->ip()),
            'user_agent_hash' => $this->privacyHash($request->userAgent()),
        ]);

        return back()->withErrors([
            'email' => 'The provided credentials are incorrect or the account is inactive.',
        ])->onlyInput('email');
    }

    public function logout(): RedirectResponse
    {
        $staff = Auth::guard('staff')->user();

        if ($staff) {
            $this->logAudit(
                'auth',
                'logout',
                'auth',
                'Staff member logged out',
                ['staff_id' => $staff->id, 'name' => $staff->name]
            );
        }

        Auth::guard('staff')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/'.config('admin.path').'/login');
    }

    protected function throttleKey(AdminLoginRequest $request): string
    {
        return 'staff-login:'.$this->privacyHash(
            Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()
        );
    }

    protected function privacyHash(?string $value): string
    {
        return hash_hmac('sha256', trim((string) $value), (string) config('app.key'));
    }

    protected function logAudit(
        string $eventType,
        string $eventAction,
        string $module,
        string $description,
        array $metadata = []
    ): void {
        try {
            $this->auditService->log($eventType, $eventAction, $module, $description, $metadata);
        } catch (Throwable $exception) {
            logger()->warning('Audit logging failed', [
                'event_type' => $eventType,
                'event_action' => $eventAction,
                'module' => $module,
                'description' => $description,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
