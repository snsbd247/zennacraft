<?php

namespace App\Modules\Communication\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Communication\Contracts\CommunicationChannel;
use App\Modules\Communication\Jobs\SendCommunicationJob;
use App\Modules\Communication\Models\CommunicationLog;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\Channels\EmailChannel;
use App\Modules\Communication\Services\Channels\InternalChannel;
use App\Modules\Communication\Services\Channels\MessengerChannel;
use App\Modules\Communication\Services\Channels\SmsChannel;
use App\Modules\Communication\Services\Channels\WhatsappChannel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

class CommunicationService
{
    public function __construct(
        private TemplateEngineService $templates,
        private AuditService $auditService,
        private CacheService $cacheService,
        private PhoneService $phoneService,
    ) {}

    public function createFromTemplate(
        string $channel,
        string $recipient,
        string $template,
        array $variables = [],
        array $context = []
    ): CommunicationMessage {
        $rendered = $this->templates->render($template, $variables);
        $recipient = trim($recipient);

        $message = CommunicationMessage::create([
            'customer_id' => $context['customer_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'recovery_id' => $context['recovery_id'] ?? null,
            'coupon_id' => $context['coupon_id'] ?? null,
            'channel' => $channel,
            'recipient' => $recipient,
            'recipient_hash' => $this->recipientHash($recipient),
            'template' => $rendered->key,
            'subject' => $rendered->subject,
            'body' => $rendered->body,
            'variables' => $rendered->variables,
            'status' => 'pending',
        ]);

        $this->recordLog($message, 'pending', 'system', [
            'template' => $template,
            'context' => $this->safeContext($context),
        ]);
        $this->logAudit('communication.create', [
            'message_id' => $message->id,
            'channel' => $channel,
            'template' => $template,
            'customer_id' => $message->customer_id,
            'order_id' => $message->order_id,
            'recovery_id' => $message->recovery_id,
            'coupon_id' => $message->coupon_id,
            'recipient_hash' => $message->recipient_hash,
        ]);

        return $message->refresh();
    }

    /**
     * @param  string|null  $queueTier  Force a priority tier ('otp'/'transactional'/'bulk')
     *                                  instead of inferring one from the message's template.
     *                                  Only used by call sites that already structurally know
     *                                  they're a many-recipient blast regardless of which
     *                                  template ends up attached (campaign/segment/coupon
     *                                  fan-out) — see CommunicationMessage::queueTier().
     */
    public function queueMessage(CommunicationMessage $message, bool $dispatch = false, ?string $queueTier = null): CommunicationMessage
    {
        if ($message->status !== 'queued') {
            $message->update([
                'status' => 'queued',
                'queued_at' => $message->queued_at ?? now(),
            ]);

            $this->recordLog($message, 'queued', 'queue');
            $this->logAudit('communication.queue', [
                'message_id' => $message->id,
                'channel' => $message->channel,
                'template' => $message->template,
                'customer_id' => $message->customer_id,
                'order_id' => $message->order_id,
            ]);
        }

        if ($dispatch) {
            $tier = $queueTier ?? $message->queueTier();
            $queueName = (string) config('queue_priorities.'.$tier, config('queue_priorities.bulk'));

            SendCommunicationJob::dispatch($message->id)->onQueue($queueName);
        }

        return $message->refresh();
    }

    public function send(CommunicationMessage $message): CommunicationMessage
    {
        $channel = $this->channel($message->channel);

        if (! $channel || ! $channel->enabled()) {
            $response = [
                'sent' => false,
                'message' => 'Communication channel is disabled or unavailable.',
            ];

            $this->markFailed($message, 'Communication channel disabled.', $response);

            return $message->refresh();
        }

        try {
            $response = $channel->send($message);

            if (($response['sent'] ?? false) !== true) {
                $this->markFailed($message, $response['message'] ?? 'Communication provider did not send the message.', $response);

                return $message->refresh();
            }

            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider_response' => $response,
                'error_message' => null,
            ]);

            $this->recordLog($message, 'sent', $response['provider'] ?? $channel->name(), $response);
            $this->logAudit('communication.send', [
                'message_id' => $message->id,
                'channel' => $message->channel,
                'template' => $message->template,
                'customer_id' => $message->customer_id,
                'order_id' => $message->order_id,
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($message, $exception->getMessage(), [
                'exception' => class_basename($exception),
            ]);
        }

        return $message->refresh();
    }

    public function generateOrderStatusMessage(Order $order, string $status): ?CommunicationMessage
    {
        $template = 'order_'.$status;

        if (! array_key_exists($template, $this->templates->templates())) {
            return null;
        }

        $recipient = $order->customer_email ?: $order->customer_phone;

        if (! filled($recipient)) {
            return null;
        }

        return $this->createFromTemplate(
            $order->customer_email ? 'email' : 'sms',
            (string) $recipient,
            $template,
            $this->orderVariables($order),
            [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'trigger' => 'order_status:'.$status,
            ]
        );
    }

    public function generateRecoveryWorkflow(CheckoutRecovery $recovery, string $template = 'abandoned_checkout'): ?CommunicationMessage
    {
        if (! filled($recovery->customer_phone) && ! filled($recovery->customer_email)) {
            return null;
        }

        $existing = CommunicationMessage::query()
            ->where('recovery_id', $recovery->id)
            ->where('template', $template)
            ->exists();

        if ($existing) {
            return null;
        }

        return $this->createFromTemplate(
            $recovery->customer_email ? 'email' : 'sms',
            (string) ($recovery->customer_email ?: $recovery->customer_phone),
            $template,
            [
                'customer_name' => $recovery->customer_name ?: 'Customer',
            ],
            [
                'recovery_id' => $recovery->id,
                'trigger' => 'checkout_recovery',
            ]
        );
    }

    public function queueCouponCampaign(Coupon $coupon, Collection|array $customers, string $template = 'coupon_campaign'): int
    {
        $queued = 0;

        foreach ($customers as $customer) {
            if (! $customer instanceof Customer || (! filled($customer->email) && ! filled($customer->phone))) {
                continue;
            }

            $message = $this->createFromTemplate(
                $customer->email ? 'email' : 'sms',
                (string) ($customer->email ?: $customer->phone),
                $template,
                [
                    'customer_name' => $customer->name,
                    'coupon_code' => $coupon->code,
                ],
                [
                    'customer_id' => $customer->id,
                    'coupon_id' => $coupon->id,
                    'trigger' => 'coupon_campaign',
                ]
            );

            // Explicit bulk tier: this always fans out to a customer list,
            // regardless of which template is passed in.
            $this->queueMessage($message, true, CommunicationMessage::QUEUE_TIER_BULK);
            $queued++;
        }

        return $queued;
    }

    public function queueForMarketingSegment(
        MarketingSegment $segment,
        string $templateKey,
        array $variables = []
    ): int {
        $queued = 0;

        $segment->memberships()
            ->with('customer')
            ->orderBy('id')
            ->chunkById(100, function ($memberships) use (&$queued, $templateKey, $variables, $segment): void {
                foreach ($memberships as $membership) {
                    $customer = $membership->customer;

                    if (! $customer instanceof Customer || (! filled($customer->email) && ! filled($customer->phone))) {
                        continue;
                    }

                    $message = $this->createFromTemplate(
                        $customer->email ? 'email' : 'sms',
                        (string) ($customer->email ?: $customer->phone),
                        $templateKey,
                        array_merge($variables, [
                            'customer_name' => $customer->name,
                            'segment_name' => $segment->name,
                        ]),
                        [
                            'customer_id' => $customer->id,
                            'trigger' => 'marketing_segment:'.$segment->slug,
                        ]
                    );

                    // Explicit bulk tier: always fans out to a whole segment.
                    $this->queueMessage($message, true, CommunicationMessage::QUEUE_TIER_BULK);
                    $queued++;
                }
            });

        $this->logAudit('marketing.segment.communication.queue', [
            'segment_id' => $segment->id,
            'template' => $templateKey,
            'count' => $queued,
        ]);

        return $queued;
    }

    public function queueForMarketingSegmentId(int $segmentId, string $templateKey, array $variables = []): int
    {
        $segment = MarketingSegment::query()->find($segmentId);

        return $segment ? $this->queueForMarketingSegment($segment, $templateKey, $variables) : 0;
    }

    public function queueForMarketingSegmentSlug(string $slug, string $templateKey, array $variables = []): int
    {
        $slug = trim($slug);
        $segment = MarketingSegment::query()
            ->whereIn('slug', array_values(array_unique([$slug, strtoupper($slug)])))
            ->first();

        return $segment ? $this->queueForMarketingSegment($segment, $templateKey, $variables) : 0;
    }

    public function dashboard(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::COMMUNICATION_DASHBOARD,
            fn (): array => [
                'total' => CommunicationMessage::count(),
                'pending' => CommunicationMessage::where('status', 'pending')->count(),
                'queued' => CommunicationMessage::where('status', 'queued')->count(),
                'sent' => CommunicationMessage::where('status', 'sent')->count(),
                'delivered' => CommunicationMessage::where('status', 'delivered')->count(),
                'failed' => CommunicationMessage::where('status', 'failed')->count(),
                'email' => CommunicationMessage::where('channel', 'email')->count(),
                'sms' => CommunicationMessage::where('channel', 'sms')->count(),
                'whatsapp' => CommunicationMessage::where('channel', 'whatsapp')->count(),
                'internal' => CommunicationMessage::where('channel', 'internal')->count(),
                'messenger' => CommunicationMessage::where('channel', 'messenger')->count(),
            ],
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::COMMUNICATION_TAG]
        );
    }

    public function paginatedMessages(int $perPage = 20, ?string $status = null): LengthAwarePaginator
    {
        return CommunicationMessage::with(['customer', 'order', 'logs'])
            ->when(
                $status !== null && in_array($status, CommunicationMessage::STATUSES, true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage, ['*'], 'messages_page')
            ->withQueryString();
    }

    public function recentLogs(int $limit = 20): Collection
    {
        return CommunicationLog::with('message')
            ->latest('logged_at')
            ->limit($limit)
            ->get();
    }

    public function channelEnabled(string $channel): bool
    {
        return (bool) $this->channel($channel)?->enabled();
    }

    public function channels(): array
    {
        return [
            'email' => app(EmailChannel::class),
            'sms' => app(SmsChannel::class),
            'whatsapp' => app(WhatsappChannel::class),
            'internal' => app(InternalChannel::class),
            'messenger' => app(MessengerChannel::class),
        ];
    }

    protected function channel(string $channel): ?CommunicationChannel
    {
        return $this->channels()[$channel] ?? null;
    }

    protected function markFailed(CommunicationMessage $message, string $error, array $response = []): void
    {
        $message->update([
            'status' => 'failed',
            'failed_at' => now(),
            'provider_response' => $response,
            'error_message' => $error,
        ]);

        $this->recordLog($message, 'failed', $response['provider'] ?? null, $response, $error);
        $this->logAudit('communication.fail', [
            'message_id' => $message->id,
            'channel' => $message->channel,
            'template' => $message->template,
            'customer_id' => $message->customer_id,
            'order_id' => $message->order_id,
        ]);
    }

    protected function recordLog(
        CommunicationMessage $message,
        string $status,
        ?string $provider = null,
        array $providerResponse = [],
        ?string $error = null
    ): CommunicationLog {
        return CommunicationLog::create([
            'communication_message_id' => $message->id,
            'channel' => $message->channel,
            'status' => $status,
            'provider' => $provider,
            'provider_response' => $providerResponse !== [] ? $providerResponse : null,
            'error_message' => $error,
            'logged_at' => now(),
        ]);
    }

    protected function logAudit(string $action, array $metadata = []): void
    {
        try {
            $this->auditService->log(
                'communication',
                $action,
                'communication',
                'Communication event recorded.',
                $metadata
            );
        } catch (Throwable $exception) {
            logger()->warning('Communication audit logging failed', [
                'action' => $action,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function recipientHash(string $recipient): ?string
    {
        if ($recipient === '') {
            return null;
        }

        if (str_contains($recipient, '@')) {
            return hash('sha256', strtolower(trim($recipient)));
        }

        $normalized = $this->phoneService->normalize($recipient);

        return $this->phoneService->hash($normalized !== '' ? $normalized : $recipient);
    }

    protected function orderVariables(Order $order): array
    {
        $order->loadMissing('shipment');

        return [
            'customer_name' => $order->customer_name ?: 'Customer',
            'order_number' => $order->order_number,
            'tracking_number' => $order->shipment?->tracking_number ?? '',
        ];
    }

    protected function safeContext(array $context): array
    {
        return collect($context)
            ->except(['recipient', 'phone', 'email', 'address', 'otp', 'token'])
            ->all();
    }
}
