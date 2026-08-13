@php
    $baseImageUrl = $mediaUrl($product->thumbnail);
    $baseComparePrice = $product->compare_price && (float) $product->compare_price > (float) $product->price
        ? (float) $product->compare_price
        : null;
    $baseStock = (int) $product->stock;
    $offers = collect([
        [
            'name' => 'Single Piece',
            'variant_id' => '',
            'price' => (float) $product->price,
            'compare_price' => $baseComparePrice,
            'stock' => $baseStock,
            'image' => $baseImageUrl,
            'badge' => 'Popular Choice',
            'details' => $product->short_description ?: 'The core product offer for simple COD buying.',
            'package_type' => 'Single Piece',
            'options' => [],
            'weight' => null,
            'dimensions' => null,
        ],
    ]);

    $variantOffers = collect($activeVariants)
        ->filter(fn ($variant) => $variant->status === 'active' && (bool) $variant->show_on_storefront)
        ->values()
        ->map(function ($variant, int $index) use ($mediaUrl, $baseImageUrl) {
        $comparePrice = $variant->compare_price && (float) $variant->compare_price > (float) $variant->price
            ? (float) $variant->compare_price
            : null;

        return [
            'name' => $variant->name,
            'variant_id' => (string) $variant->id,
            'price' => (float) $variant->price,
            'compare_price' => $comparePrice,
            'stock' => (int) $variant->stock,
            'image' => $mediaUrl($variant->image) ?: $baseImageUrl,
            'badge' => $variant->badge ?: ($variant->is_featured ? 'Best Value' : ['Popular Choice', 'Limited', 'Package'][$index % 3]),
            'details' => $variant->short_description ?: ($variant->package_type ?: $variant->sku),
            'package_type' => $variant->package_type,
            'options' => $variant->option_values ?: [],
            'weight' => $variant->weight,
            'dimensions' => $variant->dimensions,
        ];
    });

    $offers = $offers->merge($variantOffers)->values();
@endphp

