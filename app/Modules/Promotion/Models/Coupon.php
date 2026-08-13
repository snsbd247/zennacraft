<?php

namespace App\Modules\Promotion\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use InvalidatesAnalyticsCache;

    public const DISCOUNT_TYPES = [
        'fixed',
        'percentage',
        'free_shipping',
    ];

    public const APPLIES_TO = [
        'all',
        'product',
        'category',
        'landing_page',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'usage_limit',
        'usage_limit_per_customer',
        'starts_at',
        'ends_at',
        'status',
        'applies_to',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CouponTarget::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }
}
