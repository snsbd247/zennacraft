<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Media\Models\Media;
use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Storefront\Models\StorefrontSlider;
use Illuminate\Database\Eloquent\Collection;

class StorefrontContentService
{
    public function __construct(private CacheService $cacheService) {}

    public function activeSliders(): Collection
    {
        $items = $this->cacheService->remember(
            CacheKeyRegistry::STOREFRONT_ACTIVE_SLIDERS,
            fn () => StorefrontSlider::query()
                ->with(['image'])
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get()
                ->map(fn (StorefrontSlider $slider): array => [
                    'attributes' => $slider->getAttributes(),
                    'desktop_image' => $slider->desktopImage?->getAttributes(),
                    'mobile_image' => $slider->mobileImage?->getAttributes(),
                ])
                ->all(),
            null,
            [CacheKeyRegistry::STOREFRONT_CONTENT_TAG]
        );

        return new Collection(collect($items)->map(function (array $item): StorefrontSlider {
            $slider = new StorefrontSlider();
            $slider->setRawAttributes($item['attributes'] ?? [], true);
            $slider->exists = true;

            if (! empty($item['desktop_image'])) {
                $media = new Media();
                $media->setRawAttributes($item['desktop_image'], true);
                $media->exists = true;
                $slider->setRelation('desktopImage', $media);
            }

            if (! empty($item['mobile_image'])) {
                $media = new Media();
                $media->setRawAttributes($item['mobile_image'], true);
                $media->exists = true;
                $slider->setRelation('mobileImage', $media);
            }

            return $slider;
        })->all());
    }

    public function footerPages(): Collection
    {
        $items = $this->cacheService->remember(
            CacheKeyRegistry::STOREFRONT_FOOTER_CMS_PAGES,
            fn () => CmsPage::query()
                ->where('active', true)
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'updated_at'])
                ->map(fn (CmsPage $page): array => $page->getAttributes())
                ->all(),
            null,
            [CacheKeyRegistry::STOREFRONT_CONTENT_TAG]
        );

        return new Collection(collect($items)->map(function (array $attributes): CmsPage {
            $page = new CmsPage();
            $page->setRawAttributes($attributes, true);
            $page->exists = true;

            return $page;
        })->all());
    }

    public function cmsPageForDisplay(CmsPage $page): CmsPage
    {
        $attributes = $this->cacheService->remember(
            CacheKeyRegistry::cmsPageDetail($page->id, (string) $page->updated_at?->timestamp),
            fn () => CmsPage::query()->findOrFail($page->id)->getAttributes(),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::STOREFRONT_CONTENT_TAG]
        );

        $cmsPage = new CmsPage();
        $cmsPage->setRawAttributes(is_array($attributes) ? $attributes : $page->getAttributes(), true);
        $cmsPage->exists = true;

        return $cmsPage;
    }
}
