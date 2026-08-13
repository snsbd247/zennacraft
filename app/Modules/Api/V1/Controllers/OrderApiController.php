<?php

namespace App\Modules\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $orders = Order::query()
            ->where('customer_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 20));

        return $this->paginated($orders, OrderResource::class);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_if((int) $order->customer_id !== (int) $request->user()->id, 404);

        $order->load(['items', 'shipment.courierProvider', 'shipment.trackingEvents']);

        return $this->success([
            'order' => new OrderResource($order),
        ]);
    }
}
