<?php

namespace App\Modules\Product\Services;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private ProductRepository $repository,
        private InventoryService $inventoryService,
        private CacheService $cacheService,
    ) {}

    public function create(array $data): Product
    {
        $this->validateSkuUnique($data['sku']);
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['name']);
        $data['stock'] = $data['stock'] ?? 0;
        $this->validateStock((int) $data['stock']);

        return DB::transaction(function () use ($data) {
            $product = $this->repository->create($data);

            if ((int) $product->stock > 0) {
                $this->inventoryService->logInitialStock($product, (int) $product->stock);
            }

            $this->cacheService->invalidateStorefrontProducts();

            return $product;
        });
    }

    public function update(Product $product, array $data): bool
    {
        $this->validateSkuUnique($data['sku'], $product->id);
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['name'], $product->id);
        $data['stock'] = $data['stock'] ?? 0;
        $newStock = (int) $data['stock'];
        $previousStock = (int) $product->stock;
        $stockChanged = $newStock !== $previousStock;
        $this->validateStock($newStock);

        return DB::transaction(function () use ($product, $data, $stockChanged, $previousStock) {
            $updated = $this->repository->update($product, $data);
            $product->refresh();

            if ($stockChanged) {
                $this->inventoryService->adjustStock(
                    $product,
                    (int) $product->stock,
                    'Product stock updated.',
                    $previousStock
                );
            }

            $this->cacheService->invalidateStorefrontProducts();

            return $updated;
        });
    }

    public function delete(Product $product): bool
    {
        $deleted = $this->repository->delete($product);
        $this->cacheService->invalidateStorefrontProducts();

        return $deleted;
    }

    public function find(int $id): ?Product
    {
        return $this->repository->find($id);
    }

    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $filters);
    }

    public function countByStockStatus(string $status): int
    {
        return $this->repository->countByStockStatus($status);
    }

    protected function prepareSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Product::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function validateSkuUnique(string $sku, ?int $ignoreId = null): void
    {
        $exists = Product::where('sku', $sku)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => 'The SKU has already been taken.',
            ]);
        }
    }

    protected function validateStock(int $stock): void
    {
        if ($stock < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stock cannot be negative.',
            ]);
        }
    }
}
