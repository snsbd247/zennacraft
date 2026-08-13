<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class MarketingSegmentMembership extends Model
{
    protected static bool $suppressAutomation = false;

    protected $fillable = [
        'marketing_segment_id',
        'customer_id',
        'joined_at',
        'last_evaluated_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_evaluated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushMarketingSegmentCache());
        static::created(function (MarketingSegmentMembership $membership): void {
            static::triggerAutomation('customer.segment_entered', $membership);
        });
        static::deleted(function (MarketingSegmentMembership $membership): void {
            static::flushMarketingSegmentCache();
            static::triggerAutomation('customer.segment_left', $membership);
        });
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'marketing_segment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function withoutAutomation(callable $callback): mixed
    {
        $previous = static::$suppressAutomation;
        static::$suppressAutomation = true;

        try {
            return $callback();
        } finally {
            static::$suppressAutomation = $previous;
        }
    }

    protected static function flushMarketingSegmentCache(): null
    {
        try {
            app(CacheService::class)->invalidateMarketingSegments();
        } catch (Throwable) {
            // Segment cache invalidation must not interrupt membership writes.
        }

        return null;
    }

    protected static function triggerAutomation(string $triggerKey, MarketingSegmentMembership $membership): void
    {
        if (static::$suppressAutomation) {
            return;
        }

        try {
            $membership->loadMissing(['customer', 'segment']);

            if (! $membership->customer) {
                return;
            }

            app(AutomationEngineService::class)->handleTrigger($triggerKey, $membership->customer, [
                'marketing_segment_id' => $membership->marketing_segment_id,
                'segment_slug' => $membership->segment?->slug,
                'membership_id' => $membership->id,
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment automation trigger failed', [
                'trigger_key' => $triggerKey,
                'membership_id' => $membership->id,
                'customer_id' => $membership->customer_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
