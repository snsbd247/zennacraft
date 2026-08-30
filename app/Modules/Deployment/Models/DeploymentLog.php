<?php

namespace App\Modules\Deployment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentLog extends Model
{
    protected $fillable = [
        'deployment_run_id',
        'level',
        'step',
        'message',
    ];

    public function deploymentRun(): BelongsTo
    {
        return $this->belongsTo(DeploymentRun::class);
    }
}
