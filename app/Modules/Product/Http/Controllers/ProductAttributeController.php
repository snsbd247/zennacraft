<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Product\Models\ProductAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProductAttributeController extends Controller
{
    public function index(): View
    {
        return view('studio.products.attributes.index', [
            'attributes' => ProductAttribute::withCount('values')->orderBy('sort_order')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('studio.products.attributes.form', ['attribute' => new ProductAttribute()]);
    }

    public function store(Request $request): RedirectResponse
    {
        ProductAttribute::create($request->validate(['name' => ['required', 'string', 'max:120']]));

        return redirect()->route('products.attributes.index')->with('success', 'Attribute added.');
    }

    public function edit(ProductAttribute $attribute): View
    {
        return view('studio.products.attributes.form', ['attribute' => $attribute]);
    }

    public function update(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $attribute->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return redirect()->route('products.attributes.index')->with('success', 'Attribute updated.');
    }

    public function toggleStatus(ProductAttribute $attribute): RedirectResponse
    {
        $attribute->update(['status' => $attribute->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        $attribute->delete();

        return back()->with('success', 'Attribute deleted.');
    }
}
