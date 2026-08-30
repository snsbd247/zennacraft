<?php

namespace App\Modules\Deployment\Jobs;

use App\Modules\Deployment\Models\DeploymentRun;
use App\Modules\Deployment\Services\UpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * git pull + composer install + migrate can comfortably outlast a web
 * request's connection timeout on shared hosting (confirmed with the
 * Backup module's "Run now" button — same lesson applies here). The queue
 * worker, already polled every minute via cron, has no such limit.
 */
class RunUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public DeploymentRun $deploymentRun) {}

    public function handle(UpdateService $service): void
    {
        $service->runUpdate($this->deploymentRun);
    }
}
