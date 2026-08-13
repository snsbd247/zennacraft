<?php

namespace App\Modules\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categories = Category::query()
            ->where('status', 'active')
            ->with(['image', 'children' => fn ($query) => $query->where('status', 'active')])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return $this->paginated($categories, CategoryResource::class);
    }

    public function show(string $category): JsonResponse
    {
        $category = Category::query()
            ->where('status', 'active')
            ->where(function ($query) use ($category) {
                $query->where('slug', $category);

                if (is_numeric($category)) {
                    $query->orWhere('id', (int) $category);
                }
            })
            ->firstOrFail();

        $category->load([
            'image',
            'children' => fn ($query) => $query->where('status', 'active'),
        ]);

        return $this->success([
            'category' => new CategoryResource($category),
        ]);
    }
}
