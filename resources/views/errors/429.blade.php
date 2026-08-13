@php
    $adminPath = trim((string) config('admin.path', 'studio'), '/');
    $isStudio = request()->is($adminPath, $adminPath.'/*');
@endphp
@include('partials.error-shell', [
    'code' => 429,
    'isStudio' => $isStudio,
    'title' => 'Too many requests',
    'message' => "You've made too many requests in a short time. Please wait a moment and try again.",
])
