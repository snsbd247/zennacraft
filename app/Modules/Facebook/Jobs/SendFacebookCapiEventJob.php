<?php

namespace App\Modules\Facebook\Jobs;

use App\Modules\Facebook\Models\FacebookEvent;
use App\Modules\Facebook\Services\FacebookTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Performs the actual blocking HTTP call to Meta's Graph API. Mirrors
 * SendCommunicationJob's retry/failure pattern exactly: FacebookTrackingService
 * ::sendToCapi() catches every failure internally and marks the FacebookEvent
 * 'failed' without throwing, so handle() throws on that outcome to make
 * $tries/$backoff actually retry a transient failure, and failed() is the
 * safety net once retries are exhausted.
 */
class SendFacebookCapiEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    // Seconds to wait before each retry: 10s, then 30s, then 90s.
    public array $backoff = [10, 30, 90];

    public function __construct(public int $facebookEventId) {}

    public function handle(FacebookTrackingService $service): void
    {
        $event = FacebookEvent::find($this->facebookEventId);

        if (! $event) {
            return;
        }

        $service->sendToCapi($event);
        $event->refresh();
        $service->logAudit($event);

        if ($event->status === 'failed') {
            throw new RuntimeException($event->error_message ?? 'Facebook CAPI send failed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $event = FacebookEvent::find($this->facebookEventId);

        if (! $event || in_array($event->status, ['sent', 'skipped'], true)) {
            return;
        }

        $event->update([
            'status' => 'failed',
            'error_message' => $event->error_message ?: ($exception?->getMessage() ?? 'Facebook CAPI job failed after retries.'),
        ]);

        logger()->warning('Facebook CAPI event failed after retries', [
            'facebook_event_id' => $event->id,
            'event_name' => $event->event_name,
            'error' => $event->error_message,
        ]);
    }
}
