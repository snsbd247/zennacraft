<?php

use App\Modules\Backup\Models\BackupRun;
use App\Modules\Backup\Services\BackupService;
use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Customer\Services\CustomerOtpService;
use App\Modules\Deployment\Services\DeploymentHealthService;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Marketing\Services\MarketingCampaignService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    app(CustomerOtpService::class)->cleanupExpiredOtps();
})->hourly()->name('customer-otp-cleanup')->withoutOverlapping();

Schedule::call(function (): void {
    $backup = BackupRun::query()
        ->where('status', 'completed')
        ->latest('created_at')
        ->first();

    if ($backup) {
        app(BackupService::class)->validateBackup($backup);
    }
})->dailyAt('02:15')->name('backup-validation-check')->withoutOverlapping();

// Time + enabled/disabled are Studio-configurable. This must NOT touch the
// database at file-load time — routes/console.php is parsed on every app
// boot (including plain HTTP requests), long before a request-scoped
// connection/migration state can be assumed ready. So the whole thing runs
// every minute and the closure itself — evaluated only when the scheduler
// actually fires — decides whether this is the configured backup minute.
Schedule::call(function (): void {
    $service = app(BackupService::class);

    if (! $service->isScheduleEnabled() || now()->format('H:i') !== $service->scheduleTime()) {
        return;
    }

    Artisan::call('backup:run', ['--force' => true]);
})->everyMinute()->name('scheduled-database-backup')->withoutOverlapping();

Schedule::call(function (): void {
    $retentionDays = config('security.fraud_event_retention_days');

    if (! is_numeric($retentionDays) || (int) $retentionDays <= 0) {
        return;
    }

    FraudEvent::query()
        ->where('created_at', '<', now()->subDays((int) $retentionDays))
        ->delete();
})->dailyAt('03:15')->name('fraud-event-retention-cleanup')->withoutOverlapping();

Schedule::call(function (): void {
    Cache::put('deployment.health.snapshot', [
        'summary' => app(DeploymentHealthService::class)->summary(),
        'refreshed_at' => now()->toIso8601String(),
    ], now()->addHour());
})->hourly()->name('deployment-health-snapshot-refresh')->withoutOverlapping();

Schedule::call(function (): void {
    app(MarketingCampaignService::class)->runScheduledStarts();
})->everyFiveMinutes()->name('marketing-campaign-start')->withoutOverlapping();

Schedule::call(function (): void {
    app(MarketingCampaignService::class)->runScheduledCompletions();
})->everyFiveMinutes()->name('marketing-campaign-complete')->withoutOverlapping();

Schedule::call(function (): void {
    app(BehaviorEventService::class)->cleanupOldEvents();
})->dailyAt('03:45')->name('behavior-event-retention-cleanup')->withoutOverlapping();
