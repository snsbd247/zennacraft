<?php

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category|int $category, array $data): bool
    {
        $category = $category instanceof Category ? $category : $this->find($category);

        return $category ? $category->update($data) : false;
    }

    public function delete(Category|int $category): bool
    {
        $category = $category instanceof Category ? $category : $this->find($category);

        return $category ? (bool) $category->delete() : false;
    }

    public function find(int $id): ?Category
    {
        return Category::with(['parent', 'image'])->find($id);
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Category::with(['parent', 'image'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function tree(): Collection
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        return $this->buildTree($categories);
    }

    protected function buildTree(Collection $categories, ?int $parentId = null): Collection
    {
        return $categories
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Category $category) use ($categories) {
                $category->setRelation('children', $this->buildTree($categories, $category->id));

                return $category;
            });
    }
}
