<?php

namespace App\Modules\Media\Services;

use App\Modules\Media\Models\Media;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WatermarkService
{
    protected const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected const POSITIONS = [
        'bottom_right',
        'bottom_left',
        'top_right',
        'top_left',
        'center',
    ];

    public function __construct(private SettingService $settingService) {}

    public function shouldApplyWatermark(UploadedFile $file, ?string $context = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));

        return in_array($extension, self::SUPPORTED_EXTENSIONS, true)
            && str_starts_with($mimeType, 'image/')
            && $this->contextAllowed($context);
    }

    public function applyWatermark(Media $media, ?string $context = null): Media
    {
        if (! $this->enabled() || $media->watermark_applied || ! $this->mediaSupported($media) || ! $this->contextAllowed($context)) {
            return $media;
        }

        try {
            if (! extension_loaded('gd')) {
                throw new \RuntimeException('GD extension is not available.');
            }

            $disk = Storage::disk($media->disk);
            $path = $this->mediaPath($media);

            if (! $disk->exists($path)) {
                throw new \RuntimeException('Media file does not exist on disk.');
            }

            $absolutePath = $disk->path($path);
            $originalPath = $media->original_path ?: $this->originalPath($media);

            $disk->makeDirectory(dirname($originalPath));

            if (! $media->original_path && ! $disk->copy($path, $originalPath)) {
                throw new \RuntimeException('Unable to preserve original media before watermarking.');
            }

            $image = $this->createImage($absolutePath, strtolower($media->extension));

            if (! $image) {
                throw new \RuntimeException('Unable to read media image for watermarking.');
            }

            $this->drawWatermark($image, $this->watermarkText());

            $temporaryPath = $absolutePath.'.watermark.tmp';

            if (! $this->saveImage($image, $temporaryPath, strtolower($media->extension))) {
                @unlink($temporaryPath);
                throw new \RuntimeException('Unable to write watermarked media image.');
            }

            if (! @rename($temporaryPath, $absolutePath)) {
                @unlink($temporaryPath);
                throw new \RuntimeException('Unable to replace media with watermarked image.');
            }

            clearstatcache(true, $absolutePath);
            $dimensions = @getimagesize($absolutePath) ?: [];

            $media->forceFill([
                'watermark_applied' => true,
                'watermark_text' => $this->watermarkText(),
                'watermarked_at' => now(),
                'original_path' => $originalPath,
                'size' => @filesize($absolutePath) ?: $media->size,
                'width' => $dimensions[0] ?? $media->width,
                'height' => $dimensions[1] ?? $media->height,
            ])->save();

            return $media->refresh();
        } catch (Throwable $exception) {
            logger()->warning('Media watermark failed', [
                'media_id' => $media->id,
                'context' => $context,
                'error' => $exception->getMessage(),
            ]);

            return $media;
        }
    }

    public function watermarkText(): string
    {
        $text = (string) $this->settingService->get('general', 'watermark_text', '');

        if (trim($text) === '') {
            $text = (string) $this->settingService->get('general', 'site_name', config('app.name', 'Zenna Craft'));
        }

        $text = trim(strip_tags($text));
        $text = preg_replace('/[[:cntrl:]]+/', ' ', $text) ?: '';
        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 80);
        }

        return substr($text, 0, 80);
    }

    public function position(): string
    {
        $position = (string) $this->settingService->get('general', 'watermark_position', 'bottom_right');

        return in_array($position, self::POSITIONS, true) ? $position : 'bottom_right';
    }

    public function opacity(): int
    {
        return max(0, min(100, (int) $this->settingService->get('general', 'watermark_opacity', 35)));
    }

    protected function enabled(): bool
    {
        return filter_var(
            $this->settingService->get('general', 'watermark_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    protected function contextAllowed(?string $context): bool
    {
        $applyTo = (string) $this->settingService->get('general', 'watermark_apply_to', 'product_images');

        if ($applyTo === 'all_media') {
            return true;
        }

        return ($context ?: 'general_media') === 'product_images';
    }

    protected function mediaSupported(Media $media): bool
    {
        return in_array(strtolower((string) $media->extension), self::SUPPORTED_EXTENSIONS, true)
            && str_starts_with(strtolower((string) $media->mime_type), 'image/');
    }

    protected function mediaPath(Media $media): string
    {
        return trim($media->directory.'/'.$media->filename, '/');
    }

    protected function originalPath(Media $media): string
    {
        return trim($media->directory.'/originals/'.basename($media->filename), '/');
    }

    protected function createImage(string $path, string $extension): mixed
    {
        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    protected function saveImage(mixed $image, string $path, string $extension): bool
    {
        return match ($extension) {
            'jpg', 'jpeg' => @imagejpeg($image, $path, 88),
            'png' => @imagepng($image, $path, 6),
            'webp' => function_exists('imagewebp') ? @imagewebp($image, $path, 85) : false,
            default => false,
        };
    }

    protected function drawWatermark(mixed $image, string $text): void
    {
        if ($text === '') {
            return;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $padding = min(24, max(8, (int) floor(min($width, $height) * 0.04)));
        $alpha = 127 - (int) round(127 * ($this->opacity() / 100));
        $shadowAlpha = min(127, $alpha + 45);
        $font = $this->fontPath();

        if ($font) {
            $fontSize = max(12, min(42, (int) floor($width / 24)));
            $box = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            $textHeight = abs(($box[7] ?? 0) - ($box[1] ?? 0));
            [$x, $y] = $this->coordinates($width, $height, $textWidth, $textHeight, $padding);
            $baselineY = $y + $textHeight;
            $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, $shadowAlpha);
            $textColor = imagecolorallocatealpha($image, 255, 255, 255, $alpha);

            imagettftext($image, $fontSize, 0, $x + 2, $baselineY + 2, $shadowColor, $font, $text);
            imagettftext($image, $fontSize, 0, $x, $baselineY, $textColor, $font, $text);

            return;
        }

        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        [$x, $y] = $this->coordinates($width, $height, $textWidth, $textHeight, $padding);
        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, $shadowAlpha);
        $textColor = imagecolorallocatealpha($image, 255, 255, 255, $alpha);

        imagestring($image, $fontSize, $x + 1, $y + 1, $text, $shadowColor);
        imagestring($image, $fontSize, $x, $y, $text, $textColor);
    }

    protected function coordinates(int $width, int $height, int $textWidth, int $textHeight, int $padding): array
    {
        return match ($this->position()) {
            'bottom_left' => [$padding, max($padding, $height - $textHeight - $padding)],
            'top_right' => [max($padding, $width - $textWidth - $padding), $padding],
            'top_left' => [$padding, $padding],
            'center' => [max($padding, (int) floor(($width - $textWidth) / 2)), max($padding, (int) floor(($height - $textHeight) / 2))],
            default => [max($padding, $width - $textWidth - $padding), max($padding, $height - $textHeight - $padding)],
        };
    }

    protected function fontPath(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
        ] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
