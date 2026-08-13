<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class MarketingCampaignLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'marketing_campaign_id',
        'log_type',
        'message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushCampaignCache());
        static::deleted(fn (): null => static::flushCampaignCache());
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    protected static function flushCampaignCache(): null
    {
        try {
            app(CacheService::class)->invalidateMarketingCampaigns();
        } catch (Throwable) {
            // Campaign cache invalidation must never interrupt log writes.
        }

        return null;
    }
}
