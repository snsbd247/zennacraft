@php
    $adminPath = trim((string) config('admin.path', 'studio'), '/');
    $isStudio = request()->is($adminPath, $adminPath.'/*');
@endphp
@include('partials.error-shell', [
    'code' => 419,
    'isStudio' => $isStudio,
    'title' => 'Your session expired',
    'message' => 'For your security, this page expired after a period of inactivity. Please go back and try again.',
])
