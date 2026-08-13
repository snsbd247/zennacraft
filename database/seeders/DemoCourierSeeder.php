<?php

namespace Database\Seeders;

use App\Modules\Courier\Models\CourierMetric;
use App\Modules\Courier\Models\CourierProvider;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Order\Models\Order;
use App\Modules\Tracking\Models\ShipmentTrackingEvent;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoCourierSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $providers = $this->providers();
        $orders = Order::query()
            ->where('order_number', 'like', 'DEMO-ORD-%')
            ->whereNotIn('status', ['pending'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $existingShipmentIds = Shipment::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->pluck('id');

        ShipmentTrackingEvent::query()->whereIn('shipment_id', $existingShipmentIds)->delete();
        Shipment::query()->whereIn('order_id', $orders->pluck('id'))->delete();

        foreach ($orders as $index => $order) {
            $provider = $providers[$index % $providers->count()];
            $status = $this->shipmentStatus((string) $order->status);
            $assignedAt = $order->created_at?->copy()->addHours(8) ?? now()->subDays(2);
            $shippedAt = in_array($status, ['shipped', 'delivered', 'returned'], true) ? $assignedAt->copy()->addHours(12) : null;
            $deliveredAt = $status === 'delivered' ? $assignedAt->copy()->addDays(2 + ($index % 3)) : null;
            $returnedAt = $status === 'returned' ? $assignedAt->copy()->addDays(4 + ($index % 2)) : null;

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'courier_provider_id' => $provider->id,
                'tracking_number' => 'DEMO-TRK-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'consignment_id' => 'DEMO-CNS-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'delivery_charge' => $order->delivery_fee,
                'cod_amount' => $order->total,
                'courier_cost' => $status === 'cancelled' ? 0 : round(((float) $order->delivery_fee) * 0.72, 2),
                'assigned_at' => $assignedAt,
                'shipped_at' => $shippedAt,
                'delivered_at' => $deliveredAt,
                'returned_at' => $returnedAt,
                'notes' => 'DEMO QA shipment for courier intelligence testing.',
                'created_at' => $assignedAt,
                'updated_at' => $deliveredAt ?? $returnedAt ?? $shippedAt ?? $assignedAt,
            ]);

            $this->trackingEvents($shipment, $assignedAt, $shippedAt, $deliveredAt, $returnedAt);
        }

        $this->refreshMetrics($providers);
    }

    protected function providers(): Collection
    {
        return collect([
            ['Steadfast', 'steadfast'],
            ['Pathao', 'pathao'],
            ['RedX', 'redx'],
            ['Sundarban', 'sundarban'],
        ])->map(fn (array $provider): CourierProvider => CourierProvider::query()->firstOrCreate(
            ['slug' => $provider[1]],
            [
                'name' => $provider[0],
                'status' => 'active',
                'api_enabled' => false,
                'config' => ['demo' => true],
                'notes' => 'DEMO QA courier provider. No external API calls.',
            ]
        ));
    }

    protected function shipmentStatus(string $orderStatus): string
    {
        return match ($orderStatus) {
            'delivered' => 'delivered',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
            'shipped' => 'shipped',
            default => 'assigned',
        };
    }

    protected function trackingEvents(Shipment $shipment, mixed $assignedAt, mixed $shippedAt, mixed $deliveredAt, mixed $returnedAt): void
    {
        $events = [
            ['assigned', 'Courier assigned', 'Demo shipment assigned to courier.', $assignedAt],
        ];

        if ($shippedAt) {
            $events[] = ['in_transit', 'Parcel in transit', 'Demo shipment left sorting hub.', $shippedAt];
        }

        if ($deliveredAt) {
            $events[] = ['delivered', 'Delivered', 'Demo shipment delivered successfully.', $deliveredAt];
        }

        if ($returnedAt) {
            $events[] = ['returned', 'Returned/RTO', 'Demo shipment returned for RTO testing.', $returnedAt];
        }

        foreach ($events as [$status, $title, $description, $time]) {
            ShipmentTrackingEvent::create([
                'shipment_id' => $shipment->id,
                'order_id' => $shipment->order_id,
                'created_by' => $this->demoStaffId(),
                'status' => $status,
                'title' => $title,
                'description' => $description,
                'event_time' => $time,
            ]);
        }
    }

    protected function refreshMetrics(Collection $providers): void
    {
        foreach ($providers as $provider) {
            $shipments = $provider->shipments()->get();
            $total = max(1, $shipments->count());
            $delivered = $shipments->where('status', 'delivered')->count();
            $returned = $shipments->where('status', 'returned')->count();
            $cancelled = $shipments->where('status', 'cancelled')->count();
            $failed = $shipments->where('status', 'failed')->count();

            CourierMetric::updateOrCreate(
                ['courier_provider_id' => $provider->id],
                [
                    'total_shipments' => $shipments->count(),
                    'assigned_shipments' => $shipments->where('status', 'assigned')->count(),
                    'shipped_shipments' => $shipments->where('status', 'shipped')->count(),
                    'delivered_shipments' => $delivered,
                    'returned_shipments' => $returned,
                    'cancelled_shipments' => $cancelled,
                    'failed_shipments' => $failed,
                    'success_rate' => round(($delivered / $total) * 100, 2),
                    'return_rate' => round(($returned / $total) * 100, 2),
                    'failure_rate' => round((($cancelled + $failed) / $total) * 100, 2),
                    'avg_delivery_days' => round(2.4 + ($provider->id % 3), 2),
                    'total_cod_amount' => $shipments->sum('cod_amount'),
                    'total_delivery_charge' => $shipments->sum('delivery_charge'),
                    'total_courier_cost' => $shipments->sum('courier_cost'),
                    'last_calculated_at' => now(),
                ]
            );
        }
    }
}
