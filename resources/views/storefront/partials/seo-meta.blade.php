@php
    $seo = $seoPayload ?? [];
@endphp

@if (filled($seo['title'] ?? null))
    <title>{{ $seo['title'] }}</title>
@endif

@if (filled($seo['description'] ?? null))
    <meta name="description" content="{{ $seo['description'] }}">
@endif

@if (filled($seo['keywords'] ?? null))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif

@if (filled($seo['robots'] ?? null))
    <meta name="robots" content="{{ $seo['robots'] }}">
@endif

@if (filled($seo['canonical_url'] ?? null))
    <link rel="canonical" href="{{ $seo['canonical_url'] }}">
@endif

@if (filled($seo['og_title'] ?? null))
    <meta property="og:title" content="{{ $seo['og_title'] }}">
@endif

@if (filled($seo['og_description'] ?? null))
    <meta property="og:description" content="{{ $seo['og_description'] }}">
@endif

@if (filled($seo['og_image'] ?? null))
    <meta property="og:image" content="{{ $seo['og_image'] }}">
@endif

@if (filled($seo['og_url'] ?? null))
    <meta property="og:url" content="{{ $seo['og_url'] }}">
@endif

@if (filled($seo['og_type'] ?? null))
    <meta property="og:type" content="{{ $seo['og_type'] }}">
@endif

@if (filled($seo['twitter_card'] ?? null))
    <meta name="twitter:card" content="{{ $seo['twitter_card'] }}">
@endif

@if (filled($seo['twitter_title'] ?? null))
    <meta name="twitter:title" content="{{ $seo['twitter_title'] }}">
@endif

@if (filled($seo['twitter_description'] ?? null))
    <meta name="twitter:description" content="{{ $seo['twitter_description'] }}">
@endif

@if (filled($seo['twitter_image'] ?? null))
    <meta name="twitter:image" content="{{ $seo['twitter_image'] }}">
@endif

@if (! empty($seo['schema']))
    <script type="application/ld+json">@json($seo['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif
