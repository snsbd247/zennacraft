<?php

namespace App\Modules\Recovery\Models;

use App\Modules\Performance\Services\CacheService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Communication\Models\CommunicationMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class CheckoutRecovery extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'address',
        'quantity',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushMarketingCache());
        static::deleted(fn (): null => static::flushMarketingCache());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'recovery_id')->latest();
    }

    protected static function flushMarketingCache(): null
    {
        try {
            $cacheService = app(CacheService::class);
            $cacheService->invalidateMarketingCommandCenter();
            $cacheService->invalidateAnalyticsDashboard();
        } catch (Throwable) {
            // Recovery cache invalidation must never interrupt checkout recovery writes.
        }

        return null;
    }
}
