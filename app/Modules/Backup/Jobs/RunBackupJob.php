<?php

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A full backup (database + media zip) can take well past a web request's
 * connection timeout on shared hosting — that's exactly what happened when
 * "Run now" in Studio called BackupService synchronously: LiteSpeed killed
 * the request mid-zip and left the BackupRun stuck at status=running
 * forever, since nothing ever got the chance to update it. Running it on
 * the queue (already polled every minute via cron) decouples the backup
 * from any HTTP request's lifetime entirely.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(private array $scopes = ['database', 'files'], private ?int $staffId = null) {}

    public function handle(BackupService $service): void
    {
        $service->runAndShip($this->scopes, $this->staffId);
    }
}
