<section class="zc-page-section">
    <div class="zc-container">
        <div class="mb-8 max-w-3xl">
            <p class="zc-kicker">Craft story</p>
            <h2 class="zc-heading mt-2 text-3xl sm:text-4xl">A handmade product experience with clear care details.</h2>
            <p class="zc-copy mt-3">Use the sections below to understand the product, materials, dimensions, and care expectations before placing a COD order.</p>
        </div>

        <div class="zc-product-story-grid">
            <details class="zc-product-story-card zc-ds-card" open>
                <summary>Craft Story</summary>
                @if ($product->craft_story || $product->description)
                    <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $product->craft_story ?: $product->description }}</div>
                @else
                    <p class="mt-4 text-sm leading-7 text-slate-600">Craft story has not been added for this product yet.</p>
                @endif
            </details>

            <details class="zc-product-story-card zc-ds-card">
                <summary>Materials</summary>
                @if ($product->materials)
                    <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $product->materials }}</div>
                @else
                    <p class="mt-4 text-sm leading-7 text-slate-600">Material details are not available yet.</p>
                @endif
            </details>

            <details class="zc-product-story-card zc-ds-card">
                <summary>Dimensions</summary>
                @if ($product->dimensions)
                    <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $product->dimensions }}</div>
                @else
                    <p class="mt-4 text-sm leading-7 text-slate-600">Dimensions are not available yet.</p>
                @endif
            </details>

            <details class="zc-product-story-card zc-ds-card">
                <summary>Care Instructions</summary>
                @if ($product->care_guide)
                    <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $product->care_guide }}</div>
                @else
                    <p class="mt-4 text-sm leading-7 text-slate-600">Care instructions have not been added yet.</p>
                @endif
            </details>

            @if (is_array($product->faq_json) && count($product->faq_json) > 0)
                <details class="zc-product-story-card zc-ds-card">
                    <summary>Product FAQ</summary>
                    <div class="mt-4 space-y-4">
                        @foreach ($product->faq_json as $faq)
                            <div>
                                <div class="text-sm font-black text-slate-950">{{ $faq['question'] ?? '' }}</div>
                                <p class="mt-1 text-sm leading-7 text-slate-600">{{ $faq['answer'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>
</section>
