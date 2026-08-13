<?php

namespace App\Modules\Backup\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupRun extends Model
{
    protected $fillable = [
        'backup_type',
        'backup_scope',
        'status',
        'disk',
        'directory',
        'database_path',
        'files_path',
        'manifest_path',
        'total_size',
        'checksum',
        'validation_status',
        'validation_message',
        'restore_ready',
        'error_message',
        'metadata',
        'created_by',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'restore_ready' => 'boolean',
        'metadata' => 'array',
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
        return $this->hasMany(BackupLog::class)->latest();
    }
}
