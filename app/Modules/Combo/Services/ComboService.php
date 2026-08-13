<?php

namespace App\Modules\Combo\Services;

use App\Modules\Combo\Models\Combo;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The one place combo-product linking lives — reads/writes the
 * combo_items pivot via syncProducts()/syncProductCombos() rather than
 * touching it directly elsewhere. No Studio UI is wired to this module
 * currently (kept as a backend-only building block); see
 * _design/STUDIO-PRODUCTS-COMBO-REDESIGN-REPORT.md for the UI that
 * previously called it.
 */
class ComboService
{
    public function __construct(private CacheService $cacheService) {}

    public function create(array $data, array $items): Combo
    {
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['name']);

        return DB::transaction(function () use ($data, $items) {
            $combo = Combo::create($data);
            $this->syncProducts($combo, $items);
            $this->cacheService->invalidateStorefrontProducts();

            return $combo->fresh(['products']);
        });
    }

    public function update(Combo $combo, array $data, array $items): Combo
    {
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['name'], $combo->id);

        return DB::transaction(function () use ($combo, $data, $items) {
            $combo->update($data);
            $this->syncProducts($combo, $items);
            $this->cacheService->invalidateStorefrontProducts();

            return $combo->fresh(['products']);
        });
    }

    public function delete(Combo $combo): bool
    {
        $deleted = (bool) $combo->delete();
        $this->cacheService->invalidateStorefrontProducts();

        return $deleted;
    }

    /**
     * $items = [['product_id' => int, 'quantity' => int], ...]. Replaces
     * the combo's entire product list — used by the drawer's full save.
     */
    public function syncProducts(Combo $combo, array $items): void
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to the combo.',
            ]);
        }

        $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
        $existingCount = Product::whereIn('id', $productIds)->count();

        if ($existingCount !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected products no longer exist.',
            ]);
        }

        $syncData = [];

        foreach (array_values($items) as $index => $item) {
            $productId = (int) $item['product_id'];
            $syncData[$productId] = [
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'sort_order' => $index,
            ];
        }

        $combo->products()->sync($syncData);
    }

    /**
     * The product drawer's "Combo bundles" checklist is plain local UI
     * state until the drawer's single Save button is clicked (same as
     * every other field in that drawer, matching the mockup) — so this
     * replaces the product's whole combo membership in one call rather
     * than toggling one at a time. quantity/sort_order fall back to
     * their DB defaults (1/0) since this list only ever carries "which
     * combos", not per-combo quantities — that's set from the combo
     * builder's own product list instead.
     */
    public function syncProductCombos(Product $product, array $comboIds): void
    {
        $product->combos()->sync(array_values(array_unique(array_map('intval', $comboIds))));
        $this->cacheService->invalidateStorefrontProducts();
    }

    protected function prepareSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::slug($name).'-'.Str::random(4);
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
        return Combo::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
