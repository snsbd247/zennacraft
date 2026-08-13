<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'package_type' => $this->package_type,
            'badge' => $this->badge,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'stock' => $this->stock,
            'status' => $this->status,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'is_featured' => $this->is_featured,
            'option_values' => $this->option_values,
            'image' => $this->whenLoaded('image', fn () => $this->mediaToArray($this->image)),
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
