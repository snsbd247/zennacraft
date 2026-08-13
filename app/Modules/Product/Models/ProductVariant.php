<?php

namespace App\Modules\Product\Models;

use App\Modules\Inventory\Models\VariantInventoryLog;
use App\Modules\Media\Models\Media;
use App\Modules\Review\Models\ProductReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'product_color_id',
        'product_size_id',
        'image_id',
        'name',
        'badge',
        'short_description',
        'package_type',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'stock',
        'weight',
        'dimensions',
        'sort_order',
        'is_featured',
        'show_on_storefront',
        'status',
        'option_values',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
        'show_on_storefront' => 'boolean',
        'option_values' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(VariantInventoryLog::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_variant_id');
    }
}
