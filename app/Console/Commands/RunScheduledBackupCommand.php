<?php

namespace App\Console\Commands;

use App\Modules\Backup\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class RunScheduledBackupCommand extends Command
{
    protected $signature = 'backup:run {--force : Run even if scheduled backups are disabled in Studio}';

    protected $description = 'Create a full backup and upload it to Dropbox if configured — used by the daily schedule and the Studio "Run now" button';

    public function handle(BackupService $service): int
    {
        if (! $this->option('force') && ! $service->isScheduleEnabled()) {
            $this->info('Scheduled backups are disabled in Studio — skipping.');

            return self::SUCCESS;
        }

        try {
            $backup = $service->runAndShip();
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup #{$backup->id} completed — offsite status: ".($backup->offsite_status ?? 'not configured'));

        return self::SUCCESS;
    }
}
