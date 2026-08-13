<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'image' => $this->whenLoaded('image', fn () => $this->mediaToArray($this->image)),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }

    protected function mediaToArray($media): ?array
    {
        if (! $media) {
            return null;
        }

        return [
            'id' => $media->id,
            'url' => Storage::disk($media->disk)->url($media->directory.'/'.$media->filename),
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }
}
