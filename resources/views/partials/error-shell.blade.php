{{--
    Shared shell for every custom error page (404/419/429/500). Deliberately
    NOT extending layouts.app or layouts.studio: both compute theme/settings/
    cart/auth state unconditionally in their @php blocks, which is exactly
    the kind of dependency an error page — especially a 500, which fires
    precisely when something in the app's own state may already be broken —
    should not inherit. This only includes the stateless CSS design-system
    partials (pure <style> blocks, no service calls) so the page still looks
    on-brand without risking a second failure while rendering the first one.

    Deliberately NOT at resources/views/errors/minimal.blade.php — Laravel
    reserves that exact path as the override slot for its own internal
    default error layout (vendor/laravel/framework/.../Exceptions/views/
    minimal.blade.php), which every Laravel-provided fallback view (401,
    402, 403, 503, ...) extends via errors::minimal. Every status code this
    app hasn't built a dedicated errors/{code}.blade.php for (e.g. 403)
    still falls through to Laravel's own errors::403, which needs its own
    minimal layout's variables — putting an incompatible file at that path
    broke every one of those codes with "Undefined variable" errors,
    caught by tests/Feature/Rbac/StaffPermissionDenialTest.php (expects
    403, got 500) before this file ever shipped.

    Expects: $code (int), $title (string), $message (string), $isStudio (bool)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — {{ $isStudio ? 'Zenna Studio' : 'Zenna Craft' }}</title>
    @if ($isStudio)
        @include('studio.partials.design-system')
    @else
        {{-- Self-contained styles: the storefront design-system was removed
             pending a new UI, so error pages carry their own minimal look. --}}
        <style>
            :root{--zc-bg:#f3ece0;--zc-muted:#5a4f45;--zc-ink:#211a14;--zc-madder:#a63a2e;--zc-line:#d8ccb9;}
            *{box-sizing:border-box;}
            body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;color:var(--zc-ink);}
            .zc-card-lg,.zc-ds-card{background:#fff;border:1px solid var(--zc-line);box-shadow:0 24px 48px -24px rgba(33,26,20,.28);}
            .zc-btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 24px;border-radius:2px;font-weight:600;text-decoration:none;border:1px solid var(--zc-ink);color:var(--zc-ink);}
            .zc-btn-primary{background:var(--zc-madder);color:var(--zc-bg);border-color:var(--zc-madder);}
        </style>
    @endif
</head>
<body class="{{ $isStudio ? 'studio-shell' : '' }}" style="min-height:100vh;display:flex;align-items:center;justify-content:center;{{ $isStudio ? 'background:var(--studio-bg,#f8fafc);' : 'background:var(--zc-bg,#f8fafc);' }}">
    <a href="#error-content" class="sr-only focus:not-sr-only" style="position:absolute;top:0.5rem;left:0.5rem;z-index:50;background:#fff;padding:0.5rem 1rem;border-radius:0.5rem;">Skip to content</a>

    <main id="error-content" style="max-width:32rem;width:100%;padding:2rem;text-align:center;">
        <div class="{{ $isStudio ? 'studio-card' : 'zc-card-lg zc-ds-card' }}" style="padding:3rem 2rem;border-radius:1.5rem;">
            <p style="font-weight:900;letter-spacing:0.18em;text-transform:uppercase;font-size:0.75rem;color:{{ $isStudio ? 'var(--studio-muted,#64748b)' : 'var(--zc-muted,#64748b)' }};">
                Error {{ $code }}
            </p>
            <h1 style="margin-top:0.75rem;font-size:2rem;font-weight:900;letter-spacing:-0.01em;">
                {{ $title }}
            </h1>
            <p style="margin-top:1rem;font-size:1rem;line-height:1.6;color:{{ $isStudio ? 'var(--studio-muted,#64748b)' : 'var(--zc-muted,#64748b)' }};">
                {{ $message }}
            </p>

            <div style="margin-top:2rem;display:flex;flex-wrap:wrap;gap:0.75rem;justify-content:center;">
                @if ($isStudio)
                    <a href="{{ url(config('admin.path')) }}" class="studio-command-button studio-command-button--primary">Go to Studio</a>
                @else
                    <a href="{{ route('storefront.home') }}" class="zc-btn zc-btn-primary">Back to shop</a>
                    <a href="{{ route('tracking.form') }}" class="zc-btn">Track my order</a>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
