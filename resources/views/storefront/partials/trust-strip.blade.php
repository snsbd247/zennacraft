@php
    $items = $items ?? [
        ['label' => 'Handmade Product', 'class' => 'zc-badge-handmade'],
        ['label' => 'Artisan Crafted', 'class' => 'zc-badge-artisan'],
        ['label' => 'Cash On Delivery', 'class' => 'zc-badge-cod'],
        ['label' => 'Quality Checked', 'class' => 'zc-badge-verified'],
        ['label' => 'Fast Delivery', 'class' => 'zc-badge-gift'],
    ];
@endphp

<div class="zc-product-trust-strip" aria-label="Product trust badges">
    @foreach ($items as $item)
        <span class="zc-badge {{ $item['class'] }}">
            <span aria-hidden="true">✓</span>
            {{ $item['label'] }}
        </span>
    @endforeach
</div>
