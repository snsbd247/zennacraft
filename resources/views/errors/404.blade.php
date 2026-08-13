@php
    $adminPath = trim((string) config('admin.path', 'studio'), '/');
    $isStudio = request()->is($adminPath, $adminPath.'/*');
@endphp
@include('partials.error-shell', [
    'code' => 404,
    'isStudio' => $isStudio,
    'title' => 'Page not found',
    'message' => $isStudio
        ? "This Studio page doesn't exist, or the link may be out of date."
        : "The page you're looking for doesn't exist, or may have moved.",
])
