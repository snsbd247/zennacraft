<?php

namespace App\Modules\Product\Repositories;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductVariantRepository
{
    public function create(array $data): ProductVariant
    {
        return ProductVariant::create($data);
    }

    public function update(ProductVariant|int $variant, array $data): bool
    {
        $variant = $variant instanceof ProductVariant ? $variant : $this->find($variant);

        return $variant ? $variant->update($data) : false;
    }

    public function delete(ProductVariant|int $variant): bool
    {
        $variant = $variant instanceof ProductVariant ? $variant : $this->find($variant);

        return $variant ? (bool) $variant->delete() : false;
    }

    public function find(int $id): ?ProductVariant
    {
        return ProductVariant::with(['product', 'image'])->find($id);
    }

    public function paginateByProduct(Product $product, int $perPage = 20): LengthAwarePaginator
    {
        return ProductVariant::with(['image'])
            ->where('product_id', $product->id)
            ->orderBy('name')
            ->paginate($perPage);
    }
}
