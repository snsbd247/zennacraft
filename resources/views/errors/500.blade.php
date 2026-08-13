{{-- Deliberately does not reference $exception anywhere, regardless of
     APP_DEBUG — a 500 page must never be the place a stack trace or
     internal detail leaks. --}}
@php
    $adminPath = trim((string) config('admin.path', 'studio'), '/');
    $isStudio = request()->is($adminPath, $adminPath.'/*');
@endphp
@include('partials.error-shell', [
    'code' => 500,
    'isStudio' => $isStudio,
    'title' => 'Something went wrong',
    'message' => "We hit an unexpected error on our end. Our team has been notified — please try again in a moment.",
])
