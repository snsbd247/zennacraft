<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    private const PER_PAGE = [20, 50, 100];

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE, true) ? (int) $request->query('per_page') : 50;
        $invoice = trim((string) $request->query('invoice', ''));

        $purchases = Purchase::with('supplier')->withCount('items')
            ->when($invoice !== '', fn ($q) => $q->where('invoice_no', 'like', '%'.$invoice.'%'))
            ->when($request->query('from'), fn ($q) => $q->whereDate('purchase_date', '>=', $request->query('from')))
            ->when($request->query('to'), fn ($q) => $q->whereDate('purchase_date', '<=', $request->query('to')))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        return view('studio.purchases.index', [
            'purchases' => $purchases,
            'filters' => ['invoice' => $invoice, 'from' => $request->query('from'), 'to' => $request->query('to'), 'per_page' => $perPage],
            'perPageOptions' => self::PER_PAGE,
        ]);
    }

    public function create(Request $request): View
    {
        return view('studio.purchases.form', [
            'purchase' => new Purchase(['purchase_date' => now()->toDateString()]),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'selectedSupplier' => (int) $request->query('supplier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $purchase = DB::transaction(function () use ($data) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'invoice_no' => $data['invoice_no'] ?? null,
                'comment' => $data['comment'] ?? null,
                'paid_amount' => $data['paid_amount'] ?? 0,
                'total_amount' => 0,
                'created_by' => Auth::guard('staff')->id(),
                'created_by_name' => Auth::guard('staff')->user()?->name,
            ]);
            $this->syncItems($purchase, $data['items']);

            return $purchase;
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase #'.$purchase->id.' recorded.');
    }

    public function show(Purchase $purchase): View
    {
        return view('studio.purchases.show', ['purchase' => $purchase->load('supplier', 'items')]);
    }

    public function edit(Purchase $purchase): View
    {
        return view('studio.purchases.form', [
            'purchase' => $purchase->load('items'),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'selectedSupplier' => (int) $purchase->supplier_id,
        ]);
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($purchase, $data) {
            $this->reverseStock($purchase);
            $purchase->items()->delete();
            $purchase->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'invoice_no' => $data['invoice_no'] ?? null,
                'comment' => $data['comment'] ?? null,
                'paid_amount' => $data['paid_amount'] ?? 0,
            ]);
            $this->syncItems($purchase, $data['items']);
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase #'.$purchase->id.' updated.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($purchase) {
            $this->reverseStock($purchase);
            $purchase->delete();
        });

        return back()->with('success', 'Purchase deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_code' => ['nullable', 'string', 'max:120'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function syncItems(Purchase $purchase, array $items): void
    {
        $total = 0;
        foreach ($items as $item) {
            $qty = (int) $item['quantity'];
            $price = (float) $item['purchase_price'];
            $subtotal = $qty * $price;
            $total += $subtotal;

            $code = trim((string) ($item['product_code'] ?? ''));
            $product = $code !== '' ? Product::where('sku', $code)->first() : null;
            if ($product) {
                $product->increment('stock', $qty);
            }

            $purchase->items()->create([
                'product_id' => $product?->id,
                'product_code' => $code ?: null,
                'product_name' => $product?->name ?: ($code ?: 'Item'),
                'purchase_price' => $price,
                'quantity' => $qty,
                'subtotal' => $subtotal,
            ]);
        }

        $purchase->update(['total_amount' => $total]);
    }

    private function reverseStock(Purchase $purchase): void
    {
        foreach ($purchase->items()->whereNotNull('product_id')->get() as $item) {
            if ($product = Product::find($item->product_id)) {
                $product->update(['stock' => max(0, (int) $product->stock - (int) $item->quantity)]);
            }
        }
    }
}
