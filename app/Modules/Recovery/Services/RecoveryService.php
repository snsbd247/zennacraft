<?php

namespace App\Modules\Recovery\Services;

use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Communication\Services\CommunicationService;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerPersonalizationService;
use App\Modules\Customer\Services\CustomerShadowAccountService;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\Order\Models\Order;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecoveryService
{
    public function __construct(
        private CustomerShadowAccountService $shadowAccountService,
        private PhoneService $phoneService,
        private CommunicationService $communicationService,
        private MarketingSegmentEngineService $marketingSegmentEngine,
        private BehaviorEventService $behaviorEventService,
        private CustomerPersonalizationService $personalizationService,
    ) {}

    protected const STATUSES = [
        'open',
        'contacted',
        'called',
        'no_answer',
        'interested',
        'not_interested',
        'recovered',
        'lost',
        'closed',
    ];

    protected const ACTIVE_STATUSES = [
        'open',
        'contacted',
        'called',
        'no_answer',
        'interested',
    ];

    /** @return array<int,string> every valid recovery status. */
    public static function statuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<int,string> statuses still worth following up on. */
    public static function activeStatuses(): array
    {
        return self::ACTIVE_STATUSES;
    }

    public function saveRecovery(array $data): CheckoutRecovery
    {
        $recovery = $this->findExistingRecovery($data);

        $payload = [
            'product_id' => $data['product_id'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? $data['name'] ?? null,
            'customer_phone' => $this->normalizedPhone($data['customer_phone'] ?? $data['phone'] ?? null),
            'customer_email' => $data['customer_email'] ?? $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'status' => $data['status'] ?? $recovery?->status ?? 'open',
        ];

        if ($recovery) {
            $recovery->update($payload);

            $recovery = $recovery->refresh();
            $this->touchShadowActivity($recovery);
            $this->evaluateMarketingSegments($recovery, 'checkout_recovery:update');
            $this->createRecoveryCommunication($recovery, 'recovery_reminder');
            $this->triggerAutomation($recovery, 'checkout_recovery:update');

            return $recovery;
        }

        $recovery = CheckoutRecovery::create($payload);
        $this->touchShadowActivity($recovery);
        $this->evaluateMarketingSegments($recovery, 'checkout_recovery:create');
        $this->createRecoveryCommunication($recovery, 'abandoned_checkout');
        $this->triggerAutomation($recovery, 'checkout_recovery:create');

        return $recovery;
    }

    public function markStatus(CheckoutRecovery $recovery, string $status): CheckoutRecovery
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected recovery status is invalid.',
            ]);
        }

        $recovery->update(['status' => $status]);

        $recovery = $recovery->refresh();
        $this->touchShadowActivity($recovery);
        $this->evaluateMarketingSegments($recovery, 'checkout_recovery:'.$status);
        $this->triggerAutomation($recovery, 'checkout_recovery:'.$status);

        return $recovery;
    }

    public function openRecoveries(int $perPage = 20): LengthAwarePaginator
    {
        return CheckoutRecovery::with(['product.thumbnail', 'variant.image', 'communicationMessages'])
            ->latest()
            ->paginate($perPage);
    }

    public function recoveryDashboard(): array
    {
        $today = now()->startOfDay();
        $activeQuery = CheckoutRecovery::query()->whereIn('status', self::ACTIVE_STATUSES);
        $totalRecoverable = (int) CheckoutRecovery::query()
            ->whereIn('status', array_merge(self::ACTIVE_STATUSES, ['recovered', 'lost', 'closed']))
            ->count();
        $recoveredCount = (int) CheckoutRecovery::query()->where('status', 'recovered')->count();
        $hotLeads = CheckoutRecovery::query()
            ->with(['product', 'variant'])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn (CheckoutRecovery $recovery): bool => $this->recoveryScore($recovery)['level'] === 'Hot')
            ->count();

        return [
            'abandoned_today' => (int) CheckoutRecovery::query()
                ->where('created_at', '>=', $today)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->count(),
            'incomplete_checkouts' => (int) (clone $activeQuery)
                ->where(fn ($query) => $query
                    ->whereNotNull('customer_phone')
                    ->orWhereNotNull('customer_email')
                    ->orWhereNotNull('customer_name')
                    ->orWhereNotNull('address'))
                ->count(),
            'callback_pending' => (int) CheckoutRecovery::query()
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNotNull('customer_phone')
                ->count(),
            'recovered_revenue' => $this->recoveredRevenue(),
            'recovery_rate' => $totalRecoverable > 0 ? round(($recoveredCount / $totalRecoverable) * 100, 2) : 0.0,
            'hot_leads' => (int) $hotLeads,
        ];
    }

    public function recoveriesForTab(string $tab, int $perPage = 20): LengthAwarePaginator
    {
        return CheckoutRecovery::query()
            ->with(['product.thumbnail', 'variant.image', 'communicationMessages'])
            ->when($tab === 'abandoned', fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(fn ($nested) => $nested
                    ->whereNull('customer_phone')
                    ->orWhere('customer_phone', '')))
            ->when($tab === 'incomplete', fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(fn ($nested) => $nested
                    ->whereNotNull('customer_phone')
                    ->orWhereNotNull('customer_email')
                    ->orWhereNotNull('customer_name')
                    ->orWhereNotNull('address')))
            ->when($tab === 'callback', fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereNotNull('customer_phone'))
            ->when(! in_array($tab, ['abandoned', 'incomplete', 'callback'], true), fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function queueRecoveryMessage(CheckoutRecovery $recovery, string $channel): ?CommunicationMessage
    {
        if (! in_array($channel, ['sms', 'whatsapp', 'internal'], true)) {
            throw ValidationException::withMessages([
                'channel' => 'The selected recovery channel is invalid.',
            ]);
        }

        if ($channel !== 'internal' && ! filled($recovery->customer_phone)) {
            throw ValidationException::withMessages([
                'channel' => 'A customer phone is required before SMS or WhatsApp recovery can be queued.',
            ]);
        }

        $customer = $this->customerForRecovery($recovery);
        $recipient = $channel === 'internal' ? 'studio' : (string) $recovery->customer_phone;

        $message = $this->communicationService->createFromTemplate(
            $channel,
            $recipient,
            'recovery_reminder',
            [
                'customer_name' => $recovery->customer_name ?: 'Customer',
            ],
            [
                'customer_id' => $customer?->id,
                'recovery_id' => $recovery->id,
                'trigger' => 'recovery_center:'.$channel,
            ]
        );

        $this->communicationService->queueMessage($message, true);

        if (in_array($recovery->status, ['open', 'no_answer'], true)) {
            $this->markStatus($recovery, 'contacted');
        }

        return $message->refresh();
    }

    public function recoveryScore(CheckoutRecovery $recovery): array
    {
        $score = 10;
        $factors = [];

        if (filled($recovery->customer_phone)) {
            $score += 25;
            $factors[] = 'Phone available';
        }

        $value = $this->recoveryValue($recovery);
        if ($value >= 5000) {
            $score += 20;
            $factors[] = 'High cart value';
        } elseif ($value >= 2000) {
            $score += 10;
            $factors[] = 'Meaningful cart value';
        }

        $stage = $this->completionStage($recovery);
        $stageScores = [
            'Cart' => 5,
            'Phone Entered' => 20,
            'Address Entered' => 30,
            'Checkout Started' => 35,
            'Left Before Submit' => 40,
        ];
        $score += $stageScores[$stage] ?? 5;
        $factors[] = $stage;

        $behaviorSignals = $this->behaviorEventService->recoverySignals($recovery);
        if ($behaviorSignals['fresh'] ?? false) {
            $score += 10;
            $factors[] = 'Fresh product behavior';
        }

        if ($behaviorSignals['package_selected'] ?? null) {
            $score += 5;
            $factors[] = 'Package selected';
        }

        $customer = $this->customerForRecovery($recovery);
        $personalization = null;

        try {
            $personalization = $this->personalizationService->recoveryProfile($recovery);
            $intentLabel = $personalization['intent']['label'] ?? null;

            if ($intentLabel === 'Hot') {
                $score += 10;
                $factors[] = 'Hot intent';
            } elseif ($intentLabel === 'Warm') {
                $score += 5;
                $factors[] = 'Warm intent';
            }
        } catch (Throwable $exception) {
            logger()->warning('Recovery personalization profile failed', [
                'recovery_id' => $recovery->id,
                'phone_hash' => $this->phoneService->hash($recovery->customer_phone),
                'error' => $exception->getMessage(),
            ]);
        }

        if ($customer) {
            $ordersCount = (int) $customer->orders()->count();
            if ($ordersCount > 1) {
                $score += 20;
                $factors[] = 'Repeat customer';
            } elseif ($ordersCount === 1) {
                $score += 10;
                $factors[] = 'Known customer';
            }

            if ($customer->couponUsages()->exists()) {
                $score += 5;
                $factors[] = 'Coupon history';
            }
        }

        $minutesSinceAbandonment = $recovery->updated_at
            ? max(0, $recovery->updated_at->diffInMinutes(now()))
            : 0;

        if ($minutesSinceAbandonment <= 60) {
            $score += 20;
            $factors[] = 'Fresh lead';
        } elseif ($minutesSinceAbandonment <= 1440) {
            $score += 10;
            $factors[] = 'Recent lead';
        } elseif ($minutesSinceAbandonment > 4320) {
            $score -= 10;
            $factors[] = 'Aging lead';
        }

        $score = max(0, min(100, $score));
        $level = $score >= 70 ? 'Hot' : ($score >= 40 ? 'Warm' : 'Cold');

        return [
            'score' => $score,
            'level' => $level,
            'factors' => $factors,
            'value' => $value,
            'stage' => $stage,
            'behavior' => [
                'last_viewed_product' => $behaviorSignals['last_viewed_product'] ?? null,
                'package_selected' => $behaviorSignals['package_selected'] ?? null,
                'checkout_started_at' => $behaviorSignals['checkout_started_at'] ?? null,
            ],
            'intent' => $personalization['intent'] ?? null,
            'personalization' => [
                'favorite_category' => $personalization['favorite_category']['name'] ?? null,
                'favorite_product' => $personalization['favorite_product']['name'] ?? null,
                'recommended_action' => $personalization['recommended_action'] ?? null,
                'suggested_coupon' => $personalization['suggested_coupon']?->code ?? null,
                'last_signal' => $personalization['last_signal']?->event_type ?? null,
            ],
        ];
    }

    public function recoveryTimeline(CheckoutRecovery $recovery): Collection
    {
        return collect([
            [
                'title' => 'Cart created',
                'body' => 'Recovery record opened for a storefront checkout journey.',
                'at' => $recovery->created_at,
                'tone' => 'info',
            ],
            [
                'title' => 'Product added',
                'body' => $recovery->product?->name ?: 'Product unavailable',
                'at' => $recovery->created_at,
                'tone' => 'info',
            ],
        ])
            ->when(filled($recovery->customer_phone), fn (Collection $events) => $events->push([
                'title' => 'Phone entered',
                'body' => 'Lead has a callable phone number.',
                'at' => $recovery->updated_at ?: $recovery->created_at,
                'tone' => 'success',
            ]))
            ->when(filled($recovery->address), fn (Collection $events) => $events->push([
                'title' => 'Address entered',
                'body' => 'Customer entered delivery details before leaving checkout.',
                'at' => $recovery->updated_at ?: $recovery->created_at,
                'tone' => 'success',
            ]))
            ->merge($this->behaviorEventService->recoverySignals($recovery)['events']->map(fn ($event): array => [
                'title' => str_replace('_', ' ', ucfirst($event->event_type)),
                'body' => trim(($event->product?->name ?: 'Storefront behavior').' '.($event->variant?->name ? '· '.$event->variant->name : '')),
                'at' => $event->occurred_at,
                'tone' => in_array($event->event_type, ['added_to_cart', 'checkout_started', 'order_submitted'], true) ? 'success' : 'info',
            ]))
            ->merge($recovery->communicationMessages->map(fn (CommunicationMessage $message): array => [
                'title' => ucfirst($message->channel).' recovery message',
                'body' => ucfirst($message->status).' - '.($message->subject ?: $message->template),
                'at' => $message->queued_at ?: $message->sent_at ?: $message->created_at,
                'tone' => $message->status === 'failed' ? 'warning' : 'info',
            ]))
            ->when(in_array($recovery->status, ['contacted', 'called', 'no_answer', 'interested', 'not_interested', 'recovered', 'lost', 'closed'], true), fn (Collection $events) => $events->push([
                'title' => 'Recovery '.str_replace('_', ' ', $recovery->status),
                'body' => 'Recovery outcome updated by Studio operations.',
                'at' => $recovery->updated_at,
                'tone' => $recovery->status === 'recovered' ? 'success' : ($recovery->status === 'lost' || $recovery->status === 'closed' ? 'warning' : 'info'),
            ]))
            ->filter(fn (array $event): bool => filled($event['at']))
            ->sortBy('at')
            ->values();
    }

    public function customerRecoveryIntelligence(Customer $customer): array
    {
        $recoveries = CheckoutRecovery::query()
            ->with(['product.thumbnail', 'variant.image', 'communicationMessages'])
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $customer->phone))
            ->latest()
            ->limit(20)
            ->get();

        $active = $recoveries->whereIn('status', self::ACTIVE_STATUSES);
        $recovered = $recoveries->where('status', 'recovered');

        return [
            'records' => $recoveries,
            'current_cart_count' => $active->count(),
            'abandoned_count' => $recoveries->where('status', 'open')->count(),
            'incomplete_count' => $active->filter(fn (CheckoutRecovery $recovery): bool => filled($recovery->customer_phone) || filled($recovery->address))->count(),
            'callback_count' => $active->filter(fn (CheckoutRecovery $recovery): bool => filled($recovery->customer_phone))->count(),
            'recovered_count' => $recovered->count(),
            'recovered_revenue' => $recovered->isNotEmpty()
                ? (float) $customer->orders()->whereNotIn('status', ['cancelled', 'returned'])->sum('total')
                : 0.0,
            'latest_score' => $recoveries->first() ? $this->recoveryScore($recoveries->first()) : null,
            'behavior_signals' => $recoveries->first()
                ? $this->behaviorEventService->recoverySignals($recoveries->first())
                : ['events' => collect(), 'fresh' => false],
            'timeline' => $recoveries
                ->flatMap(fn (CheckoutRecovery $recovery) => $this->recoveryTimeline($recovery)->map(fn (array $event): array => array_merge($event, [
                    'recovery_id' => $recovery->id,
                ])))
                ->sortByDesc('at')
                ->values(),
        ];
    }

    public function statusOptions(): array
    {
        return [
            'open' => 'Pending',
            'contacted' => 'Called / Contacted',
            'called' => 'Called',
            'no_answer' => 'No Answer',
            'interested' => 'Interested',
            'not_interested' => 'Not Interested',
            'recovered' => 'Recovered',
            'lost' => 'Lost',
            'closed' => 'Closed',
        ];
    }

    public function tabLabels(): array
    {
        return [
            'abandoned' => 'Abandoned Cart',
            'incomplete' => 'Incomplete Checkout',
            'callback' => 'Callback Queue',
        ];
    }

    protected function findExistingRecovery(array $data): ?CheckoutRecovery
    {
        if (! empty($data['recovery_id'])) {
            $recovery = CheckoutRecovery::whereIn('status', ['open', 'contacted'])
                ->find($data['recovery_id']);

            if ($recovery) {
                return $recovery;
            }
        }

        $phone = $this->normalizedPhone($data['customer_phone'] ?? $data['phone'] ?? null);

        if ($phone) {
            return CheckoutRecovery::whereIn('status', ['open', 'contacted'])
                ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $phone))
                ->when(isset($data['product_id']), fn ($query) => $query->where('product_id', $data['product_id']))
                ->when(array_key_exists('variant_id', $data), fn ($query) => $query->where('variant_id', $data['variant_id']))
                ->latest()
                ->first();
        }

        return null;
    }

    protected function touchShadowActivity(CheckoutRecovery $recovery): void
    {
        try {
            if (! $recovery->customer_phone) {
                return;
            }

            $customer = Customer::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $recovery->customer_phone))->first();

            if (! $customer) {
                return;
            }

            $this->shadowAccountService->touchActivity($customer);
        } catch (Throwable $exception) {
            logger()->warning('Recovery shadow account activity tracking failed', [
                'recovery_id' => $recovery->id,
                'phone_hash' => $this->phoneService->hash($recovery->customer_phone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function createRecoveryCommunication(CheckoutRecovery $recovery, string $template): void
    {
        try {
            $this->communicationService->generateRecoveryWorkflow($recovery, $template);
        } catch (Throwable $exception) {
            logger()->warning('Recovery communication automation failed', [
                'recovery_id' => $recovery->id,
                'template' => $template,
                'phone_hash' => $this->phoneService->hash($recovery->customer_phone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function evaluateMarketingSegments(CheckoutRecovery $recovery, string $trigger): void
    {
        try {
            if (! $recovery->customer_phone) {
                return;
            }

            $customer = Customer::where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $recovery->customer_phone))->first();

            if (! $customer) {
                return;
            }

            $this->marketingSegmentEngine->evaluateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment recovery evaluation failed', [
                'recovery_id' => $recovery->id,
                'trigger' => $trigger,
                'phone_hash' => $this->phoneService->hash($recovery->customer_phone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function triggerAutomation(CheckoutRecovery $recovery, string $trigger): void
    {
        if ($recovery->status !== 'open') {
            return;
        }

        try {
            app(AutomationEngineService::class)->handleTrigger('checkout.abandoned', $recovery, [
                'recovery_id' => $recovery->id,
                'trigger' => $trigger,
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Recovery automation trigger failed', [
                'recovery_id' => $recovery->id,
                'trigger' => $trigger,
                'phone_hash' => $this->phoneService->hash($recovery->customer_phone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function normalizedPhone(?string $phone): ?string
    {
        $normalized = $this->phoneService->normalize($phone);

        return $normalized !== '' ? $normalized : null;
    }

    protected function customerForRecovery(CheckoutRecovery $recovery): ?Customer
    {
        if (! filled($recovery->customer_phone)) {
            return null;
        }

        return Customer::query()
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', (string) $recovery->customer_phone))
            ->first();
    }

    protected function recoveryValue(CheckoutRecovery $recovery): float
    {
        $price = (float) ($recovery->variant?->price ?? $recovery->product?->price ?? 0);

        return $price * max(1, (int) $recovery->quantity);
    }

    protected function completionStage(CheckoutRecovery $recovery): string
    {
        if (filled($recovery->address)) {
            return 'Left Before Submit';
        }

        if (filled($recovery->customer_phone) && filled($recovery->customer_name)) {
            return 'Checkout Started';
        }

        if (filled($recovery->customer_phone)) {
            return 'Phone Entered';
        }

        return 'Cart';
    }

    protected function recoveredRevenue(): float
    {
        $phones = CheckoutRecovery::query()
            ->where('status', 'recovered')
            ->whereNotNull('customer_phone')
            ->pluck('customer_phone')
            ->flatMap(fn ($phone): array => $this->phoneService->lookupValues((string) $phone))
            ->filter()
            ->unique()
            ->values();

        if ($phones->isEmpty()) {
            return 0.0;
        }

        return (float) Order::query()
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereIn('customer_phone', $phones->all())
            ->sum('total');
    }
}
