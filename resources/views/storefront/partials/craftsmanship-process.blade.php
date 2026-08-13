@php
    $processSteps = $processSteps ?? [
        ['step' => '01', 'title' => 'Design', 'copy' => 'Collection ideas begin with pattern, use, color, and gifting context.'],
        ['step' => '02', 'title' => 'Handmade Creation', 'copy' => 'Pieces are prepared around artisan-led handmade production.'],
        ['step' => '03', 'title' => 'Quality Check', 'copy' => 'Active catalog items are reviewed before they appear in the storefront.'],
        ['step' => '04', 'title' => 'Delivery', 'copy' => 'Orders move into the COD and courier workflow after checkout.'],
    ];
@endphp

<section class="zc-page-section" style="background:var(--neel-950);">
    <div class="zc-container">
        <div class="mb-8 max-w-3xl">
            <p class="zc-kicker" style="color:var(--turmeric);">
                <span style="display:inline-block;width:26px;height:2px;background-image:linear-gradient(90deg,var(--turmeric) 0 60%,transparent 60%);background-size:8px 2px;"></span>
                Craftsmanship process
            </p>
            <h2 class="mt-2 text-3xl sm:text-4xl" style="color:var(--cotton);">From pattern thinking to doorstep delivery.</h2>
            <p class="mt-3" style="color:var(--cotton-dim);opacity:.85;">A simple visual process for how Zenna Craft presents and prepares handmade collections.</p>
        </div>

        <div class="zc-process-grid">
            @foreach ($processSteps as $step)
                <article class="zc-process-card">
                    <span class="zc-process-card__step">{{ $step['step'] }}</span>
                    <h3 class="mt-5 text-xl" style="font-family:'Fraunces',serif;font-weight:600;color:var(--ink);">{{ $step['title'] }}</h3>
                    <p class="mt-3 text-sm leading-7" style="color:var(--ink-soft);">{{ $step['copy'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
