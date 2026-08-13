<?php

namespace App\Modules\Combo\Http\Controllers;

use App\Modules\Combo\Models\Combo;
use App\Modules\Combo\Services\ComboService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Studio "Combo Products" (Campaign/Offer): bundle several products together
 * and sell them at a combo price. The list mirrors the reference — thumbnail,
 * name + code, item count, and a Price / Sale Price / Discount trio. All
 * combo-product linking goes through ComboService so the pivot has one owner.
 */
class ComboController extends Controller
{
    public function __construct(private ComboService $comboService, private MediaService $mediaService) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $combos = Combo::with(['image', 'products.thumbnail'])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('studio.combos.index', [
            'combos' => $combos,
            'term' => $term,
            'perPage' => $perPage,
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Combo(['status' => 'active']));
    }

    public function edit(Combo $combo): View
    {
        return $this->form($combo->load(['image', 'products.thumbnail']));
    }

    private function form(Combo $combo): View
    {
        return view('studio.combos.form', [
            'combo' => $combo,
            'items' => $combo->exists
                ? $combo->products->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => (float) $p->price,
                    'quantity' => (int) $p->pivot->quantity,
                    'thumb' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
                ])->values()->all()
                : [],
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        if ($id = $this->uploadImage($request)) {
            $data['image_id'] = $id;
        }
        $this->comboService->create($data, $items);

        return redirect()->route('combos.index')->with('success', 'Combo product created.');
    }

    public function update(Request $request, Combo $combo): RedirectResponse
    {
        [$data, $items] = $this->validated($request);
        if ($id = $this->uploadImage($request)) {
            $data['image_id'] = $id;
        }
        $this->comboService->update($combo, $data, $items);

        return redirect()->route('combos.index')->with('success', 'Combo product updated.');
    }

    public function destroy(Request $request, Combo $combo): JsonResponse|RedirectResponse
    {
        $this->comboService->delete($combo);

        return $request->expectsJson()
            ? response()->json(['message' => 'Combo removed.'])
            : back()->with('success', 'Combo removed.');
    }

    /** AJAX product picker — search products by name or SKU to add to the combo. */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $products = Product::query()
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'))
            ->with('thumbnail')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return response()->json([
            'results' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) $p->price,
                'thumb' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
            ])->values(),
        ]);
    }

    /** @return array{0: array<string,mixed>, 1: array<int,array{product_id:int,quantity:int}>} */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(Combo::STATUSES)],
            'image' => ['nullable', 'image', 'max:6144'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ], [
            'items.required' => 'Add at least one product to the combo.',
            'items.min' => 'Add at least one product to the combo.',
        ]);

        $items = collect($v['items'])
            ->map(fn ($i) => ['product_id' => (int) $i['product_id'], 'quantity' => max(1, (int) ($i['quantity'] ?? 1))])
            ->values()
            ->all();

        $data = [
            'name' => $v['name'],
            'code' => $v['code'] ?? null,
            'description' => $v['description'] ?? null,
            'regular_price' => (float) ($v['regular_price'] ?? 0),
            'price' => (float) $v['price'],
            'status' => $v['status'],
            'feature_on_home' => $request->boolean('feature_on_home'),
        ];

        return [$data, $items];
    }

    private function uploadImage(Request $request): ?int
    {
        return $request->hasFile('image')
            ? $this->mediaService->upload($request->file('image'), (string) $request->input('name', 'Combo'), null, 'combo')->id
            : null;
    }
}
