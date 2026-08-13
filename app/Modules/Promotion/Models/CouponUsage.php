<?php

namespace App\Modules\Promotion\Models;

use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'coupon_id',
        'order_id',
        'customer_id',
        'code',
        'discount_amount',
        'used_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'used_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
