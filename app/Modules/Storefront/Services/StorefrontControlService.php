<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Performance\Services\CacheService;
use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Storefront\Models\StorefrontSlider;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StorefrontControlService
{
    public function __construct(private CacheService $cacheService) {}

    public function createSlider(array $data): StorefrontSlider
    {
        return StorefrontSlider::create($this->sliderPayload($data));
    }

    public function updateSlider(StorefrontSlider $slider, array $data): StorefrontSlider
    {
        $slider->update($this->sliderPayload($data));

        return $slider->refresh();
    }

    public function deleteSlider(StorefrontSlider $slider): void
    {
        $slider->delete();
    }

    public function setSliderActive(StorefrontSlider $slider, bool $active): StorefrontSlider
    {
        $slider->update(['active' => $active]);

        return $slider->refresh();
    }

    public function reorderSliders(array $orders): void
    {
        foreach ($orders as $id => $sortOrder) {
            StorefrontSlider::query()
                ->whereKey((int) $id)
                ->update(['sort_order' => max(0, (int) $sortOrder)]);
        }

        $this->cacheService->invalidateStorefrontContent();
    }

    public function createCmsPage(array $data): CmsPage
    {
        return CmsPage::create($this->cmsPayload($data));
    }

    public function updateCmsPage(CmsPage $page, array $data): CmsPage
    {
        $page->update($this->cmsPayload($data, $page));

        return $page->refresh();
    }

    public function deleteCmsPage(CmsPage $page): void
    {
        $page->delete();
    }

    protected function sliderPayload(array $data): array
    {
        return [
            'title' => $data['title'],
            'subtitle' => Arr::get($data, 'subtitle'),
            'description' => Arr::get($data, 'description'),
            'button_text' => Arr::get($data, 'button_text'),
            'button_url' => Arr::get($data, 'button_url'),
            'desktop_image_id' => Arr::get($data, 'desktop_image_id'),
            'mobile_image_id' => Arr::get($data, 'mobile_image_id'),
            'badge_text' => Arr::get($data, 'badge_text'),
            'active' => (bool) Arr::get($data, 'active', false),
            'sort_order' => max(0, (int) Arr::get($data, 'sort_order', 0)),
        ];
    }

    protected function cmsPayload(array $data, ?CmsPage $page = null): array
    {
        $slug = filled($data['slug'] ?? null)
            ? Str::slug((string) $data['slug'])
            : Str::slug((string) $data['title']);

        return [
            'title' => $data['title'],
            'slug' => $slug,
            'content' => Arr::get($data, 'content'),
            'meta_title' => Arr::get($data, 'meta_title'),
            'meta_description' => Arr::get($data, 'meta_description'),
            'active' => (bool) Arr::get($data, 'active', false),
        ];
    }
}
