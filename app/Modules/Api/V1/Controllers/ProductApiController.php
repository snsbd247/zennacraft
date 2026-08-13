<?php

namespace App\Modules\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Modules\Product\Models\Product;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $products = Product::query()
            ->where('status', 'active')
            ->with(['category', 'thumbnail'])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['category'] ?? null, function ($query, string $category) {
                $query->whereHas('category', function ($query) use ($category) {
                    $query->where('slug', $category);

                    if (is_numeric($category)) {
                        $query->orWhere('id', (int) $category);
                    }
                });
            })
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 20));

        return $this->paginated($products, ProductResource::class);
    }

    public function show(string $product): JsonResponse
    {
        $product = Product::query()
            ->where('status', 'active')
            ->where(function ($query) use ($product) {
                $query->where('slug', $product);

                if (is_numeric($product)) {
                    $query->orWhere('id', (int) $product);
                }
            })
            ->firstOrFail();

        $product->load([
            'category',
            'thumbnail',
            'galleryMedia',
            'variants' => fn ($query) => $query->where('status', 'active')->where('show_on_storefront', true)->with('image'),
        ]);

        return $this->success([
            'product' => new ProductResource($product),
        ]);
    }
}
