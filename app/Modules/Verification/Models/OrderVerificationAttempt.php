<?php

namespace App\Modules\Verification\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class OrderVerificationAttempt extends Model
{
    protected $fillable = [
        'order_id',
        'staff_user_id',
        'outcome',
        'notes',
        'next_follow_up_at',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushAnalyticsCache());
        static::deleted(fn (): null => static::flushAnalyticsCache());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }

    protected static function flushAnalyticsCache(): null
    {
        try {
            app(CacheService::class)->invalidateAnalyticsDashboard();
        } catch (Throwable) {
            // Verification analytics cache invalidation must never interrupt attempt writes.
        }

        return null;
    }
}