<div class="zc-offer-selector" data-offer-selector data-behavior-product-id="{{ $product->id }}">
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="zc-kicker">Choose your package</p>
            <h2 class="text-2xl font-black text-slate-950">Package options</h2>
        </div>
        <span class="zc-badge zc-badge-limited">Handmade Package Selection</span>
    </div>

    <div class="zc-offer-grid">
        @foreach ($offers as $offer)
            @php
                $savings = $offer['compare_price'] ? max(0, $offer['compare_price'] - $offer['price']) : 0;
                $discountPercent = $offer['compare_price']
                    ? max(1, (int) round((($offer['compare_price'] - $offer['price']) / $offer['compare_price']) * 100))
                    : null;
                $inputId = 'offer_'.($offer['variant_id'] !== '' ? $offer['variant_id'] : 'base');
            @endphp
            <label class="zc-offer-card" for="{{ $inputId }}">
                <input
                    id="{{ $inputId }}"
                    class="sr-only"
                    type="radio"
                    name="variant_id"
                    value="{{ $offer['variant_id'] }}"
                    data-offer-option
                    data-offer-name="{{ $offer['name'] }}"
                    data-offer-price="{{ number_format($offer['price'], 2) }}"
                    data-offer-raw-price="{{ $offer['price'] }}"
                    data-offer-compare-price="{{ $offer['compare_price'] ? number_format($offer['compare_price'], 2) : '' }}"
                    data-offer-has-compare="{{ $offer['compare_price'] ? '1' : '0' }}"
                    data-offer-discount="{{ $discountPercent ? $discountPercent.'% off' : '' }}"
                    data-offer-has-discount="{{ $discountPercent ? '1' : '0' }}"
                    data-offer-savings="{{ $savings > 0 ? 'Save '.number_format($savings, 2) : '' }}"
                    data-offer-has-savings="{{ $savings > 0 ? '1' : '0' }}"
                    data-offer-stock="{{ $offer['stock'] > 0 ? ($offer['stock'] <= 5 ? 'Low stock: '.$offer['stock'].' left' : 'In stock') : 'Out of stock' }}"
                    data-offer-stock-tone="{{ $offer['stock'] > 0 ? 'success' : 'danger' }}"
                    data-offer-badge="{{ $offer['badge'] }}"
                    data-offer-image="{{ $offer['image'] }}"
                    @checked($loop->first)
                >
                <span class="zc-offer-card__surface zc-ds-card zc-ds-card--interactive">
                    <span class="flex items-start justify-between gap-3">
                        <span>
                            <span class="zc-badge {{ $loop->first ? 'zc-badge-artisan' : 'zc-badge-gift' }}">{{ $offer['badge'] }}</span>
                            <span class="mt-4 block text-lg font-black text-slate-950">{{ $offer['name'] }}</span>
                            @if (! empty($offer['package_type']))
                                <span class="mt-1 block text-xs font-black uppercase tracking-[0.18em] text-slate-400">{{ $offer['package_type'] }}</span>
                            @endif
                            <span class="mt-1 block text-sm text-slate-500">{{ $offer['details'] }}</span>
                        </span>
                        <span class="zc-offer-card__radio" aria-hidden="true"></span>
                    </span>

                    <span class="zc-offer-price-row mt-5">
                        <strong>{{ number_format($offer['price'], 2) }}</strong>
                        @if ($offer['compare_price'])
                            <span class="pb-1 text-sm text-slate-400 line-through">{{ number_format($offer['compare_price'], 2) }}</span>
                        @endif
                    </span>

                    <span class="zc-offer-badge-row mt-3">
                        @if ($savings > 0)
                            <span class="zc-badge zc-badge-warning">Save {{ number_format($savings, 2) }}</span>
                        @endif
                        <span class="zc-badge {{ $offer['stock'] > 0 ? 'zc-badge-success' : 'zc-badge-danger' }}">
                            {{ $offer['stock'] > 0 ? 'In stock' : 'Out of stock' }}
                        </span>
                    </span>

                    @if (! empty($offer['options']))
                        <span class="mt-4 flex flex-wrap gap-2">
                            @foreach ($offer['options'] as $optionName => $optionValue)
                                <span class="zc-badge">{{ $optionName }}: {{ $optionValue }}</span>
                            @endforeach
                        </span>
                    @endif

                    @if (! empty($offer['weight']) || ! empty($offer['dimensions']))
                        <span class="mt-4 flex flex-wrap gap-2">
                            @if (! empty($offer['weight']))
                                <span class="zc-badge">{{ number_format((float) $offer['weight'], 2) }} kg</span>
                            @endif
                            @if (! empty($offer['dimensions']))
                                <span class="zc-badge">{{ $offer['dimensions'] }}</span>
                            @endif
                        </span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</div>

