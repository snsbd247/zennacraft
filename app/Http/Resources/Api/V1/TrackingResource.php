<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Courier\Models\Shipment;
use App\Modules\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Shipment) {
            return $this->shipmentToArray($this->resource);
        }

        if ($this->resource instanceof Order) {
            $shipment = $this->resource->shipment;

            return [
                'order_number' => $this->resource->order_number,
                'order_status' => $this->resource->status,
                'shipment' => $shipment ? $this->shipmentToArray($shipment) : null,
            ];
        }

        return [];
    }

    protected function shipmentToArray(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'status' => $shipment->status,
            'tracking_number' => $shipment->tracking_number,
            'courier' => $shipment->relationLoaded('courierProvider') && $shipment->courierProvider ? [
                'id' => $shipment->courierProvider->id,
                'name' => $shipment->courierProvider->name,
                'slug' => $shipment->courierProvider->slug,
            ] : null,
            'timeline' => $shipment->relationLoaded('trackingEvents')
                ? $shipment->trackingEvents->map(fn ($event) => [
                    'status' => $event->status,
                    'title' => $event->title,
                    'event_time' => $event->event_time,
                ])->values()
                : [],
        ];
    }
}
