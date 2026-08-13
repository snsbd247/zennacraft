<?php

namespace App\Modules\Automation\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Automation\Models\AutomationAction;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationWorkflow;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerPersonalizationService;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Models\MarketingSegmentMembership;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Services\MarketingCampaignService;
use App\Modules\Order\Models\Order;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Review\Models\ProductReview;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class AutomationEngineService
{
    public function __construct(
        private AuditService $auditService,
        private CommunicationService $communicationService,
        private CacheService $cacheService,
        private PhoneService $phoneService,
        private MarketingCampaignService $marketingCampaignService,
        private CustomerPersonalizationService $personalizationService,
    ) {}

    public function handleTrigger(string $triggerKey, mixed $subject, array $context = []): void
    {
        try {
            $workflows = AutomationWorkflow::query()
                ->where('status', 'active')
                ->where('trigger_key', $triggerKey)
                ->orderBy('id')
                ->get();

            foreach ($workflows as $workflow) {
                try {
                    $this->logAudit('automation.triggered', [
                        'workflow_id' => $workflow->id,
                        'trigger_key' => $triggerKey,
                        'subject_type' => $this->subjectType($subject),
                        'subject_id' => $this->subjectId($subject),
                    ]);

                    $this->evaluateWorkflow($workflow, $subject, $context);
                } catch (Throwable $exception) {
                    logger()->warning('Automation workflow trigger failed', [
                        'workflow_id' => $workflow->id,
                        'trigger_key' => $triggerKey,
                        'subject_type' => $this->subjectType($subject),
                        'subject_id' => $this->subjectId($subject),
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            logger()->warning('Automation trigger lookup failed', [
                'trigger_key' => $triggerKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function evaluateWorkflow(AutomationWorkflow $workflow, mixed $subject, array $context = []): AutomationRun
    {
        $run = AutomationRun::create([
            'automation_workflow_id' => $workflow->id,
            'subject_type' => $this->subjectType($subject),
            'subject_id' => $this->subjectId($subject),
            'status' => 'running',
            'trigger_key' => $workflow->trigger_key,
            'metadata' => $this->safeMetadata($context),
            'started_at' => now(),
        ]);

        try {
            if (! $this->conditionsPass($workflow, $subject, $context)) {
                $run->update([
                    'status' => 'skipped',
                    'result_summary' => 'Workflow conditions did not pass.',
                    'finished_at' => now(),
                ]);

                return $run->refresh();
            }

            $this->executeActions($run, $workflow, $subject, $context);

            $run->update([
                'status' => 'completed',
                'result_summary' => 'Workflow completed.',
                'finished_at' => now(),
            ]);

            $workflow->forceFill(['last_run_at' => now()])->save();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'result_summary' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            logger()->warning('Automation workflow evaluation failed', [
                'workflow_id' => $workflow->id,
                'run_id' => $run->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $run->refresh();
    }

    public function conditionsPass(AutomationWorkflow $workflow, mixed $subject, array $context = []): bool
    {
        $conditions = $workflow->conditions_json ?? [];

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            if (! $this->conditionPasses($condition, $subject, $context)) {
                return false;
            }
        }

        return true;
    }

    public function executeActions(AutomationRun $run, AutomationWorkflow $workflow, mixed $subject, array $context = []): void
    {
        $actions = $workflow->actions_json ?? [];

        if ($actions === []) {
            AutomationAction::create([
                'automation_run_id' => $run->id,
                'action_key' => 'none',
                'status' => 'skipped',
                'result_summary' => 'No actions configured.',
                'executed_at' => now(),
            ]);

            return;
        }

        foreach ($actions as $actionData) {
            if (! is_array($actionData)) {
                continue;
            }

            $actionKey = $this->actionKey($actionData);
            $action = AutomationAction::create([
                'automation_run_id' => $run->id,
                'action_key' => $actionKey,
                'status' => 'pending',
                'metadata' => $this->safeMetadata($actionData),
            ]);

            try {
                $result = $this->executeAction($actionKey, $actionData, $subject, $context);

                $action->update([
                    'status' => $result['status'] ?? 'completed',
                    'result_summary' => $result['summary'] ?? 'Action completed.',
                    'metadata' => $this->safeMetadata($result['metadata'] ?? []),
                    'executed_at' => now(),
                ]);

                $this->logAudit('automation.action.executed', [
                    'workflow_id' => $workflow->id,
                    'run_id' => $run->id,
                    'action_key' => $actionKey,
                    'trigger_key' => $workflow->trigger_key,
                    'subject_type' => $this->subjectType($subject),
                    'subject_id' => $this->subjectId($subject),
                ]);
            } catch (Throwable $exception) {
                $action->update([
                    'status' => 'failed',
                    'result_summary' => $exception->getMessage(),
                    'metadata' => $this->safeMetadata([
                        'exception' => class_basename($exception),
                    ]),
                    'executed_at' => now(),
                ]);

                $this->logAudit('automation.action.failed', [
                    'workflow_id' => $workflow->id,
                    'run_id' => $run->id,
                    'action_key' => $actionKey,
                    'trigger_key' => $workflow->trigger_key,
                    'subject_type' => $this->subjectType($subject),
                    'subject_id' => $this->subjectId($subject),
                ]);

                logger()->warning('Automation action failed', [
                    'workflow_id' => $workflow->id,
                    'run_id' => $run->id,
                    'action_key' => $actionKey,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function runWorkflowManually(AutomationWorkflow $workflow): int
    {
        $count = 0;

        $this->subjectsForWorkflow($workflow, function (mixed $subject) use (&$count, $workflow): void {
            $this->evaluateWorkflow($workflow, $subject, [
                'trigger' => 'manual',
                'staff_id' => auth()->guard('staff')->id(),
            ]);
            $count++;
        });

        return $count;
    }

    public function workflowSummary(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::AUTOMATION_SUMMARY,
            fn (): array => [
                'total' => AutomationWorkflow::query()->count(),
                'active' => AutomationWorkflow::query()->where('status', 'active')->count(),
                'draft' => AutomationWorkflow::query()->where('status', 'draft')->count(),
                'paused' => AutomationWorkflow::query()->where('status', 'paused')->count(),
                'runs' => AutomationRun::query()->count(),
                'failed_runs' => AutomationRun::query()->where('status', 'failed')->count(),
            ],
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::AUTOMATION_TAG]
        );
    }

    public function commandCenter(): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::AUTOMATION_COMMAND_CENTER,
            fn (): array => $this->buildCommandCenter(),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::AUTOMATION_TAG]
        );
    }

    public function recentRuns(int $limit = 12): \Illuminate\Support\Collection
    {
        return AutomationRun::query()
            ->with(['workflow', 'actions'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function buildCommandCenter(): array
    {
        $today = now()->startOfDay();
        $orderSubjectTypes = array_values(array_unique([Order::class, (new Order())->getMorphClass(), 'order']));
        $recoverySubjectTypes = array_values(array_unique([CheckoutRecovery::class, (new CheckoutRecovery())->getMorphClass(), 'checkout_recovery']));
        $completedOrderIds = AutomationRun::query()
            ->where('status', 'completed')
            ->whereIn('subject_type', $orderSubjectTypes)
            ->whereNotNull('subject_id')
            ->distinct()
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        $revenueAssisted = $completedOrderIds === []
            ? 0.0
            : (float) Order::query()
                ->whereIn('id', $completedOrderIds)
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->sum('total');

        $messagesQueued = (int) AutomationAction::query()
            ->where('action_key', 'queue_communication')
            ->where('status', 'completed')
            ->count();

        $topTriggers = AutomationRun::query()
            ->select('trigger_key')
            ->selectRaw('COUNT(*) as run_count')
            ->groupBy('trigger_key')
            ->orderByDesc('run_count')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'trigger_key' => (string) $row->trigger_key,
                'runs' => (int) $row->run_count,
            ])
            ->all();

        return [
            'kpis' => [
                'active_automations' => (int) AutomationWorkflow::query()->where('status', 'active')->count(),
                'runs_today' => (int) AutomationRun::query()->where('created_at', '>=', $today)->count(),
                'messages_queued' => $messagesQueued,
                'failed_runs' => (int) AutomationRun::query()->where('status', 'failed')->count(),
                'recovery_automations' => (int) AutomationWorkflow::query()
                    ->where('status', 'active')
                    ->where('trigger_key', 'checkout.abandoned')
                    ->count(),
                'order_automations' => (int) AutomationWorkflow::query()
                    ->where('status', 'active')
                    ->where('trigger_key', 'like', 'order.%')
                    ->count(),
                'revenue_assisted' => $revenueAssisted,
                'is_estimated' => true,
            ],
            'workflow_mix' => [
                'order' => (int) AutomationWorkflow::query()->where('trigger_key', 'like', 'order.%')->count(),
                'recovery' => (int) AutomationWorkflow::query()->where('trigger_key', 'checkout.abandoned')->count(),
                'customer_lifecycle' => (int) AutomationWorkflow::query()->where('trigger_key', 'like', 'customer.%')->count(),
                'marketing' => (int) AutomationWorkflow::query()->whereIn('trigger_key', ['customer.segment_entered', 'coupon.used'])->count(),
                'risk' => (int) AutomationWorkflow::query()->whereIn('trigger_key', ['fraud.event.created', 'blacklist.added'])->count(),
            ],
            'run_statuses' => [
                'completed' => (int) AutomationRun::query()->where('status', 'completed')->count(),
                'skipped' => (int) AutomationRun::query()->where('status', 'skipped')->count(),
                'failed' => (int) AutomationRun::query()->where('status', 'failed')->count(),
                'running' => (int) AutomationRun::query()->whereIn('status', ['pending', 'running'])->count(),
            ],
            'top_triggers' => $topTriggers,
            'messages' => [
                'queued_by_automation' => $messagesQueued,
                'pending_communications' => (int) CommunicationMessage::query()->where('status', 'pending')->count(),
                'queued_communications' => (int) CommunicationMessage::query()->where('status', 'queued')->count(),
                'failed_communications' => (int) CommunicationMessage::query()->where('status', 'failed')->count(),
            ],
            'integrations' => [
                'marketing_campaign_actions' => (int) AutomationAction::query()
                    ->whereIn('action_key', ['launch_campaign', 'queue_campaign', 'pause_campaign'])
                    ->count(),
                'recovery_runs' => (int) AutomationRun::query()->whereIn('subject_type', $recoverySubjectTypes)->count(),
                'segment_actions' => (int) AutomationAction::query()
                    ->whereIn('action_key', ['add_to_segment', 'remove_from_segment'])
                    ->count(),
            ],
        ];
    }

    protected function conditionPasses(array $condition, mixed $subject, array $context): bool
    {
        $key = $this->conditionKey($condition);

        return match ($key) {
            'always' => true,
            'customer_in_segment' => $this->customerHasSegment($subject, $context, $condition),
            'customer_not_in_segment' => ! $this->customerHasSegment($subject, $context, $condition),
            'customer_not_blacklisted' => ! $this->customerIsBlacklisted($subject, $context),
            'order_status_is' => $this->orderStatusMatches($subject, $context, $condition),
            'order_total_greater_than' => $this->orderTotalGreaterThan($subject, $context, $condition),
            'fraud_level_is' => $this->fraudLevelMatches($subject, $context, $condition),
            'coupon_code_is' => $this->couponCodeMatches($subject, $context, $condition),
            'customer_intent_is' => $this->customerIntentMatches($subject, $context, $condition),
            default => false,
        };
    }

    protected function executeAction(string $actionKey, array $actionData, mixed $subject, array $context): array
    {
        return match ($actionKey) {
            'queue_communication' => $this->queueCommunication($actionData, $subject, $context),
            'add_to_segment' => $this->addToSegment($actionData, $subject, $context),
            'remove_from_segment' => $this->removeFromSegment($actionData, $subject, $context),
            'launch_campaign' => $this->launchCampaign($actionData),
            'queue_campaign' => $this->queueCampaignAction($actionData),
            'pause_campaign' => $this->pauseCampaign($actionData),
            'create_internal_note', 'create_followup_task', 'mark_for_review' => [
                'status' => 'completed',
                'summary' => 'Automation action recorded for staff follow-up.',
                'metadata' => ['action_key' => $actionKey],
            ],
            default => [
                'status' => 'skipped',
                'summary' => 'Unknown automation action skipped.',
                'metadata' => ['action_key' => $actionKey],
            ],
        };
    }

    protected function queueCommunication(array $actionData, mixed $subject, array $context): array
    {
        $template = (string) ($actionData['template'] ?? $actionData['template_key'] ?? 'abandoned_checkout');
        $channel = (string) ($actionData['channel'] ?? 'sms');
        $customer = $this->resolveCustomer($subject, $context);
        $recipient = $this->communicationRecipient($subject, $customer, $channel);

        if (! filled($recipient)) {
            return [
                'status' => 'skipped',
                'summary' => 'No communication recipient was available.',
            ];
        }

        $message = $this->communicationService->createFromTemplate(
            $channel,
            (string) $recipient,
            $template,
            array_merge(
                $this->defaultVariables($subject, $customer),
                is_array($actionData['variables'] ?? null) ? $actionData['variables'] : []
            ),
            $this->communicationContext($subject, $customer, $context)
        );

        $this->communicationService->queueMessage($message, true);

        return [
            'status' => 'completed',
            'summary' => 'Communication message queued and dispatched.',
            'metadata' => ['message_id' => $message->id],
        ];
    }

    protected function addToSegment(array $actionData, mixed $subject, array $context): array
    {
        $customer = $this->resolveCustomer($subject, $context);
        $segment = $this->resolveSegment($actionData);

        if (! $customer || ! $segment) {
            return [
                'status' => 'skipped',
                'summary' => 'Customer or segment was not available.',
            ];
        }

        MarketingSegmentMembership::withoutAutomation(function () use ($customer, $segment): void {
            MarketingSegmentMembership::query()->updateOrCreate(
                [
                    'marketing_segment_id' => $segment->id,
                    'customer_id' => $customer->id,
                ],
                [
                    'joined_at' => now(),
                    'last_evaluated_at' => now(),
                ]
            );
        });

        return [
            'status' => 'completed',
            'summary' => 'Customer added to marketing segment.',
            'metadata' => [
                'customer_id' => $customer->id,
                'segment_id' => $segment->id,
            ],
        ];
    }

    protected function removeFromSegment(array $actionData, mixed $subject, array $context): array
    {
        $customer = $this->resolveCustomer($subject, $context);
        $segment = $this->resolveSegment($actionData);

        if (! $customer || ! $segment) {
            return [
                'status' => 'skipped',
                'summary' => 'Customer or segment was not available.',
            ];
        }

        MarketingSegmentMembership::withoutAutomation(function () use ($customer, $segment): void {
            MarketingSegmentMembership::query()
                ->where('marketing_segment_id', $segment->id)
                ->where('customer_id', $customer->id)
                ->delete();
        });

        return [
            'status' => 'completed',
            'summary' => 'Customer removed from marketing segment.',
            'metadata' => [
                'customer_id' => $customer->id,
                'segment_id' => $segment->id,
            ],
        ];
    }

    protected function launchCampaign(array $actionData): array
    {
        $campaign = $this->resolveCampaign($actionData);

        if (! $campaign) {
            return [
                'status' => 'skipped',
                'summary' => 'Campaign was not found.',
            ];
        }

        $this->marketingCampaignService->activateCampaign($campaign);

        return [
            'status' => 'completed',
            'summary' => 'Campaign activated.',
            'metadata' => ['campaign_id' => $campaign->id],
        ];
    }

    protected function queueCampaignAction(array $actionData): array
    {
        $campaign = $this->resolveCampaign($actionData);

        if (! $campaign) {
            return [
                'status' => 'skipped',
                'summary' => 'Campaign was not found.',
            ];
        }

        $count = $this->marketingCampaignService->queueCampaign($campaign);

        return [
            'status' => 'completed',
            'summary' => 'Campaign queued '.$count.' message(s).',
            'metadata' => ['campaign_id' => $campaign->id, 'count' => $count],
        ];
    }

    protected function pauseCampaign(array $actionData): array
    {
        $campaign = $this->resolveCampaign($actionData);

        if (! $campaign) {
            return [
                'status' => 'skipped',
                'summary' => 'Campaign was not found.',
            ];
        }

        $this->marketingCampaignService->pauseCampaign($campaign);

        return [
            'status' => 'completed',
            'summary' => 'Campaign paused.',
            'metadata' => ['campaign_id' => $campaign->id],
        ];
    }

    protected function customerHasSegment(mixed $subject, array $context, array $condition): bool
    {
        $customer = $this->resolveCustomer($subject, $context);
        $segment = $this->resolveSegment($condition);

        if (! $customer || ! $segment) {
            return false;
        }

        return $customer->marketingSegments()
            ->where('marketing_segments.id', $segment->id)
            ->exists();
    }

    protected function customerIsBlacklisted(mixed $subject, array $context): bool
    {
        $customer = $this->resolveCustomer($subject, $context);
        $phone = $this->phoneForSubject($subject, $customer, $context);
        $values = $this->phoneService->lookupValues($phone);

        if (! $customer && $values === []) {
            return false;
        }

        return CustomerBlacklist::query()
            ->where('active', true)
            ->where(function ($query) use ($customer, $values): void {
                if ($customer) {
                    $query->where('customer_id', $customer->id);
                }

                if ($values !== []) {
                    $placeholders = implode(', ', array_fill(0, count($values), '?'));
                    $expression = "REPLACE(REPLACE(REPLACE(TRIM(phone), ' ', ''), '-', ''), '+', '')";
                    $query->orWhereRaw($expression.' in ('.$placeholders.')', $values);
                }
            })
            ->exists();
    }

    protected function orderStatusMatches(mixed $subject, array $context, array $condition): bool
    {
        $order = $this->resolveOrder($subject, $context);
        $expected = (string) ($condition['status'] ?? $condition['value'] ?? '');

        return $order instanceof Order && $expected !== '' && $order->status === $expected;
    }

    protected function orderTotalGreaterThan(mixed $subject, array $context, array $condition): bool
    {
        $order = $this->resolveOrder($subject, $context);
        $amount = (float) ($condition['amount'] ?? $condition['value'] ?? 0);

        return $order instanceof Order && (float) $order->total > $amount;
    }

    protected function fraudLevelMatches(mixed $subject, array $context, array $condition): bool
    {
        $expected = strtolower((string) ($condition['severity'] ?? $condition['fraud_level'] ?? $condition['value'] ?? ''));
        $actual = strtolower((string) ($context['severity'] ?? $context['fraud_level'] ?? ''));

        if ($subject instanceof FraudEvent) {
            $actual = strtolower((string) $subject->severity);
        }

        return $expected !== '' && $actual === $expected;
    }

    protected function couponCodeMatches(mixed $subject, array $context, array $condition): bool
    {
        $expected = strtoupper(trim((string) ($condition['code'] ?? $condition['coupon_code'] ?? $condition['value'] ?? '')));
        $actual = strtoupper(trim((string) ($this->couponCodeForSubject($subject, $context) ?? '')));

        return $expected !== '' && $actual === $expected;
    }

    protected function customerIntentMatches(mixed $subject, array $context, array $condition): bool
    {
        $customer = $this->resolveCustomer($subject, $context);
        $expected = strtolower(trim((string) ($condition['intent'] ?? $condition['label'] ?? $condition['value'] ?? '')));

        return $customer instanceof Customer
            && in_array($expected, ['hot', 'warm', 'cold'], true)
            && $this->personalizationService->customerIntentMatches($customer, $expected);
    }

    protected function subjectsForWorkflow(AutomationWorkflow $workflow, callable $callback): void
    {
        match ($workflow->trigger_key) {
            'order.created', 'order.status_changed' => Order::query()->orderBy('id')->chunkById(100, fn ($orders) => $orders->each($callback)),
            'customer.created', 'customer.segment_entered', 'customer.segment_left' => Customer::query()->orderBy('id')->chunkById(100, fn ($customers) => $customers->each($callback)),
            'coupon.used' => CouponUsage::query()->with(['customer', 'order'])->orderBy('id')->chunkById(100, fn ($usages) => $usages->each($callback)),
            'checkout.abandoned' => CheckoutRecovery::query()->orderBy('id')->chunkById(100, fn ($recoveries) => $recoveries->each($callback)),
            'fraud.event.created' => FraudEvent::query()->with(['customer', 'order'])->orderBy('id')->chunkById(100, fn ($events) => $events->each($callback)),
            'blacklist.added' => CustomerBlacklist::query()->orderBy('id')->chunkById(100, fn ($blacklists) => $blacklists->each($callback)),
            'review.approved' => ProductReview::query()->approved()->with(['customer', 'order'])->orderBy('id')->chunkById(100, fn ($reviews) => $reviews->each($callback)),
            default => null,
        };
    }

    protected function resolveCustomer(mixed $subject, array $context = []): ?Customer
    {
        if ($subject instanceof Customer) {
            return $subject;
        }

        if ($subject instanceof Order) {
            return $subject->customer ?: $this->customerByPhone((string) $subject->customer_phone);
        }

        if ($subject instanceof CouponUsage) {
            $subject->loadMissing(['customer', 'order.customer']);

            return $subject->customer ?: $subject->order?->customer;
        }

        if ($subject instanceof CheckoutRecovery) {
            return $this->customerByPhone((string) $subject->customer_phone);
        }

        if ($subject instanceof FraudEvent) {
            $subject->loadMissing(['customer', 'order.customer']);

            return $subject->customer ?: $subject->order?->customer;
        }

        if ($subject instanceof CustomerBlacklist) {
            $subject->loadMissing('customer');

            return $subject->customer ?: $this->customerByPhone((string) $subject->phone);
        }

        if ($subject instanceof ProductReview) {
            $subject->loadMissing(['customer', 'order.customer']);

            return $subject->customer ?: $subject->order?->customer;
        }

        if (isset($context['customer_id'])) {
            return Customer::query()->find($context['customer_id']);
        }

        if (isset($context['phone'])) {
            return $this->customerByPhone((string) $context['phone']);
        }

        return null;
    }

    protected function resolveOrder(mixed $subject, array $context = []): ?Order
    {
        if ($subject instanceof Order) {
            return $subject;
        }

        if ($subject instanceof CouponUsage) {
            $subject->loadMissing('order');

            return $subject->order;
        }

        if ($subject instanceof FraudEvent) {
            $subject->loadMissing('order');

            return $subject->order;
        }

        if ($subject instanceof ProductReview) {
            $subject->loadMissing('order');

            return $subject->order;
        }

        if (isset($context['order_id'])) {
            return Order::query()->find($context['order_id']);
        }

        return null;
    }

    protected function resolveSegment(array $data): ?MarketingSegment
    {
        $id = $data['segment_id'] ?? $data['marketing_segment_id'] ?? null;

        if ($id) {
            return MarketingSegment::query()->find($id);
        }

        $slug = trim((string) ($data['segment_slug'] ?? $data['segment'] ?? $data['slug'] ?? ''));

        if ($slug === '') {
            return null;
        }

        return MarketingSegment::query()
            ->whereIn('slug', array_values(array_unique([$slug, strtoupper($slug)])))
            ->first();
    }

    protected function resolveCampaign(array $data): ?MarketingCampaign
    {
        $id = $data['campaign_id'] ?? $data['marketing_campaign_id'] ?? null;

        if ($id) {
            return MarketingCampaign::query()->find($id);
        }

        $slug = trim((string) ($data['campaign_slug'] ?? $data['slug'] ?? ''));

        if ($slug === '') {
            return null;
        }

        return MarketingCampaign::query()->where('slug', $slug)->first();
    }

    protected function customerByPhone(string $phone): ?Customer
    {
        $values = $this->phoneService->lookupValues($phone);

        if ($values === []) {
            return null;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $expression = "REPLACE(REPLACE(REPLACE(TRIM(phone), ' ', ''), '-', ''), '+', '')";

        return Customer::query()
            ->whereRaw($expression.' in ('.$placeholders.')', $values)
            ->first();
    }

    protected function communicationRecipient(mixed $subject, ?Customer $customer, string $channel): ?string
    {
        if ($channel === 'email') {
            return $customer?->email
                ?: ($subject instanceof Order ? $subject->customer_email : null)
                ?: ($subject instanceof CheckoutRecovery ? $subject->customer_email : null);
        }

        return $customer?->phone
            ?: ($subject instanceof Order ? $subject->customer_phone : null)
            ?: ($subject instanceof CheckoutRecovery ? $subject->customer_phone : null);
    }

    protected function communicationContext(mixed $subject, ?Customer $customer, array $context): array
    {
        return array_merge($this->safeMetadata($context), [
            'customer_id' => $customer?->id,
            'order_id' => $this->resolveOrder($subject, $context)?->id,
            'recovery_id' => $subject instanceof CheckoutRecovery ? $subject->id : null,
            'coupon_id' => $subject instanceof CouponUsage ? $subject->coupon_id : null,
            'review_id' => $subject instanceof ProductReview ? $subject->id : null,
            'trigger' => 'automation:'.$this->subjectType($subject),
        ]);
    }

    protected function defaultVariables(mixed $subject, ?Customer $customer): array
    {
        return [
            'customer_name' => $customer?->name
                ?: ($subject instanceof Order ? $subject->customer_name : null)
                ?: ($subject instanceof CheckoutRecovery ? $subject->customer_name : null)
                ?: 'Customer',
            'order_number' => $subject instanceof Order ? $subject->order_number : '',
            'tracking_number' => $subject instanceof Order ? ($subject->shipment?->tracking_number ?? '') : '',
            'coupon_code' => $subject instanceof CouponUsage ? $subject->code : '',
            'product_name' => $subject instanceof ProductReview ? ($subject->product?->name ?? '') : '',
        ];
    }

    protected function phoneForSubject(mixed $subject, ?Customer $customer, array $context): string
    {
        return (string) (
            $customer?->phone
            ?: ($subject instanceof Order ? $subject->customer_phone : null)
            ?: ($subject instanceof CheckoutRecovery ? $subject->customer_phone : null)
            ?: ($subject instanceof CustomerBlacklist ? $subject->phone : null)
            ?: ($context['phone'] ?? '')
        );
    }

    protected function couponCodeForSubject(mixed $subject, array $context): ?string
    {
        if ($subject instanceof CouponUsage) {
            return $subject->code;
        }

        if ($subject instanceof Order) {
            return $subject->coupon_code;
        }

        return $context['coupon_code'] ?? null;
    }

    protected function conditionKey(array $condition): string
    {
        return (string) ($condition['key'] ?? $condition['condition_key'] ?? $condition['type'] ?? '');
    }

    protected function actionKey(array $action): string
    {
        return (string) ($action['key'] ?? $action['action_key'] ?? $action['type'] ?? '');
    }

    protected function subjectType(mixed $subject): ?string
    {
        return $subject instanceof Model ? $subject->getMorphClass() : (is_object($subject) ? class_basename($subject) : null);
    }

    protected function subjectId(mixed $subject): ?int
    {
        return $subject instanceof Model ? (int) $subject->getKey() : null;
    }

    protected function logAudit(string $action, array $metadata): void
    {
        try {
            $this->auditService->log(
                'automation',
                $action,
                'automation',
                'Automation workflow event recorded.',
                $this->safeMetadata($metadata)
            );
        } catch (Throwable $exception) {
            logger()->warning('Automation audit logging failed', [
                'action' => $action,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function safeMetadata(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (
                str_contains($lowerKey, 'phone')
                || str_contains($lowerKey, 'email')
                || str_contains($lowerKey, 'address')
                || str_contains($lowerKey, 'recipient')
                || str_contains($lowerKey, 'otp')
                || str_contains($lowerKey, 'token')
            ) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->safeMetadata($value);

                continue;
            }

            $safe[$key] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
        }

        return $safe;
    }
}
