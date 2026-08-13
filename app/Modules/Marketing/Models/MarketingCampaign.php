<?php

namespace App\Modules\Marketing\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class MarketingCampaign extends Model
{
    public const STATUSES = [
        'draft',
        'scheduled',
        'running',
        'completed',
        'paused',
        'cancelled',
    ];

    public const AUDIENCE_TYPES = [
        'all_customers',
        'segment',
        'customer_list',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'campaign_type',
        'status',
        'audience_type',
        'marketing_segment_id',
        'audience_json',
        'template_key',
        'starts_at',
        'ends_at',
        'total_recipients',
        'total_queued',
        'total_sent',
        'total_opened',
        'total_clicked',
        'total_converted',
        'total_revenue',
        'created_by',
    ];

    protected $casts = [
        'audience_json' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'total_revenue' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushCampaignCache());
        static::deleted(fn (): null => static::flushCampaignCache());
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MarketingCampaignLog::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'marketing_segment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }

    protected static function flushCampaignCache(): null
    {
        try {
            app(CacheService::class)->invalidateMarketingCampaigns();
        } catch (Throwable) {
            // Campaign cache invalidation must never interrupt campaign writes.
        }

        return null;
    }
}
