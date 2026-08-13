<?php

namespace App\Modules\Facebook\Models;

use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class FacebookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_name',
        'event_source_url',
        'event_time',
        'customer_phone_hash',
        'customer_email_hash',
        'order_id',
        'payload',
        'response',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'event_time' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushMarketingCommandCache());
        static::deleted(fn (): null => static::flushMarketingCommandCache());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function flushMarketingCommandCache(): null
    {
        try {
            app(CacheService::class)->invalidateMarketingCommandCenter();
        } catch (Throwable) {
            // Marketing cache invalidation must never interrupt Facebook event writes.
        }

        return null;
    }
}
