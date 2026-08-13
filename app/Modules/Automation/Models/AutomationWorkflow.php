<?php

namespace App\Modules\Automation\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class AutomationWorkflow extends Model
{
    public const STATUSES = [
        'draft',
        'active',
        'paused',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'trigger_key',
        'status',
        'conditions_json',
        'actions_json',
        'last_run_at',
        'created_by',
    ];

    protected $casts = [
        'conditions_json' => 'array',
        'actions_json' => 'array',
        'last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushAutomationCache());
        static::deleted(fn (): null => static::flushAutomationCache());
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }

    protected static function flushAutomationCache(): null
    {
        try {
            $cacheService = app(CacheService::class);
            $cacheService->invalidateAutomation();
            $cacheService->invalidateAnalyticsDashboard();
        } catch (Throwable) {
            // Automation cache invalidation should never interrupt workflow writes.
        }

        return null;
    }
}
