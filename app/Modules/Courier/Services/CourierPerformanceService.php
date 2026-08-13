<?php

namespace App\Modules\Courier\Services;

use App\Modules\Courier\Models\CourierMetric;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CourierPerformanceService
{
    public function __construct(private PhoneService $phoneService) {}

    public function recalculateProvider(CourierProvider $provider): CourierMetric
    {
        $shipments = $provider->shipments()->get();
        $completedShipments = $shipments->whereIn('status', ['delivered', 'returned', 'cancelled', 'failed'])->count();
        $deliveredShipments = $shipments->where('status', 'delivered')->count();
        $returnedShipments = $shipments->where('status', 'returned')->count();
        $failedShipments = $shipments->where('status', 'failed')->count();

        $avgDeliveryDays = $this->averageDeliveryDays($shipments);

        return CourierMetric::updateOrCreate(
            ['courier_provider_id' => $provider->id],
            [
                'total_shipments' => $shipments->count(),
                'assigned_shipments' => $shipments->where('status', 'assigned')->count(),
                'shipped_shipments' => $shipments->where('status', 'shipped')->count(),
                'delivered_shipments' => $deliveredShipments,
                'returned_shipments' => $returnedShipments,
                'cancelled_shipments' => $shipments->where('status', 'cancelled')->count(),
                'failed_shipments' => $failedShipments,
                'success_rate' => $this->rate($deliveredShipments, $completedShipments),
                'return_rate' => $this->rate($returnedShipments, $completedShipments),
                'failure_rate' => $this->rate($failedShipments, $completedShipments),
                'avg_delivery_days' => $avgDeliveryDays,
                'total_cod_amount' => $shipments->sum('cod_amount'),
                'total_delivery_charge' => $shipments->sum('delivery_charge'),
                'total_courier_cost' => $shipments->sum('courier_cost'),
                'last_calculated_at' => now(),
            ]
        );
    }

    public function recalculateAll(): void
    {
        CourierProvider::query()
            ->orderBy('id')
            ->each(fn (CourierProvider $provider) => $this->recalculateProvider($provider));
    }

    public function bestProvider(): ?CourierProvider
    {
        return CourierProvider::query()
            ->where('status', 'active')
            ->whereHas('metric', fn ($query) => $query->where('total_shipments', '>=', 10))
            ->with('metric')
            ->get()
            ->sortBy([
                fn (CourierProvider $provider) => -1 * (float) $provider->metric->success_rate,
                fn (CourierProvider $provider) => (float) $provider->metric->return_rate,
                fn (CourierProvider $provider) => (float) $provider->metric->total_courier_cost,
            ])
            ->first();
    }

    public function metrics(int $perPage = 20): LengthAwarePaginator
    {
        return CourierMetric::query()
            ->with('courierProvider')
            ->orderByDesc('success_rate')
            ->orderBy('return_rate')
            ->orderBy('total_courier_cost')
            ->paginate($perPage);
    }

    public function dashboard(?Collection $comparisonRows = null): array
    {
        $comparisonRows ??= $this->comparisonRows();
        $best = $comparisonRows->where('total', '>', 0)->sortByDesc('score')->first();
        $worst = $comparisonRows->where('total', '>', 0)->sortBy('score')->first();
        $avgDeliveryDays = $comparisonRows
            ->pluck('avg_days')
            ->filter(fn ($days) => $days !== null)
            ->avg();

        return [
            'total_shipments' => (int) Shipment::query()->count(),
            'delivered' => (int) Shipment::query()->where('status', 'delivered')->count(),
            'in_transit' => (int) Shipment::query()->whereIn('status', ['pending', 'assigned', 'shipped'])->count(),
            'returned_rto' => (int) Shipment::query()->whereIn('status', ['returned', 'failed'])->count(),
            'average_delivery_time' => $avgDeliveryDays === null ? null : round((float) $avgDeliveryDays, 2),
            'best_courier' => $best['provider'] ?? null,
            'worst_courier' => $worst['provider'] ?? null,
            'courier_cost' => round((float) Shipment::query()->sum('courier_cost'), 2),
        ];
    }

    public function providerCards(): Collection
    {
        return $this->comparisonRows();
    }

    public function comparisonRows(): Collection
    {
        $providers = CourierProvider::query()
            ->with('metric')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $providers->map(function (CourierProvider $provider): array {
            $metric = $provider->metric;
            $total = (int) ($metric?->total_shipments ?? 0);
            $delivered = (int) ($metric?->delivered_shipments ?? 0);
            $returned = (int) ($metric?->returned_shipments ?? 0);
            $cancelled = (int) ($metric?->cancelled_shipments ?? 0);
            $failed = (int) ($metric?->failed_shipments ?? 0);
            $inTransit = (int) ($metric?->assigned_shipments ?? 0) + (int) ($metric?->shipped_shipments ?? 0);
            $avgCost = $total > 0 ? round((float) ($metric?->total_courier_cost ?? 0) / $total, 2) : 0.0;
            $score = $this->providerScore(
                (float) ($metric?->success_rate ?? 0),
                (float) ($metric?->return_rate ?? 0),
                (float) ($metric?->failure_rate ?? 0),
                $avgCost,
                $metric?->avg_delivery_days === null ? null : (float) $metric->avg_delivery_days,
                $total
            );

            return [
                'provider_id' => $provider->id,
                'provider' => $provider->name,
                'slug' => $provider->slug,
                'total' => $total,
                'delivered' => $delivered,
                'returned' => $returned,
                'cancelled' => $cancelled,
                'failed' => $failed,
                'in_transit' => $inTransit,
                'success_rate' => (float) ($metric?->success_rate ?? 0),
                'rto_rate' => (float) ($metric?->return_rate ?? 0),
                'failure_rate' => (float) ($metric?->failure_rate ?? 0),
                'avg_days' => $metric?->avg_delivery_days === null ? null : (float) $metric->avg_delivery_days,
                'avg_cost' => $avgCost,
                'score' => $score,
                'recommended_for' => $this->recommendedFor(
                    (float) ($metric?->success_rate ?? 0),
                    (float) ($metric?->return_rate ?? 0),
                    $avgCost,
                    $metric?->avg_delivery_days === null ? null : (float) $metric->avg_delivery_days,
                    $total
                ),
            ];
        })->values();
    }

    public function districtIntelligence(int $limit = 12): Collection
    {
        $shipments = Shipment::query()
            ->with(['order:id,address,total,status', 'courierProvider:id,name'])
            ->whereNotNull('courier_provider_id')
            ->get();

        return $this->districtRowsFromShipments($shipments)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    public function customerCourierHistory(?Customer $customer = null, ?string $phone = null): array
    {
        $query = Shipment::query()
            ->with(['order:id,customer_id,customer_phone,order_number,total,status,address', 'courierProvider:id,name', 'trackingEvents'])
            ->whereNotNull('courier_provider_id');

        $customerId = $customer?->id;
        $lookupPhones = $this->courierPhoneLookupValues($phone ?: $customer?->phone);

        if ($customerId || $lookupPhones !== []) {
            $query->whereHas('order', function ($orderQuery) use ($customerId, $lookupPhones): void {
                $orderQuery->where(function ($nested) use ($customerId, $lookupPhones): void {
                    if ($customerId) {
                        $nested->where('customer_id', $customerId);
                    }

                    if ($lookupPhones !== []) {
                        $nested->orWhereIn('customer_phone', $lookupPhones);
                    }
                });
            });
        } else {
            return $this->emptyCustomerHistory();
        }

        return $this->summarizeCustomerShipments($query->latest()->get());
    }

    public function customerCourierHistoriesForOrders(iterable $orders): array
    {
        $orders = collect($orders);
        $customerIds = $orders->pluck('customer_id')->filter()->unique()->values();
        $phones = $orders
            ->pluck('customer_phone')
            ->flatMap(fn ($phone) => $this->courierPhoneLookupValues($phone))
            ->filter()
            ->unique()
            ->values();

        if ($customerIds->isEmpty() && $phones->isEmpty()) {
            return [];
        }

        $shipments = Shipment::query()
            ->with(['order:id,customer_id,customer_phone,order_number,total,status,address', 'courierProvider:id,name'])
            ->whereNotNull('courier_provider_id')
            ->whereHas('order', function ($query) use ($customerIds, $phones): void {
                $query->where(function ($nested) use ($customerIds, $phones): void {
                    if ($customerIds->isNotEmpty()) {
                        $nested->whereIn('customer_id', $customerIds->all());
                    }

                    if ($phones->isNotEmpty()) {
                        $nested->orWhereIn('customer_phone', $phones->all());
                    }
                });
            })
            ->get();

        return $orders->mapWithKeys(function (Order $order) use ($shipments): array {
            $lookupPhones = $this->courierPhoneLookupValues($order->customer_phone);
            $matched = $shipments->filter(function (Shipment $shipment) use ($order, $lookupPhones): bool {
                return ($order->customer_id && $shipment->order?->customer_id === $order->customer_id)
                    || ($lookupPhones !== [] && in_array($shipment->order?->customer_phone, $lookupPhones, true));
            });

            return [$order->id => $this->summarizeCustomerShipments($matched)];
        })->all();
    }

    public function orderCourierIntelligence(Order $order): array
    {
        $order->loadMissing(['customer', 'shipment.courierProvider.metric', 'shipment.trackingEvents', 'riskProfile']);
        $recommendation = $this->recommendationForOrder($order);
        $district = $this->districtFromAddress($order->address);
        $districtRows = $district ? $this->districtRowsForDistrict($district) : collect();

        return [
            'recommendation' => $recommendation,
            'customer_history' => $this->customerCourierHistory($order->customer, $order->customer_phone),
            'district' => $district,
            'district_rows' => $districtRows,
            'assigned_shipment' => $order->shipment,
            'tracking_events' => $order->shipment?->trackingEvents ?: collect(),
            'rto_risk' => [
                'level' => $order->riskProfile?->risk_level,
                'score' => $order->riskProfile?->risk_score,
                'courier_score' => $order->riskProfile?->courier_risk_score,
                'reasons' => $order->riskProfile?->risk_reasons ?: [],
            ],
        ];
    }

    public function recommendationForOrder(Order $order): array
    {
        $rows = $this->comparisonRows()->where('total', '>', 0);

        if ($rows->isEmpty()) {
            return [
                'provider' => null,
                'confidence' => 'Unavailable',
                'score' => 0,
                'reason' => 'No courier shipment history is available yet.',
            ];
        }

        $customerHistory = $this->customerCourierHistory($order->customer, $order->customer_phone);
        $customerRows = collect($customerHistory['providers'] ?? [])->keyBy('provider_id');
        $district = $this->districtFromAddress($order->address);
        $districtRows = $district ? $this->districtRowsForDistrict($district)->keyBy('provider_id') : collect();

        $ranked = $rows->map(function (array $row) use ($customerRows, $districtRows, $order): array {
            $customerRow = $customerRows->get($row['provider_id']);
            $districtRow = $districtRows->get($row['provider_id']);
            $score = (float) $row['score'];
            $reasons = [];

            if ($customerRow && (int) $customerRow['total'] > 0) {
                $score += ((float) $customerRow['success_rate'] - (float) $customerRow['rto_rate']) * 0.25;
                $reasons[] = 'customer history success '.$customerRow['success_rate'].'%';
            }

            if ($districtRow && (int) $districtRow['orders'] > 0) {
                $score += ((float) $districtRow['success_rate'] - (float) $districtRow['rto_rate']) * 0.18;
                $reasons[] = 'district success '.$districtRow['success_rate'].'%';
            }

            if ((float) $order->total >= 5000 && (float) $row['success_rate'] >= 70) {
                $score += 6;
                $reasons[] = 'stronger fit for high value COD orders';
            }

            if ($row['avg_days'] !== null && (float) $row['avg_days'] <= 3) {
                $score += 4;
                $reasons[] = 'faster average delivery';
            }

            if ((float) $row['avg_cost'] > 0) {
                $score -= min(6, (float) $row['avg_cost'] / 100);
            }

            $row['recommendation_score'] = round(max(0, min(100, $score)), 2);
            $row['recommendation_reasons'] = $reasons;

            return $row;
        })->sortByDesc('recommendation_score')->values();

        $best = $ranked->first();
        $confidence = match (true) {
            (float) $best['recommendation_score'] >= 82 => 'High',
            (float) $best['recommendation_score'] >= 60 => 'Medium',
            default => 'Low',
        };

        return [
            'provider_id' => $best['provider_id'],
            'provider' => $best['provider'],
            'confidence' => $confidence,
            'score' => $best['recommendation_score'],
            'reason' => $best['recommendation_reasons'] !== []
                ? $best['provider'].' recommended because '.implode(' and ', $best['recommendation_reasons']).'.'
                : $best['provider'].' recommended from overall courier score and current shipment metrics.',
            'rows' => $ranked,
        ];
    }

    public function recommendProviderForOrder(Order $order): ?CourierProvider
    {
        $recommendation = $this->recommendationForOrder($order);

        if (empty($recommendation['provider_id'])) {
            return $this->bestProvider();
        }

        return CourierProvider::query()->find($recommendation['provider_id']);
    }

    protected function rate(int $part, int $total): float
    {
        if ($total === 0) {
            return 0;
        }

        return round(($part / $total) * 100, 2);
    }

    protected function averageDeliveryDays(Collection $shipments): ?float
    {
        $deliveredShipments = $shipments->filter(
            fn ($shipment) => $shipment->status === 'delivered' && $shipment->assigned_at && $shipment->delivered_at
        );

        if ($deliveredShipments->isEmpty()) {
            return null;
        }

        return round($deliveredShipments->avg(
            fn ($shipment) => $shipment->assigned_at->diffInSeconds($shipment->delivered_at) / 86400
        ), 2);
    }

    protected function providerScore(
        float $successRate,
        float $returnRate,
        float $failureRate,
        float $avgCost,
        ?float $avgDays,
        int $total
    ): float {
        if ($total === 0) {
            return 0.0;
        }

        $score = $successRate - ($returnRate * 0.75) - ($failureRate * 0.5);

        if ($avgDays !== null) {
            $score += max(0, 8 - $avgDays);
        }

        if ($avgCost > 0) {
            $score -= min(8, $avgCost / 100);
        }

        if ($total < 5) {
            $score *= 0.85;
        }

        return round(max(0, min(100, $score)), 2);
    }

    protected function recommendedFor(float $successRate, float $returnRate, float $avgCost, ?float $avgDays, int $total): array
    {
        if ($total === 0) {
            return ['Needs shipment history'];
        }

        $labels = [];

        if ($successRate >= 80 && $returnRate <= 15) {
            $labels[] = 'High Value Orders';
        }

        if ($avgDays !== null && $avgDays <= 3) {
            $labels[] = 'Fast Delivery';
        }

        if ($avgCost > 0 && $avgCost <= 100) {
            $labels[] = 'Low Cost Orders';
        }

        if ($successRate >= 70) {
            $labels[] = 'COD Dispatch';
        }

        return $labels ?: ['Manual Review'];
    }

    protected function summarizeCustomerShipments(Collection $shipments): array
    {
        $rows = $shipments
            ->groupBy('courier_provider_id')
            ->map(function (Collection $providerShipments): array {
                $provider = $providerShipments->first()?->courierProvider;
                $total = $providerShipments->count();
                $delivered = $providerShipments->where('status', 'delivered')->count();
                $cancelled = $providerShipments->where('status', 'cancelled')->count();
                $returned = $providerShipments->whereIn('status', ['returned', 'failed'])->count();
                $lastShipment = $providerShipments->sortByDesc('updated_at')->first();

                return [
                    'provider_id' => $provider?->id,
                    'provider' => $provider?->name ?: 'Unassigned',
                    'total' => $total,
                    'delivered' => $delivered,
                    'cancelled' => $cancelled,
                    'returned' => $returned,
                    'success_rate' => $this->rate($delivered, $total),
                    'rto_rate' => $this->rate($returned, $total),
                    'last_status' => $lastShipment?->status,
                    'last_order' => $lastShipment?->order?->order_number,
                ];
            })
            ->sortByDesc('success_rate')
            ->values();

        $total = $shipments->count();
        $delivered = $shipments->where('status', 'delivered')->count();
        $returned = $shipments->whereIn('status', ['returned', 'failed'])->count();
        $cancelled = $shipments->where('status', 'cancelled')->count();
        $successRate = $this->rate($delivered, $total);
        $riskLevel = match (true) {
            $total === 0 => 'Unknown',
            $successRate >= 80 && ($returned + $cancelled) <= 1 => 'Low',
            $successRate >= 50 => 'Medium',
            default => 'High',
        };

        return [
            'total_orders' => $total,
            'delivered' => $delivered,
            'returned' => $returned,
            'cancelled' => $cancelled,
            'success_rate' => $successRate,
            'best_provider' => $rows->first()['provider'] ?? null,
            'risk_level' => $riskLevel,
            'providers' => $rows,
            'latest_tracking_events' => $shipments
                ->flatMap(fn (Shipment $shipment) => $shipment->relationLoaded('trackingEvents') ? $shipment->trackingEvents : collect())
                ->sortByDesc('event_time')
                ->take(5)
                ->values(),
        ];
    }

    protected function emptyCustomerHistory(): array
    {
        return [
            'total_orders' => 0,
            'delivered' => 0,
            'returned' => 0,
            'cancelled' => 0,
            'success_rate' => 0.0,
            'best_provider' => null,
            'risk_level' => 'Unknown',
            'providers' => collect(),
            'latest_tracking_events' => collect(),
        ];
    }

    protected function districtRowsForDistrict(string $district): Collection
    {
        $shipments = Shipment::query()
            ->with(['order:id,address,total,status', 'courierProvider:id,name'])
            ->whereNotNull('courier_provider_id')
            ->get()
            ->filter(fn (Shipment $shipment) => strcasecmp((string) $this->districtFromAddress($shipment->order?->address), $district) === 0);

        return $this->districtRowsFromShipments($shipments);
    }

    protected function districtRowsFromShipments(Collection $shipments): Collection
    {
        return $shipments
            ->filter(fn (Shipment $shipment) => filled($this->districtFromAddress($shipment->order?->address)))
            ->groupBy(fn (Shipment $shipment) => $this->districtFromAddress($shipment->order?->address).'|'.$shipment->courier_provider_id)
            ->map(function (Collection $districtShipments): array {
                $first = $districtShipments->first();
                $district = $this->districtFromAddress($first?->order?->address);
                $provider = $first?->courierProvider;
                $orders = $districtShipments->count();
                $delivered = $districtShipments->where('status', 'delivered')->count();
                $returned = $districtShipments->whereIn('status', ['returned', 'failed'])->count();
                $cancelled = $districtShipments->where('status', 'cancelled')->count();
                $avgDays = $this->averageDeliveryDays($districtShipments);
                $avgCost = $orders > 0 ? round((float) $districtShipments->sum('courier_cost') / $orders, 2) : 0.0;
                $successRate = $this->rate($delivered, $orders);
                $rtoRate = $this->rate($returned, $orders);

                return [
                    'district' => $district,
                    'provider_id' => $provider?->id,
                    'provider' => $provider?->name ?: 'Unassigned',
                    'orders' => $orders,
                    'delivered' => $delivered,
                    'returned' => $returned,
                    'cancelled' => $cancelled,
                    'success_rate' => $successRate,
                    'rto_rate' => $rtoRate,
                    'avg_days' => $avgDays,
                    'avg_cost' => $avgCost,
                    'score' => $this->providerScore($successRate, $rtoRate, 0, $avgCost, $avgDays, $orders),
                ];
            })
            ->values();
    }

    protected function districtFromAddress(?string $address): ?string
    {
        $parts = collect(preg_split('/,|\n/', (string) $address))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->values();

        return $parts->last() ?: null;
    }

    protected function courierPhoneLookupValues(?string $phone): array
    {
        return collect($this->phoneService->lookupValues($phone))
            ->flatMap(fn (string $value) => [$value, '+'.$value])
            ->unique()
            ->values()
            ->all();
    }
}
