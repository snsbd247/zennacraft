<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\Customer\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ownsResource = $request->user() instanceof Customer
            && (int) $request->user()->getKey() === (int) $this->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->when($ownsResource, $this->email),
            'address' => $this->when($ownsResource, $this->address),
            'total_orders' => $this->total_orders,
            'total_spent' => $this->total_spent,
            'delivered_orders' => $this->delivered_orders,
            'cancelled_orders' => $this->cancelled_orders,
            'returned_orders' => $this->returned_orders,
            'first_order_at' => $this->first_order_at,
            'last_order_at' => $this->last_order_at,
        ];
    }
}
