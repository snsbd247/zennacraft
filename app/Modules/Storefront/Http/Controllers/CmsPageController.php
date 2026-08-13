<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Storefront\Http\Requests\StoreCmsPageRequest;
use App\Modules\Storefront\Http\Requests\UpdateCmsPageRequest;
use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Studio CMS pages (About us, Privacy, Terms…) shown at /pages/{slug}. */
class CmsPageController extends Controller
{
    public function __construct(private CacheService $cacheService) {}

    public function index(): View
    {
        return view('studio.pages.index', ['pages' => CmsPage::orderBy('title')->paginate(20)]);
    }

    public function create(): View
    {
        return view('studio.pages.form', ['cmsPage' => new CmsPage(['active' => true])]);
    }

    public function store(StoreCmsPageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['title']);
        $data['active'] = $request->boolean('active');
        CmsPage::create($data);
        $this->cacheService->invalidateStorefrontContent();

        return redirect()->route('pages.index')->with('success', 'Page created.');
    }

    public function edit(CmsPage $cmsPage): View
    {
        return view('studio.pages.form', ['cmsPage' => $cmsPage]);
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['title'], $cmsPage->id);
        $data['active'] = $request->boolean('active');
        $cmsPage->update($data);
        $this->cacheService->invalidateStorefrontContent();

        return redirect()->route('pages.index')->with('success', 'Page updated.');
    }

    public function toggleStatus(Request $request, CmsPage $cmsPage): JsonResponse|RedirectResponse
    {
        $cmsPage->update(['active' => ! $cmsPage->active]);
        $this->cacheService->invalidateStorefrontContent();

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'active' => (bool) $cmsPage->active])
            : back();
    }

    public function destroy(Request $request, CmsPage $cmsPage): JsonResponse|RedirectResponse
    {
        $cmsPage->delete();
        $this->cacheService->invalidateStorefrontContent();

        return $request->expectsJson() ? response()->json(['message' => 'Page deleted.']) : back();
    }

    private function uniqueSlug(?string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $title) ?: 'page';
        $s = $base;
        $i = 2;
        while (CmsPage::where('slug', $s)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $s = $base.'-'.$i++;
        }

        return $s;
    }
}
