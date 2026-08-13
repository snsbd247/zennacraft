<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'thumbnail_id' => $this->thumbnail_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'craft_story' => $this->craft_story,
            'materials' => $this->materials,
            'dimensions' => $this->dimensions,
            'care_guide' => $this->care_guide,
            'faq' => $this->faq_json,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'stock' => $this->stock,
            'status' => $this->status,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'thumbnail' => $this->whenLoaded('thumbnail', fn () => $this->mediaToArray($this->thumbnail)),
            'gallery' => $this->whenLoaded('galleryMedia', fn () => $this->galleryMedia->map(fn ($media) => $this->mediaToArray($media))->values()),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
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
