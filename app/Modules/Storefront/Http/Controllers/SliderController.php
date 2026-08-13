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
 * Studio manager for the storefront's homepage banners. Each slider targets one
 * "placement" (hero carousel, side banner, promo banner) and carries a single,
 * device-responsive image. Saving/deleting flushes the storefront content cache
 * via the model's observer, so the live site reflects changes immediately.
 */
class SliderController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(string $placement = 'home_hero'): View
    {
        return view('studio.sliders.index', [
            'placement' => $placement,
            'meta' => StorefrontSlider::placementMeta($placement),
            'sliders' => StorefrontSlider::with('image')->where('placement', $placement)
                ->orderBy('sort_order')->orderByDesc('id')->paginate(20),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function create(string $placement = 'home_hero'): View
    {
        return view('studio.sliders.form', [
            'slider' => new StorefrontSlider(['placement' => $placement, 'active' => true, 'sort_order' => 0]),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        if ($id = $this->upload($request, $data['title'] ?: 'Slider')) {
            $data['desktop_image_id'] = $id;
        }
        StorefrontSlider::create($data);

        return redirect()->route($this->indexRoute($data['placement']))->with('success', 'Slider added.');
    }

    public function edit(StorefrontSlider $slider): View
    {
        return view('studio.sliders.form', [
            'slider' => $slider->load('image'),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function update(Request $request, StorefrontSlider $slider): RedirectResponse
    {
        $data = $this->validated($request, false);
        if ($id = $this->upload($request, $data['title'] ?: 'Slider')) {
            $data['desktop_image_id'] = $id;
        }
        $slider->update($data);

        return redirect()->route($this->indexRoute($data['placement']))->with('success', 'Slider updated.');
    }

    public function toggleStatus(Request $request, StorefrontSlider $slider): JsonResponse|RedirectResponse
    {
        $slider->update(['active' => ! $slider->active]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'active' => (bool) $slider->active])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, StorefrontSlider $slider): JsonResponse|RedirectResponse
    {
        $slider->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Slider deleted.'])
            : back()->with('success', 'Slider deleted.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(array_keys(StorefrontSlider::PLACEMENTS))],
            'title' => ['nullable', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'badge_text' => ['nullable', 'string', 'max:60'],
            'button_text' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => [$creating ? 'required' : 'nullable', 'image', 'max:6144'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        // Title is optional (image-only banners). The column is NOT NULL, so
        // store an empty string — the storefront uses filled() and treats '' as
        // "no title" (clean image, no text overlay).
        $data['title'] = $data['title'] ?? '';
        unset($data['image']);

        return $data;
    }

    private function upload(Request $request, string $alt): ?int
    {
        return $request->hasFile('image')
            ? $this->mediaService->upload($request->file('image'), $alt, null, 'slider')->id
            : null;
    }

    /** Placement value (home_hero) → its list route name (sliders.hero.index). */
    private function indexRoute(string $placement): string
    {
        return 'sliders.'.str_replace('home_', '', $placement).'.index';
    }
}
