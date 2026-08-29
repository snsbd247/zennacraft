<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Media\Models\Media;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_id',
        'status',
        'sort_order',
        'discount_percent',
        'merchant_commission',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_content',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'merchant_commission' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
