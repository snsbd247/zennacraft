<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Courier\Services\CourierService;
use App\Modules\Finance\Models\Account;
use App\Modules\Media\Services\MediaService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderManagementService;
use App\Modules\Product\Models\Product;
use App\Modules\Shared\Support\BangladeshDistricts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderCreateController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private OrderManagementService $orderManagementService,
        private CourierService $courierService,
        private MediaService $mediaService,
    ) {}

    /**
     * "Create Order" — the admin/POS manual order page.
     */
    public function create(): View
    {
        return view('studio.orders.create', [
            'couriers' => $this->courierService->activeProviders(),
            'districts' => BangladeshDistricts::ALL,
            'sources' => Order::SOURCES,
            'accounts' => Account::query()->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => $this->orderManagementService->allowedStatuses(),
        ]);
    }

    /**
     * AJAX product lookup by code/name for the barcode/search box.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $products = Product::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'))
            ->with(['thumbnail', 'variants' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json([
            'results' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
                'thumb' => $product->thumbnail ? $this->mediaService->url($product->thumbnail) : null,
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => $v->color?->name ?: ($v->option_values['Color'] ?? $v->name),
                    'price' => (float) $v->price,
                    'stock' => (int) $v->stock,
                ])->values(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'district' => ['nullable', 'string', 'max:255'],
            'sub_city' => ['nullable', 'string', 'max:255'],
            'courier_provider_id' => ['nullable', 'integer', 'exists:courier_providers,id'],
            'source' => ['nullable', 'string', 'in:'.implode(',', Order::SOURCES)],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'paid_by' => ['nullable', 'string', 'max:255'],
            'shipping_charge' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:'.implode(',', $this->orderManagementService->allowedStatuses())],
        ]);

        $items = $this->buildItems($validated['items']);
        $subtotal = array_sum(array_map(fn ($i) => (float) $i['subtotal'], $items));
        $discount = (float) ($validated['discount'] ?? 0);
        $shipping = (float) ($validated['shipping_charge'] ?? 0);
        $total = max(0, $subtotal - $discount + $shipping);

        $address = trim(($validated['address'] ?? '').(filled($validated['sub_city'] ?? null) ? ', '.$validated['sub_city'] : ''));

        try {
            $order = $this->checkoutService->createOrderFromItems($items, [
                'customer_name' => $validated['name'],
                'customer_phone' => $validated['phone'],
                'address' => $address ?: 'N/A',
                'district' => $validated['district'] ?? null,
                'delivery_fee' => $shipping,
                'payment_method' => 'cod',
                'manual_discount_type' => $discount > 0 ? 'flat' : null,
                'manual_discount_value' => $discount > 0 ? $discount : null,
                'manual_discount_amount' => $discount,
                'paid_amount' => (float) ($validated['paid'] ?? 0),
                'paid_by' => $validated['paid_by'] ?? null,
                'total' => $total,
                'source' => $validated['source'] ?? 'custom',
                'notes' => $validated['note'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        // A chosen non-pending status is applied through the real transition
        // flow so inventory/risk/ledger side-effects run properly.
        $status = $validated['status'] ?? 'pending';
        if ($status !== 'pending') {
            $this->orderManagementService->updateStatus($order, $status, 'Set on manual order creation.');
        }

        if (! empty($validated['courier_provider_id'])) {
            try {
                $this->courierService->assignShipment($order, ['courier_provider_id' => $validated['courier_provider_id']]);
            } catch (ValidationException) {
                // Courier assignment is best-effort here; the order still exists.
            }
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order '.$order->order_number.' created.');
    }

    /**
     * @param  array<int, array{product_id:int, variant_id:?int, quantity:int, price:float}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $rows): array
    {
        $productIds = array_column($rows, 'product_id');
        $products = Product::query()->whereIn('id', $productIds)->with('variants')->get()->keyBy('id');

        return array_map(function (array $row) use ($products) {
            $product = $products->get($row['product_id']);
            $variantId = $row['variant_id'] ?? null;
            $variant = $variantId ? $product?->variants->firstWhere('id', $variantId) : null;
            $price = (float) $row['price'];
            $qty = (int) $row['quantity'];

            return [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'category_id' => $product->category_id,
                'landing_page_id' => null,
                'product_name' => $variant ? $product->name.' - '.$variant->name : $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => $price,
                'unit_cost' => (float) ($variant?->cost_price ?? $product->cost_price ?? 0),
                'quantity' => $qty,
                'subtotal' => $price * $qty,
            ];
        }, $rows);
    }
}
