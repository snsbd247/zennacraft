<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Models\ProductAttribute;
use App\Modules\Product\Models\ProductAttributeValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProductAttributeValueController extends Controller
{
    public function index(): View
    {
        return view('studio.products.variants.index', [
            'values' => ProductAttributeValue::with('attribute')->orderByDesc('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('studio.products.variants.form', [
            'value' => new ProductAttributeValue(),
            'attributes' => ProductAttribute::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ProductAttributeValue::create($request->validate([
            'attribute_id' => ['required', 'integer', 'exists:product_attributes,id'],
            'name' => ['required', 'string', 'max:120'],
        ]));

        return redirect()->route('products.variants.index')->with('success', 'Variant added.');
    }

    public function edit(ProductAttributeValue $variant): View
    {
        return view('studio.products.variants.form', [
            'value' => $variant,
            'attributes' => ProductAttribute::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ProductAttributeValue $variant): RedirectResponse
    {
        $variant->update($request->validate([
            'attribute_id' => ['required', 'integer', 'exists:product_attributes,id'],
            'name' => ['required', 'string', 'max:120'],
        ]));

        return redirect()->route('products.variants.index')->with('success', 'Variant updated.');
    }

    public function toggleStatus(ProductAttributeValue $variant): RedirectResponse
    {
        $variant->update(['status' => $variant->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(ProductAttributeValue $variant): RedirectResponse
    {
        $variant->delete();

        return back()->with('success', 'Variant deleted.');
    }
}
