<?php

namespace App\Modules\Deployment\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeploymentRun extends Model
{
    protected $fillable = [
        'status',
        'progress',
        'from_commit',
        'to_commit',
        'commits_pulled',
        'migrations_ran',
        'composer_ran',
        'created_by',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'commits_pulled' => 'integer',
        'migrations_ran' => 'boolean',
        'composer_ran' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class)->latest();
    }
}
