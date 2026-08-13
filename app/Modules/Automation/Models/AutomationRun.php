<?php

namespace App\Modules\Automation\Models;

use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class AutomationRun extends Model
{
    public const STATUSES = [
        'pending',
        'running',
        'completed',
        'skipped',
        'failed',
    ];

    protected $fillable = [
        'automation_workflow_id',
        'subject_type',
        'subject_id',
        'status',
        'trigger_key',
        'result_summary',
        'metadata',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushAutomationCache());
        static::deleted(fn (): null => static::flushAutomationCache());
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'automation_workflow_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class);
    }

    protected static function flushAutomationCache(): null
    {
        try {
            $cacheService = app(CacheService::class);
            $cacheService->invalidateAutomation();
            $cacheService->invalidateAnalyticsDashboard();
        } catch (Throwable) {
            // Automation cache invalidation should never interrupt run writes.
        }

        return null;
    }
}
