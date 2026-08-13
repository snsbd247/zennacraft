<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Models\MarketingCampaignLog;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class MarketingCampaignService
{
    public function __construct(
        private AuditService $auditService,
        private CommunicationService $communicationService,
        private CacheService $cacheService,
    ) {}

    public function createCampaign(array $data): MarketingCampaign
    {
        $data = $this->normalizePayload($data);
        $data['created_by'] = auth()->guard('staff')->id();

        $campaign = MarketingCampaign::create($data);
        $this->logCampaign($campaign, 'created', 'Campaign created.', [
            'staff_id' => auth()->guard('staff')->id(),
        ]);
        $this->logAudit('marketing.campaign.create', [
            'staff_id' => auth()->guard('staff')->id(),
            'campaign_id' => $campaign->id,
        ]);

        return $campaign->refresh();
    }

    public function updateCampaign(MarketingCampaign $campaign, array $data): MarketingCampaign
    {
        $campaign->update($this->normalizePayload($data, $campaign));
        $this->logCampaign($campaign, 'updated', 'Campaign updated.', [
            'staff_id' => auth()->guard('staff')->id(),
        ]);
        $this->logAudit('marketing.campaign.update', [
            'staff_id' => auth()->guard('staff')->id(),
            'campaign_id' => $campaign->id,
        ]);

        return $campaign->refresh();
    }

    public function activateCampaign(MarketingCampaign $campaign): MarketingCampaign
    {
        $status = $campaign->starts_at && $campaign->starts_at->isFuture()
            ? 'scheduled'
            : 'running';

        $campaign->update(['status' => $status]);
        $this->logCampaign($campaign, 'activated', 'Campaign activated as '.$status.'.');
        $this->logAudit('marketing.campaign.activate', [
            'staff_id' => auth()->guard('staff')->id(),
            'campaign_id' => $campaign->id,
        ]);

        if ($status === 'running') {
            $this->queueCampaign($campaign->refresh());
        }

        return $campaign->refresh();
    }

    public function pauseCampaign(MarketingCampaign $campaign): MarketingCampaign
    {
        $campaign->update(['status' => 'paused']);
        $this->logCampaign($campaign, 'paused', 'Campaign paused.');
        $this->logAudit('marketing.campaign.pause', [
            'staff_id' => auth()->guard('staff')->id(),
            'campaign_id' => $campaign->id,
        ]);

        return $campaign->refresh();
    }

    public function cancelCampaign(MarketingCampaign $campaign): MarketingCampaign
    {
        $campaign->update(['status' => 'cancelled']);
        $this->logCampaign($campaign, 'cancelled', 'Campaign cancelled.');
        $this->logAudit('marketing.campaign.cancel', [
            'staff_id' => auth()->guard('staff')->id(),
            'campaign_id' => $campaign->id,
        ]);

        return $campaign->refresh();
    }

    public function audienceCount(MarketingCampaign|array $campaign): int
    {
        return (int) $this->audienceQuery($campaign)->count();
    }

    public function queueCampaign(MarketingCampaign $campaign): int
    {
        if (in_array($campaign->status, ['cancelled', 'completed'], true)) {
            $this->logCampaign($campaign, 'queue_skipped', 'Campaign is not queueable in its current status.');

            return 0;
        }

        $queued = 0;
        $audienceCount = $this->audienceCount($campaign);

        $this->audienceQuery($campaign)
            ->orderBy('id')
            ->chunkById(100, function ($customers) use (&$queued, $campaign): void {
                foreach ($customers as $customer) {
                    if (! $customer instanceof Customer || (! filled($customer->phone) && ! filled($customer->email))) {
                        continue;
                    }

                    if ($this->campaignMessageExists($campaign, $customer)) {
                        continue;
                    }

                    $channel = filled($customer->phone) ? 'sms' : 'email';
                    $template = $campaign->template_key ?: 'coupon_campaign';
                    $message = $this->communicationService->createFromTemplate(
                        $channel,
                        (string) ($customer->phone ?: $customer->email),
                        $template,
                        [
                            'customer_name' => $customer->name,
                            'campaign_id' => $campaign->id,
                            'campaign_name' => $campaign->name,
                            'segment_id' => $campaign->marketing_segment_id,
                            'template_key' => $template,
                        ],
                        [
                            'customer_id' => $customer->id,
                            'trigger' => 'marketing_campaign:'.$campaign->id,
                            'campaign_id' => $campaign->id,
                            'segment_id' => $campaign->marketing_segment_id,
                            'template_key' => $template,
                        ]
                    );

                    // Always the bulk tier explicitly: this loop fans a
                    // campaign out to its whole audience regardless of which
                    // template it happens to use (template_key is a free
                    // string, not constrained to a known set — inferring
                    // from it here would misroute a campaign that reuses a
                    // transactional-looking template name).
                    $this->communicationService->queueMessage($message, true, CommunicationMessage::QUEUE_TIER_BULK);
                    $queued++;
                }
            });

        $campaign->forceFill([
            'status' => $campaign->status === 'draft' ? 'running' : $campaign->status,
            'total_recipients' => $audienceCount,
            'total_queued' => (int) $campaign->total_queued + $queued,
        ])->save();

        $this->logCampaign($campaign, 'queued', 'Campaign messages queued.', [
            'count' => $queued,
            'total_recipients' => $audienceCount,
        ]);
        $this->logAudit('marketing.campaign.queue', [
            'staff_id' => auth()->guard('staff')->id(),
            'campaign_id' => $campaign->id,
            'count' => $queued,
        ]);

        return $queued;
    }

    public function startCampaign(MarketingCampaign $campaign): MarketingCampaign
    {
        if ($campaign->status !== 'scheduled') {
            return $campaign;
        }

        $campaign->update(['status' => 'running']);
        $this->logCampaign($campaign, 'started', 'Scheduled campaign started.');
        $this->queueCampaign($campaign->refresh());

        return $campaign->refresh();
    }

    public function completeCampaign(MarketingCampaign $campaign): MarketingCampaign
    {
        if ($campaign->status !== 'running') {
            return $campaign;
        }

        $this->refreshStats($campaign);
        $campaign->update(['status' => 'completed']);
        $this->logCampaign($campaign, 'completed', 'Campaign completed.');
        $this->logAudit('marketing.campaign.complete', [
            'campaign_id' => $campaign->id,
        ]);

        return $campaign->refresh();
    }

    public function campaignStats(MarketingCampaign $campaign): array
    {
        $campaign = $this->refreshStats($campaign);
        $queued = max(0, (int) $campaign->total_queued);
        $converted = (int) $campaign->total_converted;

        return [
            'total_recipients' => (int) $campaign->total_recipients,
            'total_queued' => $queued,
            'total_sent' => (int) $campaign->total_sent,
            'total_opened' => (int) $campaign->total_opened,
            'total_clicked' => (int) $campaign->total_clicked,
            'total_converted' => $converted,
            'total_revenue' => (float) $campaign->total_revenue,
            'conversion_rate' => $queued > 0 ? round(($converted / $queued) * 100, 2) : 0.0,
            'roi' => null,
        ];
    }

    public function dashboardStats(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::MARKETING_CAMPAIGN_DASHBOARD,
            function (): array {
                $topCampaign = MarketingCampaign::query()
                    ->orderByDesc('total_revenue')
                    ->orderByDesc('total_converted')
                    ->first();

                return [
                    'total_campaigns' => MarketingCampaign::query()->count(),
                    'running_campaigns' => MarketingCampaign::query()->where('status', 'running')->count(),
                    'campaign_revenue' => (float) MarketingCampaign::query()->sum('total_revenue'),
                    'campaign_conversions' => (int) MarketingCampaign::query()->sum('total_converted'),
                    'campaign_roi' => null,
                    'top_campaign' => $topCampaign ? [
                        'id' => $topCampaign->id,
                        'name' => $topCampaign->name,
                        'revenue' => (float) $topCampaign->total_revenue,
                        'conversions' => (int) $topCampaign->total_converted,
                    ] : null,
                    'recent_campaigns' => MarketingCampaign::query()
                        ->latest()
                        ->limit(5)
                        ->get(['id', 'name', 'status', 'campaign_type', 'total_queued', 'total_converted', 'total_revenue'])
                        ->map(fn (MarketingCampaign $campaign): array => [
                            'id' => $campaign->id,
                            'name' => $campaign->name,
                            'status' => $campaign->status,
                            'campaign_type' => $campaign->campaign_type,
                            'queued' => (int) $campaign->total_queued,
                            'conversions' => (int) $campaign->total_converted,
                            'revenue' => (float) $campaign->total_revenue,
                        ])
                        ->all(),
                ];
            },
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::MARKETING_CAMPAIGNS_TAG]
        );
    }

    public function paginatedCampaigns(int $perPage = 20): LengthAwarePaginator
    {
        return MarketingCampaign::query()
            ->with('segment')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginatedLogs(MarketingCampaign $campaign, int $perPage = 20): LengthAwarePaginator
    {
        return $campaign->logs()
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'logs_page')
            ->withQueryString();
    }

    public function runScheduledStarts(): int
    {
        $count = 0;

        MarketingCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($campaigns) use (&$count): void {
                foreach ($campaigns as $campaign) {
                    try {
                        $this->startCampaign($campaign);
                        $count++;
                    } catch (Throwable $exception) {
                        $this->logCampaign($campaign, 'start_failed', 'Scheduled campaign start failed.', [
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    public function runScheduledCompletions(): int
    {
        $count = 0;

        MarketingCampaign::query()
            ->where('status', 'running')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->orderBy('id')
            ->chunkById(50, function ($campaigns) use (&$count): void {
                foreach ($campaigns as $campaign) {
                    try {
                        $this->completeCampaign($campaign);
                        $count++;
                    } catch (Throwable $exception) {
                        $this->logCampaign($campaign, 'complete_failed', 'Scheduled campaign completion failed.', [
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    public function recordOrderAttribution(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        $message = CommunicationMessage::query()
            ->where('customer_id', $order->customer_id)
            ->where('created_at', '<=', $order->created_at)
            ->latest()
            ->limit(20)
            ->get()
            ->first(fn (CommunicationMessage $message): bool => isset(($message->variables ?? [])['campaign_id']));

        if (! $message) {
            return;
        }

        $campaignId = (int) (($message->variables ?? [])['campaign_id'] ?? 0);

        if ($campaignId <= 0) {
            return;
        }

        $campaign = MarketingCampaign::query()->find($campaignId);

        if (! $campaign) {
            return;
        }

        $alreadyAttributed = $campaign->logs()
            ->where('log_type', 'conversion')
            ->get()
            ->contains(fn (MarketingCampaignLog $log): bool => (int) (($log->metadata ?? [])['order_id'] ?? 0) === (int) $order->id);

        if ($alreadyAttributed) {
            return;
        }

        // Was a PHP read-modify-write (forceFill total_revenue to (float)
        // $campaign->total_revenue + (float) $order->total, then save()):
        // every call round-tripped the decimal(12,2) column through a PHP
        // float, so rounding error compounded with each conversion instead
        // of resetting each time, and the read-then-write had no lock, so
        // two concurrent conversions could silently drop one attribution.
        // increment() issues a single atomic `total_revenue = total_revenue
        // + ?` UPDATE — the addition happens in the database's own decimal
        // arithmetic, never touching a PHP float, and is race-safe because
        // there's no read step to race against.
        $campaign->increment('total_converted');
        $campaign->increment('total_revenue', (float) $order->total);

        $this->logCampaign($campaign, 'conversion', 'Order attributed to campaign by last-touch communication.', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'revenue' => (float) $order->total,
        ]);
    }

    protected function normalizePayload(array $data, ?MarketingCampaign $campaign = null): array
    {
        $name = (string) ($data['name'] ?? $campaign?->name ?? 'Campaign');
        $customerIds = $this->normalizeCustomerIds($data['customer_ids'] ?? $data['audience_json']['customer_ids'] ?? []);

        return [
            'name' => $name,
            'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $name), $campaign),
            'description' => $data['description'] ?? null,
            'campaign_type' => $data['campaign_type'],
            'status' => $data['status'] ?? 'draft',
            'audience_type' => $data['audience_type'] ?? 'all_customers',
            'marketing_segment_id' => ($data['audience_type'] ?? null) === 'segment' ? ($data['marketing_segment_id'] ?? null) : null,
            'audience_json' => $customerIds !== [] ? ['customer_ids' => $customerIds] : null,
            'template_key' => $data['template_key'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    protected function audienceQuery(MarketingCampaign|array $campaign): Builder
    {
        $audienceType = $campaign instanceof MarketingCampaign ? $campaign->audience_type : ($campaign['audience_type'] ?? 'all_customers');
        $segmentId = $campaign instanceof MarketingCampaign ? $campaign->marketing_segment_id : ($campaign['marketing_segment_id'] ?? null);
        $audienceJson = $campaign instanceof MarketingCampaign ? ($campaign->audience_json ?? []) : ($campaign['audience_json'] ?? []);

        return Customer::query()
            ->when($audienceType === 'segment', function (Builder $query) use ($segmentId): void {
                $query->whereHas('marketingSegmentMemberships', fn (Builder $membershipQuery) => $membershipQuery->where('marketing_segment_id', $segmentId));
            })
            ->when($audienceType === 'customer_list', function (Builder $query) use ($audienceJson): void {
                $ids = $this->normalizeCustomerIds($audienceJson['customer_ids'] ?? []);
                $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('id', $ids);
            });
    }

    protected function campaignMessageExists(MarketingCampaign $campaign, Customer $customer): bool
    {
        return CommunicationMessage::query()
            ->where('customer_id', $customer->id)
            ->where('template', $campaign->template_key ?: 'coupon_campaign')
            ->latest()
            ->limit(20)
            ->get()
            ->contains(fn (CommunicationMessage $message): bool => (int) (($message->variables ?? [])['campaign_id'] ?? 0) === (int) $campaign->id);
    }

    protected function refreshStats(MarketingCampaign $campaign): MarketingCampaign
    {
        $messages = CommunicationMessage::query()
            ->latest()
            ->limit(max(100, (int) $campaign->total_queued + 100))
            ->get()
            ->filter(fn (CommunicationMessage $message): bool => (int) (($message->variables ?? [])['campaign_id'] ?? 0) === (int) $campaign->id);

        if ($messages->isNotEmpty()) {
            $campaign->forceFill([
                'total_queued' => $messages->where('status', 'queued')->count() + $messages->whereIn('status', ['sent', 'delivered', 'failed'])->count(),
                'total_sent' => $messages->whereIn('status', ['sent', 'delivered'])->count(),
            ])->save();
        }

        return $campaign->refresh();
    }

    protected function normalizeCustomerIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function uniqueSlug(string $value, ?MarketingCampaign $campaign = null): string
    {
        $base = Str::slug($value) ?: 'campaign';
        $slug = $base;
        $counter = 2;

        while (
            MarketingCampaign::query()
                ->where('slug', $slug)
                ->when($campaign, fn (Builder $query) => $query->whereKeyNot($campaign->id))
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function logCampaign(MarketingCampaign $campaign, string $type, string $message, array $metadata = []): MarketingCampaignLog
    {
        return $campaign->logs()->create([
            'log_type' => $type,
            'message' => $message,
            'metadata' => $this->safeMetadata($metadata),
            'created_at' => now(),
        ]);
    }

    protected function logAudit(string $action, array $metadata = []): void
    {
        try {
            $this->auditService->log(
                'marketing.campaign',
                $action,
                'marketing',
                'Marketing campaign event recorded.',
                $this->safeMetadata($metadata)
            );
        } catch (Throwable $exception) {
            logger()->warning('Marketing campaign audit logging failed', [
                'action' => $action,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->except(['phone', 'customer_phone', 'email', 'customer_email', 'address', 'customer_address'])
            ->all();
    }
}
