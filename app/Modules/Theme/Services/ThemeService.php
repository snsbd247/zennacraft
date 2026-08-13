<?php

namespace App\Modules\Theme\Services;

use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Theme\Repositories\ThemeRepository;
use Illuminate\Support\Collection;

class ThemeService
{
    public function __construct(
        private ThemeRepository $repository,
        private MediaService $mediaService,
        private CacheService $cacheService,
        private SettingService $settingService,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings();
        $value = $settings->get($key);

        return $value !== null && $value !== '' ? $value : $default;
    }

    public function set(string $key, mixed $value, string $dataType = 'string', bool $isPublic = true): void
    {
        $this->settingService->set('theme', $key, $value, $dataType, $isPublic);
        $this->cacheService->invalidateTheme();
    }

    public function settings(): Collection
    {
        $settings = $this->cacheService->remember(
            CacheKeyRegistry::THEME_SETTINGS,
            fn () => array_replace(
                $this->repository->all()->toArray(),
                $this->settingService->getGroup('theme')
            )
        );

        return collect(is_array($settings) ? $settings : []);
    }

    public function mediaUrl(string $key): ?string
    {
        return $this->cacheService->remember(CacheKeyRegistry::themeMedia($key), function () use ($key) {
            $mediaId = $this->get($key);

            if (! $mediaId) {
                return null;
            }

            $media = Media::find($mediaId);

            return $media ? $this->mediaService->url($media) : null;
        });
    }
}
