@php
    $trustCards = $trustCards ?? [
        ['title' => 'Handmade', 'copy' => 'Craft-led pieces with tactile detail and care.', 'badge' => 'Handmade'],
        ['title' => 'Artisan Made', 'copy' => 'Designed around traditional Bengali craft language.', 'badge' => 'Artisan'],
        ['title' => 'COD Available', 'copy' => 'Place your order first and pay on delivery.', 'badge' => 'COD'],
        ['title' => 'Secure Checkout', 'copy' => 'A focused checkout flow for protected order placement.', 'badge' => 'Secure'],
        ['title' => 'Fast Delivery', 'copy' => 'Orders move through a clear courier handoff workflow.', 'badge' => 'Delivery'],
    ];
@endphp

<div class="zc-trust-grid zc-trust-grid--premium">
    @foreach ($trustCards as $card)
        <article class="zc-trust-card zc-trust-card--premium">
            <span class="zc-badge zc-badge-verified">{{ $card['badge'] }}</span>
            <h3 class="mt-4 text-lg font-black text-slate-950">{{ $card['title'] }}</h3>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $card['copy'] }}</p>
        </article>
    @endforeach
</div>
