<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Point of Sale — a fast in-store sales terminal for counter/in-person sales.
 * It reuses the exact same order engine the website uses (CheckoutService +
 * OrderManagementService), so a POS sale is a normal order with source "pos":
 * stock is deducted, revenue/profit recorded, and it shows up everywhere orders
 * do. No new order/inventory logic — pure orchestration of existing services.
 */
class PosController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private OrderManagementService $orderManagementService,
        private MediaService $mediaService,
    ) {}

    public function index(): View
    {
        return view('studio.pos.index');
    }

    /**
     * Product search for the terminal (name / SKU), with stock + price so the
     * cashier can pick fast. Same shape as the manual-order product search.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $products = Product::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'))
            ->with(['thumbnail', 'variants' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) $p->price,
                'stock' => (int) $p->stock,
                'thumb' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => $v->name,
                    'price' => (float) $v->price,
                    'stock' => (int) $v->stock,
                ])->values(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $items = array_map(
                fn (array $r) => $this->checkoutService->prepareItem(
                    (int) $r['product_id'],
                    isset($r['variant_id']) ? (int) $r['variant_id'] : null,
                    (int) $r['quantity'],
                    isset($r['price']) ? (float) $r['price'] : null,
                ),
                $validated['items'],
            );

            $subtotal = array_sum(array_map(fn ($i) => (float) $i['subtotal'], $items));
            $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
            $total = max(0, $subtotal - $discount);

            // Walk-in (no phone) sales all map to one shared "Walk-in" customer.
            // The placeholder must be numeric so PhoneService normalises it
            // consistently — a text value (e.g. "POS") normalises to "" and the
            // customer sync (which runs on create AND on the status change) then
            // tries to insert a duplicate.
            $order = $this->checkoutService->createOrderFromItems($items, [
                'customer_name' => trim((string) ($validated['customer_name'] ?? '')) ?: 'Walk-in customer',
                'customer_phone' => trim((string) ($validated['customer_phone'] ?? '')) ?: '01000000000',
                'address' => 'In-store sale (POS)',
                'delivery_fee' => 0,
                'payment_method' => 'cash',
                'manual_discount_type' => $discount > 0 ? 'flat' : null,
                'manual_discount_value' => $discount > 0 ? $discount : null,
                'manual_discount_amount' => $discount,
                'paid_amount' => $total,
                'paid_by' => 'cash',
                'total' => $total,
                'source' => 'pos',
            ]);

            // Deduct stock (confirmed reserves inventory) and complete the sale.
            $order = $this->orderManagementService->updateStatus($order, 'confirmed', 'POS sale');
            $order = $this->orderManagementService->updateStatus($order, 'delivered', 'POS sale — paid in store');
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'order_number' => $order->order_number,
            'total' => $total,
            'receipt_url' => route('orders.pos-print', $order),
        ]);
    }
}
