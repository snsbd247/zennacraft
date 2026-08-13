<?php

namespace App\Modules\Promotion\Http\Controllers;

use App\Modules\Product\Models\Product;
use App\Modules\Promotion\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Studio manager for placement-based storefront offers. One sidebar entry; each
 * offer names its placement (see Offer::PLACEMENTS) so it's obvious where it shows.
 */
class OfferController extends Controller
{
    public function index(): View
    {
        return view('studio.offers.index', [
            'offers' => Offer::orderBy('placement')->orderBy('sort_order')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('studio.offers.form', [
            'offer' => new Offer(['placement' => 'cart_free_gift', 'active' => true]),
            'products' => $this->products(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Offer::create($this->validated($request));

        return redirect()->route('offers.index')->with('success', 'Offer created.');
    }

    public function edit(Offer $offer): View
    {
        return view('studio.offers.form', ['offer' => $offer, 'products' => $this->products()]);
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        $offer->update($this->validated($request));

        return redirect()->route('offers.index')->with('success', 'Offer updated.');
    }

    public function toggleStatus(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $offer->update(['active' => ! $offer->active]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'active' => (bool) $offer->active])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $offer->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Offer deleted.'])
            : back()->with('success', 'Offer deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'placement' => ['required', Rule::in(array_keys(Offer::PLACEMENTS))],
            'threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'reward_text' => ['nullable', 'string', 'max:150'],
            'reward_product_id' => ['nullable', 'exists:products,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['active'] = $request->boolean('active');
        $data['threshold_amount'] = $data['threshold_amount'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function products()
    {
        return Product::where('status', 'active')->orderBy('name')->limit(300)->get(['id', 'name', 'sku']);
    }
}
