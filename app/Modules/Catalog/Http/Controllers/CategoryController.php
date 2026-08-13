<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Services\MediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One controller for the three category levels (Main / Sub / Sub-Sub) — all the
 * same self-referencing `categories` table, distinguished by depth. The level
 * arrives as a route default ('main' | 'sub' | 'subsub').
 */
class CategoryController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(Request $request, string $level): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = $this->baseQuery($level)
            ->with(['image', 'parent'])
            ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('studio.categories.index', array_merge($this->meta($level), [
            'level' => $level, 'categories' => $categories, 'search' => $search,
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]));
    }

    public function create(string $level): View
    {
        return view('studio.categories.form', array_merge($this->meta($level), [
            'level' => $level, 'category' => new Category(['status' => 'active', 'sort_order' => 0]),
            'parents' => $this->parentOptions($level),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]));
    }

    public function store(Request $request, string $level): RedirectResponse
    {
        $data = $this->validated($request, $level);
        $data['parent_id'] = $this->resolveParent($request, $level);
        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($request->hasFile('image')) {
            $data['image_id'] = $this->mediaService->upload($request->file('image'), $data['name'], null, 'category')->id;
        }
        Category::create($data);

        return redirect()->route('categories.'.$level.'.index')->with('success', $this->meta($level)['singular'].' created.');
    }

    public function edit(string $level, Category $category): View
    {
        return view('studio.categories.form', array_merge($this->meta($level), [
            'level' => $level, 'category' => $category->load('image'),
            'parents' => $this->parentOptions($level),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]));
    }

    public function update(Request $request, string $level, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $level);
        $data['parent_id'] = $this->resolveParent($request, $level);
        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }
        if ($request->hasFile('image')) {
            $data['image_id'] = $this->mediaService->upload($request->file('image'), $data['name'], null, 'category')->id;
        }
        $category->update($data);

        return redirect()->route('categories.'.$level.'.index')->with('success', $this->meta($level)['singular'].' updated.');
    }

    public function toggleStatus(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category->update(['status' => $category->status === 'active' ? 'inactive' : 'active']);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Status updated.', 'status' => $category->status]);
        }

        return back()->with('success', 'Status updated.');
    }

    public function applyDiscount(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['discount_percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $category->update(['discount_percent' => $data['discount_percent']]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Discount applied.', 'discount_percent' => (float) $category->discount_percent]);
        }

        return back()->with('success', 'Discount applied.');
    }

    public function destroy(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        if ($category->children()->exists()) {
            $message = 'Move or delete its sub-categories first.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['category' => $message]);
        }

        $category->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Category deleted.'])
            : back()->with('success', 'Category deleted.');
    }

    // ---- helpers -------------------------------------------------------

    private function meta(string $level): array
    {
        return match ($level) {
            'sub' => ['title' => 'Sub Categories', 'singular' => 'Sub category', 'hasParent' => true, 'parentLabel' => 'Category', 'isSub' => true],
            'subsub' => ['title' => 'Sub Sub Categories', 'singular' => 'Sub sub category', 'hasParent' => true, 'parentLabel' => 'Sub Category', 'isSub' => false],
            default => ['title' => 'Categories', 'singular' => 'Category', 'hasParent' => false, 'parentLabel' => null, 'isSub' => false],
        };
    }

    private function baseQuery(string $level): Builder
    {
        return match ($level) {
            'sub' => Category::query()->whereNotNull('parent_id')->whereHas('parent', fn (Builder $q) => $q->whereNull('parent_id')),
            'subsub' => Category::query()->whereHas('parent', fn (Builder $q) => $q->whereNotNull('parent_id')),
            default => Category::query()->whereNull('parent_id'),
        };
    }

    private function parentOptions(string $level)
    {
        return match ($level) {
            'sub' => Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'subsub' => Category::whereNotNull('parent_id')->whereHas('parent', fn (Builder $q) => $q->whereNull('parent_id'))->orderBy('name')->get(['id', 'name']),
            default => collect(),
        };
    }

    private function resolveParent(Request $request, string $level): ?int
    {
        return $level === 'main' ? null : (int) $request->input('parent_id');
    }

    private function validated(Request $request, string $level): array
    {
        // Only the fields that actually belong on a category: name, image,
        // parent and SEO. Status is managed from the list (toggle), discount
        // from the "apply discount" action, position stays at its default —
        // none of them are reset when the category is edited.
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'max:4096'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'meta_content' => ['nullable', 'string', 'max:1000'],
        ];
        if ($level !== 'main') {
            $rules['parent_id'] = ['required', 'integer', 'exists:categories,id'];
        }

        $data = $request->validate($rules);
        unset($data['image']); // handled separately

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
