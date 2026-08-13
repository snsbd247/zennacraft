@extends('layouts.app')
@section('title', ($landingPage->meta_title ?: $landingPage->title).' — '.$storeName)
@section('meta_description', $landingPage->meta_description ?: '')
@section('content')
@php
    $mediaUrl = $mediaUrl ?? fn ($m) => null;
    $suggestedProducts = $suggestedProducts ?? collect();
    $deliveryZones = $deliveryZones ?? [];
    $galleryImages = $galleryImages ?? collect();
    $landingReviews = $landingReviews ?? collect();
    $heroImg = $landingPage->heroImage ? $mediaUrl($landingPage->heroImage) : null;
    $ctaText = $landingPage->cta_text ?: 'Order Now';
    $tpl = array_key_exists($landingPage->template, \App\Modules\LandingPage\Models\LandingPage::TEMPLATES) ? $landingPage->template : 'classic';
    $accent = \App\Modules\LandingPage\Models\LandingPage::TEMPLATES[$tpl]['accent'] ?? '#1f7a3d';
    $hasOrderForm = $suggestedProducts->isNotEmpty();
    // With products present the buttons scroll to the on-page order form (no separate
    // checkout). Otherwise fall back to a saved link, or the cart checkout.
    if ($hasOrderForm) {
        $ctaUrl = '#zc-order';
    } else {
        $ctaUrl = $landingPage->cta_url ?: route('checkout');
        if ($landingPage->cta_url && preg_match('#/products/([^/?\#]+)#', $landingPage->cta_url, $m)) {
            $ctaProductId = \App\Modules\Product\Models\Product::where('slug', $m[1])->value('id');
            if ($ctaProductId) {
                $ctaUrl = route('checkout', ['product_id' => $ctaProductId, 'quantity' => 1]);
            }
        }
    }
@endphp
@include('storefront.landing-pages.templates.'.$tpl, compact('landingPage', 'heroImg', 'ctaText', 'ctaUrl'))

@include('storefront.landing-pages._sections', [
    'landingPage' => $landingPage,
    'galleryImages' => $galleryImages,
    'reviews' => $landingReviews,
    'mediaUrl' => $mediaUrl,
    'accent' => $accent,
    'ctaUrl' => $ctaUrl,
    'ctaText' => $ctaText,
])

@if ($hasOrderForm)
    @include('storefront.landing-pages._order-form', [
        'products' => $suggestedProducts,
        'landingPage' => $landingPage,
        'deliveryZones' => $deliveryZones,
        'freeDeliveryThreshold' => $freeDeliveryThreshold ?? 0,
        'freeDeliveryAll' => $freeDeliveryAll ?? false,
        'mediaUrl' => $mediaUrl,
        'accent' => $accent,
    ])
@endif
@endsection
