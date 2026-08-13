<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Order\Models\Order;
use App\Modules\Shared\Services\PhoneService;
use App\Modules\Tracking\Models\ShipmentTrackingEvent;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class TrackingService
{
    protected const STATUSES = [
        'assigned' => 'Assigned',
        'picked_up' => 'Picked Up',
        'in_transit' => 'In Transit',
        'hub_received' => 'Hub Received',
        'out_for_delivery' => 'Out For Delivery',
        'delivered' => 'Delivered',
        'returned' => 'Returned',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'note' => 'Note',
    ];

    public function __construct(
        private AuditService $auditService,
        private PhoneService $phoneService,
    ) {}

    public function createEvent(Shipment $shipment, array $data): ShipmentTrackingEvent
    {
        $event = $shipment->trackingEvents()->create([
            'order_id' => $shipment->order_id,
            'created_by' => auth()->guard('staff')->id(),
            'status' => $data['status'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'event_time' => $data['event_time'] ?? now(),
        ]);

        $this->logAudit($event);

        return $event->load(['shipment', 'order', 'createdBy']);
    }

    public function timelineForShipment(Shipment $shipment): Collection
    {
        return $shipment->trackingEvents()
            ->with('createdBy')
            ->orderBy('event_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function timelineForOrder(Order $order): Collection
    {
        return $order->trackingEvents()
            ->with(['shipment.courierProvider', 'createdBy'])
            ->orderBy('event_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /** Public tracking by order number alone (for the live tracking page). */
    public function publicLookupByNumber(string $orderNumber): ?Order
    {
        return Order::query()
            ->where('order_number', trim($orderNumber))
            ->with(['items.product.thumbnail', 'shipment.courierProvider', 'shipment.trackingEvents', 'trackingEvents'])
            ->first();
    }

    public function publicLookup(string $orderNumber, string $phone): ?Order
    {
        $order = Order::query()
            ->where('order_number', trim($orderNumber))
            ->with(['shipment.courierProvider', 'shipment.trackingEvents', 'trackingEvents'])
            ->first();

        if (! $order) {
            return null;
        }

        if ($this->phoneService->normalize($order->customer_phone) !== $this->phoneService->normalize($phone)) {
            return null;
        }

        return $order;
    }

    public function statusLabels(): array
    {
        return self::STATUSES;
    }

    protected function logAudit(ShipmentTrackingEvent $event): void
    {
        try {
            $this->auditService->log(
                'tracking.event',
                'create',
                'tracking',
                'Tracking event created: '.$event->title,
                [
                    'shipment_id' => $event->shipment_id,
                    'order_id' => $event->order_id,
                    'status' => $event->status,
                    'title' => $event->title,
                ]
            );
        } catch (Throwable $exception) {
            logger()->warning('Tracking audit logging failed', [
                'tracking_event_id' => $event->id,
                'shipment_id' => $event->shipment_id,
                'order_id' => $event->order_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

}
