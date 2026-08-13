<?php

namespace App\Modules\Media\Services;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Repositories\MediaRepository;
use App\Modules\Security\Services\FileSecurityService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaService
{
    public function __construct(
        private MediaRepository $repository,
        private FileSecurityService $fileSecurityService,
        private WatermarkService $watermarkService,
    ) {}

    public function upload(UploadedFile $file, ?string $altText = null, ?StaffUser $uploadedBy = null, ?string $context = null): Media
    {
        $this->fileSecurityService->validateUpload($file);

        $disk = 'public';
        $directory = 'uploads/'.now()->format('Y/m');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = (string) Str::uuid().'.'.$extension;

        $stored = $file->storeAs($directory, $filename, $disk);

        if (! $stored) {
            throw new RuntimeException('Unable to store uploaded media.');
        }

        [$width, $height] = $this->imageDimensions($file);

        $media = $this->repository->create([
            'disk' => $disk,
            'directory' => $directory,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $altText,
            'uploaded_by' => $uploadedBy?->id ?? auth()->guard('staff')->id(),
        ]);

        if ($this->watermarkService->shouldApplyWatermark($file, $context ?? 'general_media')) {
            return $this->watermarkService->applyWatermark($media, $context ?? 'general_media');
        }

        return $media;
    }

    public function delete(Media $media): bool
    {
        Storage::disk($media->disk)->delete($this->path($media));

        if ($media->original_path) {
            Storage::disk($media->disk)->delete($media->original_path);
        }

        return $this->repository->delete($media);
    }

    public function url(Media $media): string
    {
        return Storage::disk($media->disk)->url($this->path($media));
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    protected function imageDimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return [null, null];
        }

        $path = $file->getRealPath();
        $dimensions = $path ? @getimagesize($path) : false;

        if (! $dimensions) {
            return [null, null];
        }

        return [$dimensions[0] ?? null, $dimensions[1] ?? null];
    }

    protected function path(Media $media): string
    {
        return trim($media->directory.'/'.$media->filename, '/');
    }
}
