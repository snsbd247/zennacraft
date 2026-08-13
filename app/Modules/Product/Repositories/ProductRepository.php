<?php

namespace App\Modules\Product\Repositories;

use App\Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product|int $product, array $data): bool
    {
        $product = $product instanceof Product ? $product : $this->find($product);

        return $product ? $product->update($data) : false;
    }

    public function delete(Product|int $product): bool
    {
        $product = $product instanceof Product ? $product : $this->find($product);

        return $product ? (bool) $product->delete() : false;
    }

    public function find(int $id): ?Product
    {
        return Product::with(['category', 'thumbnail'])->find($id);
    }

    /**
     * $filters: q (name/SKU search), category_id, stock (in|low|out),
     * published (active|inactive). "effective stock" — what the list's
     * stock pill and filter both use — is the sum of a product's
     * variant stock when it has variants, else the product's own stock
     * column (mirrors how the drawer's colour×size matrix replaces the
     * base stock once real variants exist). Computed as one correlated
     * subquery so filtering and pagination stay correct together,
     * instead of filtering in PHP after the page is already sliced.
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = Product::with(['category', 'thumbnail', 'variants', 'colors'])
            ->selectRaw('products.*, '.$this->effectiveStockExpr().' as effective_stock')
            ->latest('products.created_at');

        if (filled($filters['q'] ?? null)) {
            $term = $filters['q'];
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
            });
        }

        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', $filters['category_id']);
        }

        if (filled($filters['published'] ?? null)) {
            $query->where('status', $filters['published']);
        }

        $this->applyStockFilter($query, $filters['stock'] ?? null);

        return $query->paginate($perPage);
    }

    public function countByStockStatus(string $status): int
    {
        $query = Product::query();
        $this->applyStockFilter($query, $status);

        return $query->count();
    }

    /**
     * Correlated subquery, not a GROUP BY aggregate — SQLite (used in
     * dev/tests here) rejects a HAVING clause on a query with no real
     * aggregation, so this same expression is repeated in whereRaw()
     * calls rather than filtering on a SELECT alias.
     */
    protected function effectiveStockExpr(): string
    {
        return '(SELECT COALESCE(SUM(pv.stock), products.stock) FROM product_variants pv WHERE pv.product_id = products.id)';
    }

    protected function applyStockFilter($query, ?string $status): void
    {
        $stockExpr = $this->effectiveStockExpr();
        $lowThreshold = Product::LOW_STOCK_THRESHOLD;

        match ($status) {
            'out' => $query->whereRaw("{$stockExpr} <= 0"),
            'low' => $query->whereRaw("{$stockExpr} > 0")->whereRaw("{$stockExpr} <= {$lowThreshold}"),
            'in' => $query->whereRaw("{$stockExpr} > {$lowThreshold}"),
            'low_or_out' => $query->whereRaw("{$stockExpr} <= {$lowThreshold}"),
            default => null,
        };
    }
}
