<?php

namespace App\Modules\Automation\Models;

use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class AutomationAction extends Model
{
    public const STATUSES = [
        'pending',
        'completed',
        'skipped',
        'failed',
    ];

    protected $fillable = [
        'automation_run_id',
        'action_key',
        'status',
        'result_summary',
        'metadata',
        'executed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushAutomationCache());
        static::deleted(fn (): null => static::flushAutomationCache());
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    protected static function flushAutomationCache(): null
    {
        try {
            $cacheService = app(CacheService::class);
            $cacheService->invalidateAutomation();
            $cacheService->invalidateAnalyticsDashboard();
        } catch (Throwable) {
            // Automation cache invalidation should never interrupt action writes.
        }

        return null;
    }
}
