<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\VariantInventoryService;
use App\Modules\Media\Services\MediaService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Studio "Stock" — see every product's current stock and adjust it inline.
 * Writes straight to the same stock column the product page and storefront
 * read (products.stock for simple products, product_variants.stock for variant
 * products), and records the change through the existing inventory-log services
 * so nothing about how stock is stored or counted diverges from the rest of the
 * app. Raising/lowering here therefore raises/lowers it on the product page too.
 */
class StockController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private InventoryService $inventory,
        private VariantInventoryService $variantInventory,
        private MediaService $media,
    ) {}

    public function index(Request $request): View
    {
        $filters = array_filter([
            'q' => $request->query('q'),
            'stock' => $request->query('stock'),
        ], fn ($v) => filled($v));

        return view('studio.stock.index', [
            'products' => $this->productService->paginate((int) $request->query('per_page', 30), $filters),
            'filters' => $filters,
            'lowStockCount' => $this->productService->countByStockStatus('low'),
            'outStockCount' => $this->productService->countByStockStatus('out'),
            'lowThreshold' => Product::LOW_STOCK_THRESHOLD,
            'mediaUrl' => fn ($media) => $media ? $this->media->url($media) : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $product = Product::with('variants')->findOrFail($data['product_id']);
        $newStock = (int) $data['stock'];

        if (! empty($data['variant_id'])) {
            $variant = $product->variants->firstWhere('id', (int) $data['variant_id']);
            abort_unless($variant !== null, 404);

            $previous = (int) $variant->stock;
            $variant->update(['stock' => $newStock]);
            $this->variantInventory->adjustStock($variant, $newStock, 'Stock page adjustment', $previous);
        } else {
            if ($product->variants->isNotEmpty()) {
                return response()->json(['message' => 'This product uses variants — adjust each variant’s stock instead.'], 422);
            }

            $previous = (int) $product->stock;
            $product->update(['stock' => $newStock]);
            $this->inventory->adjustStock($product, $newStock, 'Stock page adjustment', $previous);
        }

        $hasVariants = $product->variants()->count() > 0;
        $total = $hasVariants ? (int) $product->variants()->sum('stock') : (int) $product->fresh()->stock;

        return response()->json([
            'message' => 'Stock updated.',
            'stock' => $newStock,
            'total' => $total,
            'status' => $this->stockStatus($total),
        ]);
    }

    private function stockStatus(int $stock): string
    {
        if ($stock <= 0) {
            return 'out';
        }

        return $stock <= Product::LOW_STOCK_THRESHOLD ? 'low' : 'in';
    }
}
