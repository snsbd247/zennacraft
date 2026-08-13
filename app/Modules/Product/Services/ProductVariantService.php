<?php

namespace App\Modules\Product\Services;

use App\Modules\Inventory\Services\VariantInventoryService;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductColor;
use App\Modules\Product\Models\ProductSize;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Product\Repositories\ProductVariantRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    public function __construct(
        private ProductVariantRepository $repository,
        private VariantInventoryService $inventoryService,
        private CacheService $cacheService,
    ) {}

    public function create(Product $product, array $data): ProductVariant
    {
        $data['product_id'] = $product->id;
        $data = $this->validatedData($data);
        $this->validateSkuUnique($data['sku']);

        return DB::transaction(function () use ($data) {
            $variant = $this->repository->create($data);

            if ((int) $variant->stock > 0) {
                $this->inventoryService->logInitialStock($variant, (int) $variant->stock);
            }

            $this->cacheService->invalidateStorefrontProducts();

            return $variant;
        });
    }

    public function update(ProductVariant $variant, array $data): bool
    {
        $data['product_id'] = $variant->product_id;
        $data = $this->validatedData($data);
        $this->validateSkuUnique($data['sku'], $variant->id);

        $previousStock = (int) $variant->stock;
        $newStock = (int) $data['stock'];
        $stockChanged = $newStock !== $previousStock;

        return DB::transaction(function () use ($variant, $data, $stockChanged, $previousStock) {
            $updated = $this->repository->update($variant, $data);
            $variant->refresh();

            if ($stockChanged) {
                $this->inventoryService->adjustStock(
                    $variant,
                    (int) $variant->stock,
                    'Variant stock updated.',
                    $previousStock
                );
            }

            $this->cacheService->invalidateStorefrontProducts();

            return $updated;
        });
    }

    public function delete(ProductVariant $variant): bool
    {
        $deleted = $this->repository->delete($variant);
        $this->cacheService->invalidateStorefrontProducts();

        return $deleted;
    }

    public function find(int $id): ?ProductVariant
    {
        return $this->repository->find($id);
    }

    public function paginateByProduct(Product $product, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateByProduct($product, $perPage);
    }

    /**
     * Phase 4: structured colours/sizes replace the freeform
     * option_values-only matrix. Colours and sizes are now real
     * per-product rows (ProductColor/ProductSize); a stock cell is
     * still a ProductVariant, but matched to an existing one by its
     * product_color_id/product_size_id FK rather than by parsing JSON.
     * option_values keeps getting written too (Color/ColorHex/Size),
     * purely for backward compatibility with the one existing reader
     * (the storefront's package-style offer selector) — it is never
     * read back by this method.
     *
     * Deliberately scoped to *matrix-managed* variants — ones with both
     * FKs set. Older "package" variants (Single Piece / Gift Pack, no
     * colour/size at all) are never touched here.
     *
     * $colors = [['name' => string, 'hex' => ?string], ...]
     * $sizes = [['name' => string, 'dimension' => ?string, 'price' => float], ...]
     * $cells = [['color' => string, 'size' => string, 'stock' => int], ...]
     */
    public function syncColorsAndSizes(Product $product, array $colors, array $sizes, array $cells): array
    {
        return DB::transaction(function () use ($product, $colors, $sizes, $cells) {
            $colorMap = $this->syncColors($product, $colors);
            $sizeMap = $this->syncSizes($product, $sizes);
            $variants = $this->syncCells($product, $colorMap, $sizeMap, $cells);

            $this->cacheService->invalidateStorefrontProducts();

            return $variants;
        });
    }

    /**
     * Live preview for the drawer's AJAX "add a colour/size" round trip
     * — computes what syncColorsAndSizes() *would* persist (including a
     * real SKU-generation preview per cell) without writing anything.
     * $product is null for a brand-new product that doesn't exist yet;
     * $productSku is whatever SKU the admin has already typed in Basic
     * Details, used as the SKU prefix for the preview (falls back to
     * "NEW" if blank).
     */
    public function previewColorsAndSizes(?Product $product, string $productSku, array $colors, array $sizes, array $cells): array
    {
        $normalizedColors = collect($colors)
            ->map(fn ($color) => ['name' => trim((string) ($color['name'] ?? '')), 'hex' => $color['hex'] ?? null])
            ->filter(fn ($color) => $color['name'] !== '')
            ->unique('name')
            ->values();

        $normalizedSizes = collect($sizes)
            ->map(fn ($size) => [
                'name' => trim((string) ($size['name'] ?? '')),
                'dimension' => filled($size['dimension'] ?? null) ? trim((string) $size['dimension']) : null,
                'price' => round((float) ($size['price'] ?? 0), 2),
            ])
            ->filter(fn ($size) => $size['name'] !== '')
            ->unique('name')
            ->values();

        $existingVariants = $product
            ? $product->variants()->whereNotNull('product_color_id')->whereNotNull('product_size_id')->with(['color', 'size'])->get()
            : collect();

        $usedSkus = [];
        $rows = [];

        foreach ($normalizedColors as $color) {
            foreach ($normalizedSizes as $size) {
                $cell = collect($cells)->first(fn ($c) => trim((string) ($c['color'] ?? '')) === $color['name'] && trim((string) ($c['size'] ?? '')) === $size['name']);
                $stock = max(0, (int) ($cell['stock'] ?? 0));

                $match = $existingVariants->first(fn ($variant) => $variant->color?->name === $color['name'] && $variant->size?->name === $size['name']);
                $sku = $match?->sku ?? $this->generateVariantSku($productSku ?: 'NEW', $color['name'], $size['name'], $usedSkus);
                $usedSkus[] = $sku;

                $rows[] = [
                    'color' => $color['name'],
                    'color_hex' => $color['hex'],
                    'size' => $size['name'],
                    'dimension' => $size['dimension'],
                    'price' => $size['price'],
                    'stock' => $stock,
                    'sku' => $sku,
                    'is_new' => ! $match,
                ];
            }
        }

        return [
            'colors' => $normalizedColors->all(),
            'sizes' => $normalizedSizes->all(),
            'rows' => $rows,
        ];
    }

    protected function syncColors(Product $product, array $colors): array
    {
        $existing = $product->colors()->get()->keyBy('name');
        $map = [];
        $seenIds = [];

        foreach (array_values($colors) as $index => $data) {
            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $hex = $data['hex'] ?? null;

            if ($existing->has($name)) {
                $color = $existing->get($name);
                $color->update(['hex' => $hex, 'sort_order' => $index]);
            } else {
                $color = $product->colors()->create(['name' => $name, 'hex' => $hex, 'sort_order' => $index]);
                $existing->put($name, $color);
            }

            $map[$name] = $color;
            $seenIds[] = $color->id;
        }

        // A colour the admin removed takes every variant cell using it
        // with it — there's no meaningful "orphaned" cell once its
        // colour no longer exists.
        $existing->reject(fn (ProductColor $color) => in_array($color->id, $seenIds, true))
            ->each(function (ProductColor $color) {
                $color->variants()->get()->each(fn (ProductVariant $variant) => $this->delete($variant));
                $color->delete();
            });

        return $map;
    }

    protected function syncSizes(Product $product, array $sizes): array
    {
        $existing = $product->sizes()->get()->keyBy('name');
        $map = [];
        $seenIds = [];

        foreach (array_values($sizes) as $index => $data) {
            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $attributes = [
                'dimension' => filled($data['dimension'] ?? null) ? trim((string) $data['dimension']) : null,
                'price' => round((float) ($data['price'] ?? 0), 2),
                'sort_order' => $index,
            ];

            if ($existing->has($name)) {
                $size = $existing->get($name);
                $size->update($attributes);
            } else {
                $size = $product->sizes()->create($attributes + ['name' => $name]);
                $existing->put($name, $size);
            }

            $map[$name] = $size;
            $seenIds[] = $size->id;
        }

        $existing->reject(fn (ProductSize $size) => in_array($size->id, $seenIds, true))
            ->each(function (ProductSize $size) {
                $size->variants()->get()->each(fn (ProductVariant $variant) => $this->delete($variant));
                $size->delete();
            });

        return $map;
    }

    /**
     * @param  array<string, ProductColor>  $colorMap
     * @param  array<string, ProductSize>  $sizeMap
     */
    protected function syncCells(Product $product, array $colorMap, array $sizeMap, array $cells): array
    {
        $existing = $product->variants()->whereNotNull('product_color_id')->whereNotNull('product_size_id')->get();
        $seenIds = [];
        $result = [];

        foreach ($cells as $cell) {
            $color = $colorMap[trim((string) ($cell['color'] ?? ''))] ?? null;
            $size = $sizeMap[trim((string) ($cell['size'] ?? ''))] ?? null;

            // A cell whose colour or size was itself removed in this
            // same submit was already deleted by syncColors()/
            // syncSizes() above — nothing left to do for it here.
            if (! $color || ! $size) {
                continue;
            }

            $match = $existing->first(fn (ProductVariant $variant) => (int) $variant->product_color_id === $color->id && (int) $variant->product_size_id === $size->id);
            $optionValues = ['Color' => $color->name, 'ColorHex' => $color->hex, 'Size' => $size->name];

            if ($match) {
                $this->update($match, [
                    'name' => $color->name.' / '.$size->name,
                    'sku' => $match->sku,
                    'price' => $size->price,
                    'stock' => max(0, (int) ($cell['stock'] ?? 0)),
                    'option_values' => $optionValues,
                    'product_color_id' => $color->id,
                    'product_size_id' => $size->id,
                ]);
                $seenIds[] = $match->id;
                $result[] = $match->fresh();

                continue;
            }

            $variant = $this->create($product, [
                'name' => $color->name.' / '.$size->name,
                'sku' => $this->generateVariantSku($product->sku, $color->name, $size->name),
                'price' => $size->price,
                'stock' => max(0, (int) ($cell['stock'] ?? 0)),
                'option_values' => $optionValues,
                'product_color_id' => $color->id,
                'product_size_id' => $size->id,
            ]);
            $seenIds[] = $variant->id;
            $result[] = $variant;
        }

        $existing
            ->reject(fn (ProductVariant $variant) => in_array($variant->id, $seenIds, true))
            ->each(fn (ProductVariant $variant) => $this->delete($variant));

        return $result;
    }

    protected function generateVariantSku(string $productSku, string $color, string $size, array $avoid = []): string
    {
        $base = strtoupper(trim($productSku.'-'.Str::slug($color).'-'.Str::slug($size), '-'));
        $sku = $base;
        $counter = 2;

        while (ProductVariant::where('sku', $sku)->exists() || in_array($sku, $avoid, true)) {
            $sku = $base.'-'.$counter;
            $counter++;
        }

        return $sku;
    }

    protected function validatedData(array $data): array
    {
        $data['image_id'] = $data['image_id'] ?? null;
        $data['compare_price'] = $data['compare_price'] ?? null;
        $data['cost_price'] = $data['cost_price'] ?? null;
        $data['stock'] = $data['stock'] ?? 0;
        $data['weight'] = $data['weight'] ?? null;
        $data['dimensions'] = $data['dimensions'] ?? null;
        $data['sort_order'] = max(0, (int) ($data['sort_order'] ?? 0));
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);
        $data['show_on_storefront'] = (bool) ($data['show_on_storefront'] ?? true);
        $data['status'] = $data['status'] ?? 'active';
        $data['option_values'] = $this->prepareOptionValues($data['option_values'] ?? null);

        $this->validateStock((int) $data['stock']);
        $this->validateComparePrice((float) $data['price'], $data['compare_price']);
        $data['cost_price'] = $this->validatedCostPrice($data['cost_price']);

        return $data;
    }

    protected function validateSkuUnique(string $sku, ?int $ignoreId = null): void
    {
        $exists = ProductVariant::where('sku', $sku)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => 'The variant SKU has already been taken.',
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

    protected function validateComparePrice(float $price, mixed $comparePrice): void
    {
        if ($comparePrice === null || $comparePrice === '') {
            return;
        }

        if ((float) $comparePrice < $price) {
            throw ValidationException::withMessages([
                'compare_price' => 'Compare price must be greater than or equal to price.',
            ]);
        }
    }

    protected function validatedCostPrice(mixed $costPrice): mixed
    {
        if ($costPrice === null || $costPrice === '') {
            return null;
        }

        if (! is_numeric($costPrice) || (float) $costPrice < 0) {
            throw ValidationException::withMessages([
                'cost_price' => 'Cost price must be a number greater than or equal to zero.',
            ]);
        }

        return $costPrice;
    }

    protected function prepareOptionValues(mixed $optionValues): ?array
    {
        if ($optionValues === null || $optionValues === '') {
            return null;
        }

        if (is_array($optionValues)) {
            return $optionValues;
        }

        if (! is_string($optionValues)) {
            throw ValidationException::withMessages([
                'option_values' => 'Option values must be valid JSON.',
            ]);
        }

        $decoded = json_decode($optionValues, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'option_values' => 'Option values must be valid JSON.',
            ]);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
