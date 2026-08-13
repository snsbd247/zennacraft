<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Media\Services\MediaService;
use App\Modules\Storefront\Models\StorefrontSlider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Studio "Offer Banner" (Campaign/Offer): promotional banners rendered in
 * storefront content slots (above the footer, after the Top Selling row).
 * Reuses the existing StorefrontSlider table/model rather than duplicating a
 * banner store — these rows simply live on the "offer" placements. One
 * unified list manages every offer placement, with a placement picker on add.
 */
class OfferBannerController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(): View
    {
        return view('studio.offer-banners.index', [
            'banners' => StorefrontSlider::with('image')
                ->whereIn('placement', StorefrontSlider::offerPlacements())
                ->orderBy('placement')->orderBy('sort_order')->orderByDesc('id')
                ->paginate(30),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function create(): View
    {
        return $this->form(new StorefrontSlider([
            'placement' => StorefrontSlider::offerPlacements()[0],
            'active' => true,
            'sort_order' => 0,
        ]));
    }

    public function edit(StorefrontSlider $offerBanner): View
    {
        abort_unless(in_array($offerBanner->placement, StorefrontSlider::offerPlacements(), true), 404);

        return $this->form($offerBanner->load('image'));
    }

    private function form(StorefrontSlider $banner): View
    {
        return view('studio.offer-banners.form', [
            'banner' => $banner,
            'placements' => StorefrontSlider::offerPlacements(),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        if ($id = $this->upload($request, $data['title'])) {
            $data['desktop_image_id'] = $id;
        }
        StorefrontSlider::create($data);

        return redirect()->route('offer-banners.index')->with('success', 'Offer banner added.');
    }

    public function update(Request $request, StorefrontSlider $offerBanner): RedirectResponse
    {
        abort_unless(in_array($offerBanner->placement, StorefrontSlider::offerPlacements(), true), 404);

        $data = $this->validated($request, false);
        if ($id = $this->upload($request, $data['title'])) {
            $data['desktop_image_id'] = $id;
        }
        $offerBanner->update($data);

        return redirect()->route('offer-banners.index')->with('success', 'Offer banner updated.');
    }

    public function toggleStatus(Request $request, StorefrontSlider $offerBanner): JsonResponse|RedirectResponse
    {
        $offerBanner->update(['active' => ! $offerBanner->active]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'active' => (bool) $offerBanner->active])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, StorefrontSlider $offerBanner): JsonResponse|RedirectResponse
    {
        $offerBanner->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Banner deleted.'])
            : back()->with('success', 'Banner deleted.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(StorefrontSlider::offerPlacements())],
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => [$creating ? 'required' : 'nullable', 'image', 'max:6144'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        unset($data['image']);

        return $data;
    }

    private function upload(Request $request, string $alt): ?int
    {
        return $request->hasFile('image')
            ? $this->mediaService->upload($request->file('image'), $alt, null, 'offer-banner')->id
            : null;
    }
}
