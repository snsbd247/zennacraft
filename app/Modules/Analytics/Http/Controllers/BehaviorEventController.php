<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Services\BehaviorEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class BehaviorEventController extends Controller
{
    public function __construct(private BehaviorEventService $behaviorEventService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::in(BehaviorEventService::allowedEventTypes())],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'source' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array', 'max:30'],
        ]);

        $this->behaviorEventService->record($request, $validated['event_type'], [
            'product_id' => $validated['product_id'] ?? null,
            'product_variant_id' => $validated['product_variant_id'] ?? $validated['variant_id'] ?? null,
            'order_id' => $validated['order_id'] ?? null,
            'coupon_id' => $validated['coupon_id'] ?? null,
            'source' => $validated['source'] ?? null,
            'metadata' => $validated['metadata'] ?? [],
        ]);

        return response()->json(['status' => 'ok']);
    }
}
