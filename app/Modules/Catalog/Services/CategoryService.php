<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\CategoryRepository;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(
        private CategoryRepository $repository,
        private CacheService $cacheService,
    ) {}

    public function create(array $data): Category
    {
        $this->validateParentExists($data['parent_id'] ?? null);
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['name']);

        $category = $this->repository->create($data);
        $this->cacheService->invalidateStorefrontCategories();

        return $category;
    }

    public function update(Category $category, array $data): bool
    {
        $parent = $this->validateParentExists($data['parent_id'] ?? null);
        $this->validateParent($category, $parent);
        $data['slug'] = $this->prepareSlug($data['slug'] ?? null, $data['name'], $category->id);

        $updated = $this->repository->update($category, $data);
        $this->cacheService->invalidateStorefrontCategories();

        return $updated;
    }

    public function delete(Category $category): bool
    {
        $deleted = $this->repository->delete($category);
        $this->cacheService->invalidateStorefrontCategories();

        return $deleted;
    }

    public function find(int $id): ?Category
    {
        return $this->repository->find($id);
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function tree(): Collection
    {
        return $this->repository->tree();
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
        return Category::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function validateParentExists(?int $parentId): ?Category
    {
        if (! $parentId) {
            return null;
        }

        $parent = Category::find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category is invalid.',
            ]);
        }

        return $parent;
    }

    protected function validateParent(Category $category, ?Category $parent): void
    {
        if (! $parent) {
            return;
        }

        if ($category->is($parent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $current = $parent;

        while ($current) {
            if ($current->id === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot use one of its descendants as a parent.',
                ]);
            }

            $current = $current->parent_id ? Category::find($current->parent_id) : null;
        }
    }
}
