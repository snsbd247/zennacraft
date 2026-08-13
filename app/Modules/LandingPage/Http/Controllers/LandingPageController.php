<?php

namespace App\Modules\LandingPage\Http\Controllers;

use App\Modules\LandingPage\Http\Requests\StoreLandingPageRequest;
use App\Modules\LandingPage\Http\Requests\UpdateLandingPageRequest;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Studio manager for storefront Landing Pages. Each page renders with one of
 * several visual templates (see LandingPage::TEMPLATES). Reuses
 * LandingPageService for slug generation + storefront cache invalidation.
 */
class LandingPageController extends Controller
{
    public function __construct(
        private LandingPageService $service,
        private MediaService $mediaService,
        private CacheService $cacheService,
    ) {}

    public function index(): View
    {
        return view('studio.landing.index', [
            'pages' => $this->service->paginate(20),
            'autoCreate' => filter_var(app(\App\Modules\Settings\Services\SettingService::class)
                ->get('general', 'auto_landing_for_products', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /** Toggle "auto-create a landing page for every new product". */
    public function toggleAutoCreate(Request $request): RedirectResponse
    {
        app(\App\Modules\Settings\Services\SettingService::class)
            ->set('general', 'auto_landing_for_products', $request->boolean('auto_create'), 'boolean');

        return back()->with('success', 'Auto landing-page setting saved.');
    }

    public function create(): View
    {
        return view('studio.landing.form', [
            'page' => new LandingPage(['template' => 'classic', 'status' => 'active']),
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
            'suggested' => [],
            'galleryImages' => [],
        ]);
    }

    public function store(StoreLandingPageRequest $request): RedirectResponse
    {
        $this->service->create($this->payload($request));

        return redirect()->route('landing.index')->with('success', 'Landing page created.');
    }

    public function edit(LandingPage $landingPage): View
    {
        return view('studio.landing.form', [
            'page' => $landingPage->load('heroImage'),
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
            'suggested' => $this->suggestedData($landingPage->suggested_products ?? []),
            'galleryImages' => $this->galleryImages($landingPage->gallery ?? []),
        ]);
    }

    /** @return array<int, array{id:int,url:?string}> */
    private function galleryImages(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $media = \App\Modules\Media\Models\Media::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $media->get($id))->filter()
            ->map(fn ($m) => ['id' => $m->id, 'url' => $this->mediaService->url($m)])->values()->all();
    }

    public function update(UpdateLandingPageRequest $request, LandingPage $landingPage): RedirectResponse
    {
        $this->service->update($landingPage, $this->payload($request, $landingPage));

        return redirect()->route('landing.index')->with('success', 'Landing page updated.');
    }

    public function toggleStatus(Request $request, LandingPage $landingPage): JsonResponse|RedirectResponse
    {
        // Direct status flip (not via service->update, which would regenerate the slug).
        $landingPage->update(['status' => $landingPage->isActive() ? 'inactive' : 'active']);
        $this->cacheService->invalidateStorefrontLandingPages();

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'status' => $landingPage->status])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, LandingPage $landingPage): JsonResponse|RedirectResponse
    {
        $this->service->delete($landingPage);

        return $request->expectsJson()
            ? response()->json(['message' => 'Landing page deleted.'])
            : back()->with('success', 'Landing page deleted.');
    }

    /** AJAX product picker for the CTA link — search the store's products by name or SKU. */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $products = \App\Modules\Product\Models\Product::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'))
            ->with('thumbnail')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return response()->json([
            'results' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) $p->price,
                'thumb' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
                // Picking a product points the button straight at that product's
                // checkout (Buy now), not its details page.
                'url' => route('checkout', ['product_id' => $p->id, 'quantity' => 1]),
            ])->values(),
        ]);
    }

    private function payload(StoreLandingPageRequest|UpdateLandingPageRequest $request, ?LandingPage $existing = null): array
    {
        $data = $request->validated();
        if ($request->hasFile('hero_image')) {
            $data['hero_image_id'] = $this->mediaService->upload($request->file('hero_image'), $data['title'], null, 'landing')->id;
        }
        // Normalise the picked product ids (kept order, unique, integers).
        $data['suggested_products'] = array_values(array_unique(array_map('intval', (array) $request->input('suggested_products', []))));

        // Gallery: new uploads append to the existing set (or clear it first).
        $gallery = $request->boolean('clear_gallery') ? [] : ($existing?->gallery ?? []);
        foreach ((array) $request->file('gallery', []) as $file) {
            $gallery[] = $this->mediaService->upload($file, ($data['title'] ?? 'Landing').' gallery', null, 'landing')->id;
        }
        $data['gallery'] = array_values(array_filter(array_map('intval', $gallery)));
        $data['show_reviews'] = $request->boolean('show_reviews');

        unset($data['hero_image'], $data['suggested_products.*'], $data['clear_gallery'], $data['gallery.*']);

        return $data;
    }

    /**
     * Chip data (id/name/sku/thumb) for the form's suggested-product picker,
     * preserving the saved order.
     *
     * @param  array<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function suggestedData(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $products = \App\Modules\Product\Models\Product::whereIn('id', $ids)->with('thumbnail')->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $products->get($id))->filter()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'thumb' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
        ])->values()->all();
    }
}
