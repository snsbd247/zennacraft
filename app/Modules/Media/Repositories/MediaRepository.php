<?php

namespace App\Modules\Media\Repositories;

use App\Modules\Media\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MediaRepository
{
    public function create(array $data): Media
    {
        return Media::create($data);
    }

    public function find(int $id): ?Media
    {
        return Media::find($id);
    }

    public function delete(Media|int $media): bool
    {
        $media = $media instanceof Media ? $media : $this->find($media);

        return $media ? (bool) $media->delete() : false;
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Media::with('uploadedBy')->latest('created_at')->paginate($perPage);
    }

    public function search(?string $query = null, int $perPage = 20): LengthAwarePaginator
    {
        return Media::with('uploadedBy')
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('original_name', 'like', "%{$query}%")
                        ->orWhere('filename', 'like', "%{$query}%")
                        ->orWhere('mime_type', 'like', "%{$query}%");
                });
            })
            ->latest('created_at')
            ->paginate($perPage);
    }
}
