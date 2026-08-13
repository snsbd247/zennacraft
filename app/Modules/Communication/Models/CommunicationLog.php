<?php

namespace App\Modules\Communication\Models;

use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class CommunicationLog extends Model
{
    protected $fillable = [
        'communication_message_id',
        'channel',
        'status',
        'provider',
        'provider_response',
        'error_message',
        'logged_at',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'logged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushCommunicationDashboardCache());
        static::deleted(fn (): null => static::flushCommunicationDashboardCache());
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunicationMessage::class, 'communication_message_id');
    }

    protected static function flushCommunicationDashboardCache(): null
    {
        try {
            app(CacheService::class)->invalidateCommunicationDashboard();
        } catch (Throwable) {
            // Dashboard cache invalidation should never interrupt log writes.
        }

        return null;
    }
}
