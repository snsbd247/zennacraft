<?php

namespace App\Modules\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrackingResource;
use App\Modules\Shared\Http\ApiResponse;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingApiController extends Controller
{
    use ApiResponse;

    public function __construct(private TrackingService $trackingService) {}

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $order = $this->trackingService->publicLookup($validated['order_number'], $validated['phone']);

        if (! $order) {
            return $this->error('Tracking information was not found.', [
                'order_number' => ['The order number and phone combination is invalid.'],
            ], 404);
        }

        return $this->success([
            'tracking' => new TrackingResource($order),
        ]);
    }
}