@once
    @push('storefront-scripts')
        <script>
            (() => {
                const syncOffer = (input, shouldTrack = false) => {
                    if (!input) {
                        return;
                    }

                    const imageUrl = input.dataset.offerImage || '';
                    const name = input.dataset.offerName || 'Selected package';
                    const price = input.dataset.offerPrice || '';
                    const comparePrice = input.dataset.offerComparePrice || '';
                    const hasCompare = input.dataset.offerHasCompare === '1';
                    const discount = input.dataset.offerDiscount || '';
                    const hasDiscount = input.dataset.offerHasDiscount === '1';
                    const savings = input.dataset.offerSavings || '';
                    const hasSavings = input.dataset.offerHasSavings === '1';
                    const stock = input.dataset.offerStock || '';
                    const stockTone = input.dataset.offerStockTone || 'neutral';
                    const badge = input.dataset.offerBadge || '';
                    const mainImage = document.querySelector('[data-product-main-image]');
                    const mainLink = document.querySelector('[data-product-main-link]');

                    document.querySelectorAll('[data-selected-offer-name]').forEach((node) => {
                        node.textContent = name;
                    });

                    document.querySelectorAll('[data-selected-offer-price]').forEach((node) => {
                        node.textContent = price;
                    });

                    document.querySelectorAll('[data-selected-offer-compare]').forEach((node) => {
                        node.textContent = comparePrice;
                        node.classList.toggle('hidden', !hasCompare);
                    });

                    document.querySelectorAll('[data-selected-offer-stock]').forEach((node) => {
                        node.textContent = stock;
                        node.className = `zc-badge zc-badge-${stockTone}`;
                    });

                    document.querySelectorAll('[data-selected-offer-discount]').forEach((node) => {
                        node.textContent = discount;
                        node.classList.toggle('hidden', !hasDiscount);
                    });

                    document.querySelectorAll('[data-selected-offer-save]').forEach((node) => {
                        node.textContent = savings;
                        node.classList.toggle('hidden', !hasSavings);
                    });

                    document.querySelectorAll('[data-selected-offer-badge]').forEach((node) => {
                        node.textContent = badge;
                        node.classList.toggle('hidden', !badge);
                    });

                    document.querySelectorAll('[data-selected-variant-input]').forEach((node) => {
                        node.value = input.value || '';
                    });

                    if (imageUrl && mainImage) {
                        mainImage.removeAttribute('srcset');
                        mainImage.setAttribute('src', imageUrl);
                        mainImage.closest('.zc-gallery-stage')?.classList.add('is-changing');
                        window.setTimeout(() => {
                            mainImage.closest('.zc-gallery-stage')?.classList.remove('is-changing');
                        }, 240);
                    }

                    if (imageUrl && mainLink) {
                        mainLink.setAttribute('href', imageUrl);
                    }

                    if (shouldTrack && window.zennaBehavior?.track) {
                        const selector = input.closest('[data-offer-selector]');
                        const productId = selector?.dataset.behaviorProductId || null;

                        window.zennaBehavior.track('package_selected', {
                            product_id: productId,
                            product_variant_id: input.value || null,
                            metadata: {
                                package_name: name,
                                package_badge: badge,
                                price: input.dataset.offerRawPrice || null,
                            },
                        });
                    }
                };

                const syncQuantity = (input) => {
                    const quantity = input?.value || '1';

                    document.querySelectorAll('[data-sticky-quantity-input]').forEach((node) => {
                        node.value = quantity;
                    });
                };

                document.addEventListener('change', (event) => {
                    const input = event.target.closest('[data-offer-option]');

                    if (input) {
                        syncOffer(input, true);
                    }

                    const quantityInput = event.target.closest('[data-product-quantity]');

                    if (quantityInput) {
                        syncQuantity(quantityInput);
                    }
                });

                document.addEventListener('click', (event) => {
                    const thumb = event.target.closest('[data-gallery-thumb]');

                    if (thumb) {
                        const imageUrl = thumb.dataset.galleryImage || '';
                        const mainImage = document.querySelector('[data-product-main-image]');
                        const mainLink = document.querySelector('[data-product-main-link]');

                        if (imageUrl && mainImage) {
                            mainImage.removeAttribute('srcset');
                            mainImage.setAttribute('src', imageUrl);
                            mainImage.closest('.zc-gallery-stage')?.classList.add('is-changing');
                            window.setTimeout(() => {
                                mainImage.closest('.zc-gallery-stage')?.classList.remove('is-changing');
                            }, 240);
                        }

                        if (imageUrl && mainLink) {
                            mainLink.setAttribute('href', imageUrl);
                        }

                        document.querySelectorAll('[data-gallery-thumb]').forEach((node) => {
                            const active = node === thumb;
                            node.classList.toggle('is-active', active);
                            node.setAttribute('aria-pressed', active ? 'true' : 'false');
                        });
                    }

                    const quantityInput = document.querySelector('[data-product-quantity]');

                    if (event.target.closest('[data-quantity-decrement]') && quantityInput) {
                        quantityInput.value = Math.max(1, Number(quantityInput.value || 1) - 1);
                        syncQuantity(quantityInput);
                    }

                    if (event.target.closest('[data-quantity-increment]') && quantityInput) {
                        quantityInput.value = Math.min(99, Number(quantityInput.value || 1) + 1);
                        syncQuantity(quantityInput);
                    }
                });

                document.addEventListener('input', (event) => {
                    const quantityInput = event.target.closest('[data-product-quantity]');

                    if (quantityInput) {
                        quantityInput.value = Math.max(1, Math.min(99, Number(quantityInput.value || 1)));
                        syncQuantity(quantityInput);
                    }
                });

                syncOffer(document.querySelector('[data-offer-option]:checked'));
                syncQuantity(document.querySelector('[data-product-quantity]'));
            })();
        </script>
    @endpush
@endonce
