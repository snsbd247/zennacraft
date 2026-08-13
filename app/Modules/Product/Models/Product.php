<?php

namespace App\Modules\Product\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Combo\Models\Combo;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Media\Models\Media;
use App\Modules\Review\Models\ProductReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /**
     * Studio product list stock pill threshold — a product (or its
     * variant total) at or below this is "Low stock", above it is "In
     * stock". A simple fixed default rather than a new per-shop Setting,
     * matching the mockup's plain in/low/out three-way pill.
     */
    public const LOW_STOCK_THRESHOLD = 15;

    protected $fillable = [
        'category_id',
        'brand_id',
        'thumbnail_id',
        'size_chart_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'video_url',
        'craft_story',
        'materials',
        'artisan_origin',
        'dimensions',
        'care_guide',
        'faq_json',
        'price',
        'compare_price',
        'cost_price',
        'reseller_price',
        'stock',
        'status',
        'is_bestseller',
        'is_featured',
        'free_delivery',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'faq_json' => 'array',
        'is_bestseller' => 'boolean',
        'is_featured' => 'boolean',
        'free_delivery' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\Brand::class);
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'thumbnail_id');
    }

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'size_chart_id');
    }

    public function galleryMedia(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media')
            ->withPivot(['collection', 'sort_order'])
            ->withTimestamps()
            ->wherePivot('collection', 'gallery')
            ->orderBy('product_media.sort_order');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('name');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('sort_order');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class)->orderBy('sort_order');
    }

    public function storefrontVariants(): HasMany
    {
        return $this->variants()
            ->where('status', 'active')
            ->where('show_on_storefront', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(Combo::class, 'combo_items')
            ->withPivot(['quantity', 'sort_order'])
            ->withTimestamps();
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->approved();
    }
}
