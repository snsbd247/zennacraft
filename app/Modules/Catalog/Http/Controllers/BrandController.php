<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Media\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Studio manager for Brands (Brand sidebar group). A brand has a name, logo
 * image, a numeric position (higher shows first) and an active/inactive status.
 * Search, status toggle and delete are AJAX per the project-wide directive.
 */
class BrandController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(Request $request): View
    {
        $brands = Brand::query()->with('image')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderByDesc('position')->orderBy('name')
            ->paginate(20)->withQueryString();

        $data = ['brands' => $brands, 'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null];

        return $request->boolean('partial')
            ? view('studio.brands._rows', $data)
            : view('studio.brands.index', $data);
    }

    public function create(): View
    {
        return view('studio.brands.form', [
            'brand' => new Brand(['position' => 0, 'status' => 'active']),
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($id = $this->upload($request, $data['name'])) {
            $data['image_id'] = $id;
        }
        Brand::create($data);

        return redirect()->route('brands.index')->with('success', 'Brand added.');
    }

    public function edit(Brand $brand): View
    {
        return view('studio.brands.form', [
            'brand' => $brand->load('image'),
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
        ]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request, false);
        if ($data['name'] !== $brand->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $brand->id);
        }
        if ($id = $this->upload($request, $data['name'])) {
            $data['image_id'] = $id;
        }
        $brand->update($data);

        return redirect()->route('brands.index')->with('success', 'Brand updated.');
    }

    public function toggleStatus(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        $brand->update(['status' => $brand->isActive() ? 'inactive' : 'active']);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'status' => $brand->status])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        $brand->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Brand deleted.'])
            : back()->with('success', 'Brand deleted.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'image' => [$creating ? 'nullable' : 'nullable', 'image', 'max:6144'],
        ]);
        $data['position'] = $data['position'] ?? 0;
        unset($data['image']);

        return $data;
    }

    private function upload(Request $request, string $alt): ?int
    {
        return $request->hasFile('image')
            ? $this->mediaService->upload($request->file('image'), $alt, null, 'brand')->id
            : null;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $i = 2;
        while (Brand::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
