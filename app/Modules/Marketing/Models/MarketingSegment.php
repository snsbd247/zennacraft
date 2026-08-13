<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class MarketingSegment extends Model
{
    public const TYPE_SYSTEM = 'system';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'rules_json',
        'active',
        'last_evaluated_at',
    ];

    protected $casts = [
        'rules_json' => 'array',
        'active' => 'boolean',
        'last_evaluated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushMarketingSegmentCache());
        static::deleted(fn (): null => static::flushMarketingSegmentCache());
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MarketingSegmentMembership::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'marketing_segment_memberships')
            ->withPivot(['joined_at', 'last_evaluated_at'])
            ->withTimestamps();
    }

    protected static function flushMarketingSegmentCache(): null
    {
        try {
            app(CacheService::class)->invalidateMarketingSegments();
        } catch (Throwable) {
            // Segment cache invalidation must not interrupt writes.
        }

        return null;
    }
}
