<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductDamage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProductDamageController extends Controller
{
    public function index(): View
    {
        return view('studio.products.damages.index', [
            'damages' => ProductDamage::withCount('items')->orderByDesc('damage_date')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('studio.products.damages.create', [
            'products' => Product::orderBy('name')->get(['id', 'name', 'cost_price']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'damage_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $damage = ProductDamage::create(['damage_date' => $data['damage_date'], 'note' => $data['note'] ?? null, 'total_amount' => 0]);

        $total = 0;
        foreach ($data['items'] as $item) {
            $qty = (int) $item['quantity'];
            $cost = (float) $item['unit_cost'];
            $subtotal = $qty * $cost;
            $total += $subtotal;
            $name = $item['product_name'] ?? null;
            if (! $name && ! empty($item['product_id'])) {
                $name = Product::find($item['product_id'])?->name ?? 'Item';
            }
            $damage->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $name ?: 'Item',
                'quantity' => $qty, 'unit_cost' => $cost, 'subtotal' => $subtotal,
            ]);
        }

        $damage->update(['total_amount' => $total]);

        return redirect()->route('products.damages.index')->with('success', 'Damage record added.');
    }

    public function show(ProductDamage $damage): View
    {
        return view('studio.products.damages.show', ['damage' => $damage->load('items')]);
    }

    public function destroy(ProductDamage $damage): RedirectResponse
    {
        $damage->delete();

        return back()->with('success', 'Damage record deleted.');
    }
}
