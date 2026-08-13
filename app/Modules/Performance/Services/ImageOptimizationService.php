<?php

namespace App\Modules\Performance\Services;

use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;

class ImageOptimizationService
{
    public function __construct(private MediaService $mediaService) {}

    public function attributes(
        ?Media $media,
        string $alt,
        string $class = '',
        bool $eager = false,
        string $sizes = '100vw'
    ): array {
        if (! $media) {
            return [];
        }

        return array_filter([
            'src' => $this->mediaService->url($media),
            'alt' => $media->alt_text ?: $alt,
            'class' => $class,
            'loading' => $eager ? 'eager' : 'lazy',
            'decoding' => 'async',
            'width' => $media->width,
            'height' => $media->height,
            'sizes' => $sizes,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function webpAudit(): array
    {
        return [
            'webp_images' => Media::where('extension', 'webp')->count(),
            'raster_images' => Media::whereIn('extension', ['jpg', 'jpeg', 'png', 'webp'])->count(),
            'svg_images' => Media::where('extension', 'svg')->count(),
        ];
    }
}
