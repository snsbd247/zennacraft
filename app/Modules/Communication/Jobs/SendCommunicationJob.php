<?php

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendCommunicationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    // Seconds to wait before each retry: 10s, then 30s, then 90s.
    public array $backoff = [10, 30, 90];

    public function __construct(public int $messageId) {}

    public function handle(CommunicationService $communicationService): void
    {
        $message = CommunicationMessage::find($this->messageId);

        if (! $message) {
            return;
        }

        $communicationService->send($message);
        $message->refresh();

        if ($message->status === 'failed') {
            // CommunicationService::send() catches every driver/channel
            // failure internally and already records the reason on the
            // message itself — it never lets an exception reach here on
            // its own. Throwing is what makes $tries/$backoff actually
            // retry a transient provider failure (timeout, temporary 5xx)
            // instead of giving up after a single attempt, and is what
            // puts the eventual failure into Laravel's own failed_jobs
            // table once retries are exhausted — see failed() below.
            throw new RuntimeException($message->error_message ?? 'Communication message send failed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = CommunicationMessage::find($this->messageId);

        if (! $message || $message->status === 'sent') {
            return;
        }

        // Safety net: send() should already have marked the message
        // failed with a reason before the last retry threw. This just
        // guarantees the message reflects final failure even if that
        // didn't happen for some reason (e.g. the row changed between
        // attempts).
        $message->update([
            'status' => 'failed',
            'failed_at' => $message->failed_at ?? now(),
            'error_message' => $message->error_message ?: ($exception?->getMessage() ?? 'Communication job failed after retries.'),
        ]);
    }
}
