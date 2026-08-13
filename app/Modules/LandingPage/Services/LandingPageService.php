<?php

namespace App\Modules\LandingPage\Services;

use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\LandingPage\Repositories\LandingPageRepository;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class LandingPageService
{
    protected const RESERVED_SLUGS = [
        'studio',
        'admin',
        'health',
        'products',
        'categories',
        'product',
        'category',
        'checkout',
        'cart',
        'orders',
        'login',
        'register',
        'api',
        'storage',
    ];

    public function __construct(
        private LandingPageRepository $repository,
        private CacheService $cacheService,
    ) {}

    public function create(array $data): LandingPage
    {
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['title']);

        $landingPage = $this->repository->create($data);
        $this->cacheService->invalidateStorefrontLandingPages();
        $this->cacheService->invalidateMarketingCommandCenter();

        return $landingPage;
    }

    /**
     * Auto-generate a one-page (order-form) landing page for a product.
     * Idempotent: returns the existing one if a landing is already linked to
     * this product, so re-saving a product never creates duplicates. The page
     * is fully editable afterwards in Landing Page → Manage.
     */
    public function createForProduct(\App\Modules\Product\Models\Product $product): LandingPage
    {
        $existing = LandingPage::query()->where('product_id', $product->id)->first();
        if ($existing) {
            return $existing;
        }

        $subtitle = trim((string) $product->short_description)
            ?: 'অর্ডার করতে নিচের ফর্মটি পূরণ করুন — সারা বাংলাদেশে ক্যাশ অন ডেলিভারি।';

        return $this->create([
            'product_id' => $product->id,
            'title' => $product->name,
            'template' => 'sales',
            'status' => $product->status === 'active' ? 'active' : 'inactive',
            'hero_title' => $product->name,
            'hero_subtitle' => $subtitle,
            'content' => (string) $product->description,
            'cta_text' => 'অর্ডার করুন',
            'suggested_products' => [$product->id],
            'meta_title' => $product->meta_title ?: $product->name,
            'meta_description' => $product->meta_description ?: $subtitle,
        ]);
    }

    public function update(LandingPage $landingPage, array $data): bool
    {
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['title'], $landingPage->id);

        $updated = $this->repository->update($landingPage, $data);
        $this->cacheService->invalidateStorefrontLandingPages();
        $this->cacheService->invalidateMarketingCommandCenter();

        return $updated;
    }

    public function delete(LandingPage $landingPage): bool
    {
        $deleted = $this->repository->delete($landingPage);
        $this->cacheService->invalidateStorefrontLandingPages();
        $this->cacheService->invalidateMarketingCommandCenter();

        return $deleted;
    }

    public function find(int $id): ?LandingPage
    {
        return $this->repository->find($id);
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    protected function prepareSlug(?string $slug, string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug ?: $title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::slug($title);
        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugUnavailable($slug, $ignoreId)) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return LandingPage::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function slugUnavailable(string $slug, ?int $ignoreId = null): bool
    {
        return in_array($slug, self::RESERVED_SLUGS, true) || $this->slugExists($slug, $ignoreId);
    }
}
