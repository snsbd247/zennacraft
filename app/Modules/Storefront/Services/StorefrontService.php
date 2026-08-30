<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StorefrontService
{
    public function __construct(private CacheService $cacheService) {}

    public function latestProducts(int $limit = 8): Collection
    {
        $productIds = $this->cacheService->remember(
            CacheKeyRegistry::latestProducts($limit),
            fn (): array => Product::query()
                ->where('status', 'active')
                ->latest()
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            null,
            [CacheKeyRegistry::STOREFRONT_PRODUCT_TAG]
        );

        return $this->productsByIds($productIds, $limit);
    }

    public function topSellingProducts(int $limit = 8): Collection
    {
        $productIds = $this->cacheService->remember(
            'storefront.products.top_selling.'.$limit,
            function () use ($limit): array {
                return OrderItem::query()
                    ->selectRaw('product_id, SUM(quantity) as sold_quantity')
                    ->whereNotNull('product_id')
                    ->groupBy('product_id')
                    ->orderByDesc('sold_quantity')
                    ->limit($limit)
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            },
            null,
            [CacheKeyRegistry::STOREFRONT_PRODUCT_TAG]
        );

        if ($productIds === []) {
            return $this->latestProducts($limit);
        }

        $products = $this->productsByIds($productIds, $limit);

        if ($products->count() < $limit) {
            $fallback = $this->latestProducts($limit)
                ->reject(fn (Product $product) => $products->contains('id', $product->id))
                ->take($limit - $products->count());

            return $products->merge($fallback)->values();
        }

        return $products;
    }

    public function paginatedProducts(int $perPage = 12, ?string $search = null): LengthAwarePaginator
    {
        return Product::with([
            'thumbnail',
            'category',
            'variants' => fn ($query) => $query->where('status', 'active')->where('show_on_storefront', true),
        ])
            ->where('status', 'active')
            ->when(filled($search), fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('short_description', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function categoryProducts(Category $category, int $perPage = 12): LengthAwarePaginator
    {
        return Product::with([
            'thumbnail',
            'category',
            'variants' => fn ($query) => $query->where('status', 'active')->where('show_on_storefront', true),
        ])
            ->where('status', 'active')
            ->where('category_id', $category->id)
            ->latest()
            ->paginate($perPage);
    }

    public function activeCategories(): Collection
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::STOREFRONT_ACTIVE_CATEGORIES,
            fn () => Category::with('image')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            null,
            [CacheKeyRegistry::STOREFRONT_CATEGORY_TAG]
        );
    }

    public function productForDisplay(Product $product): Product
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::productDetail($product->id, (string) $product->updated_at?->timestamp),
            fn () => Product::with([
                'thumbnail',
                'galleryMedia',
                'variants' => fn ($query) => $query->where('status', 'active')->where('show_on_storefront', true)->with('image'),
                'category',
                'brand',
                'addons' => fn ($query) => $query->where('products.status', 'active'),
                'addons.thumbnail',
            ])->findOrFail($product->id),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::STOREFRONT_PRODUCT_TAG]
        );
    }

    public function categoryForDisplay(Category $category): Category
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::categoryDetail($category->id, (string) $category->updated_at?->timestamp),
            fn () => Category::with('image')->findOrFail($category->id),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::STOREFRONT_CATEGORY_TAG]
        );
    }

    public function landingPageForDisplay(LandingPage $landingPage): LandingPage
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::landingPageDetail($landingPage->id, (string) $landingPage->updated_at?->timestamp),
            fn () => LandingPage::findOrFail($landingPage->id),
            CacheService::SHORT_TTL,
            [CacheKeyRegistry::STOREFRONT_LANDING_PAGE_TAG]
        );
    }

    protected function productsByIds(array $productIds, int $limit): Collection
    {
        $ids = collect($productIds)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->take($limit)
            ->values();

        if ($ids->isEmpty()) {
            return new Collection();
        }

        return Product::with($this->productCardRelations())
            ->where('status', 'active')
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (Product $product): int|false => $ids->search((int) $product->id))
            ->values();
    }

    protected function productCardRelations(): array
    {
        return [
            'thumbnail',
            'category',
            'variants' => fn ($query) => $query->where('status', 'active')->where('show_on_storefront', true),
        ];
    }
}
