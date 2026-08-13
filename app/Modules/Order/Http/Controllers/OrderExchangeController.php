<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderExchangeService;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderExchangeController extends Controller
{
    public function __construct(private OrderExchangeService $orderExchangeService) {}

    /**
     * "Add Exchange Order" — pick the original order, then choose the
     * replacement product to ship instead.
     */
    public function create(Request $request): View
    {
        $original = null;

        if ($request->filled('order_id')) {
            $original = Order::with(['items', 'customer'])->find($request->integer('order_id'));
        }

        return view('studio.orders.exchange', [
            'original' => $original,
            'products' => Product::query()
                ->where('status', 'active')
                ->with(['variants' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'price', 'stock']),
        ]);
    }

    /**
     * AJAX: search original orders by order number or phone.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $orders = Order::query()
            ->where(fn ($query) => $query
                ->where('order_number', 'like', '%'.$term.'%')
                ->orWhere('customer_phone', 'like', '%'.$term.'%')
                ->orWhere('customer_name', 'like', '%'.$term.'%'))
            ->latest()
            ->limit(10)
            ->get(['id', 'order_number', 'customer_name', 'customer_phone', 'total', 'status']);

        return response()->json([
            'results' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'total' => number_format((float) $order->total, 2),
                'status' => $order->status,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'original_order_id' => ['required', 'integer', 'exists:orders,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $original = Order::findOrFail($validated['original_order_id']);

        try {
            $exchange = $this->orderExchangeService->create($original, [[
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'],
                'price' => $validated['price'] ?? null,
            ]], $validated['note'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('orders.show', $exchange)
            ->with('success', 'Exchange order '.$exchange->order_number.' created for '.$original->order_number.'.');
    }
}
