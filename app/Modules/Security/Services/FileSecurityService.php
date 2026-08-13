<?php

namespace App\Modules\Security\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class FileSecurityService
{
    protected const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected const MAX_KILOBYTES = 5120;

    public function validateUpload(UploadedFile $file): void
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));

        if (str_contains($originalName, "\0") || basename(str_replace('\\', '/', $originalName)) !== $originalName) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded filename is not valid.',
            ]);
        }

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file extension is not allowed.',
            ]);
        }

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file type is not allowed.',
            ]);
        }

        if (($file->getSize() ?: 0) > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file may not be greater than 5MB.',
            ]);
        }

        $path = $file->getRealPath();

        if (! $path || @getimagesize($path) === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file must be a readable image.',
            ]);
        }
    }

    public function allowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    public function allowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    public function maxKilobytes(): int
    {
        return self::MAX_KILOBYTES;
    }

}
