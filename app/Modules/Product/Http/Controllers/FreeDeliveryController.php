<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Media\Services\MediaService;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Studio "Free Delivery Products" (Campaign/Offer): opt individual products
 * into free shipping. Any order that contains at least one opted-in product
 * ships free — enforced server-side in CheckoutService::deliveryFee(), so the
 * list here is the single source of truth ("on here = free everywhere").
 */
class FreeDeliveryController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $products = Product::query()
            ->where('free_delivery', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('sku', 'like', '%'.$term.'%')))
            ->with('thumbnail')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('studio.free-delivery.index', [
            'products' => $products,
            'term' => $term,
            'perPage' => $perPage,
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);
        $product = Product::with('thumbnail')->findOrFail($data['product_id']);
        $product->update(['free_delivery' => true]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Added to Free Delivery.',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'thumb' => $product->thumbnail ? $this->mediaService->url($product->thumbnail) : null,
                    'remove_url' => route('free-delivery.destroy', $product),
                ],
            ]);
        }

        return back()->with('success', 'Product added to Free Delivery.');
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $product->update(['free_delivery' => false]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Removed from Free Delivery.'])
            : back()->with('success', 'Removed from Free Delivery.');
    }

    /** AJAX product picker — search products by name or SKU; flags ones already free. */
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
                'thumb' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
                'already' => (bool) $p->free_delivery,
            ])->values(),
        ]);
    }
}
