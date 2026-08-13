<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Analytics\Services\BehaviorEventService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Customer\Services\CustomerPersonalizationService;
use App\Modules\Facebook\Services\FacebookTrackingService;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;
use App\Modules\Performance\Services\ImageOptimizationService;
use App\Modules\Product\Models\Product;
use App\Modules\Review\Services\ProductReviewService;
use App\Modules\Seo\Services\SeoService;
use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Storefront\Services\StorefrontContentService;
use App\Modules\Storefront\Services\StorefrontService;
use App\Modules\Theme\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private StorefrontService $storefrontService,
        private MediaService $mediaService,
        private ImageOptimizationService $imageOptimizationService,
        private ThemeService $themeService,
        private SeoService $seoService,
        private FacebookTrackingService $facebookTrackingService,
        private StorefrontContentService $storefrontContentService,
        private BehaviorEventService $behaviorEventService,
        private CustomerPersonalizationService $personalizationService,
        private ProductReviewService $reviewService,
    ) {}

    public function home(Request $request): View
    {
        $this->facebookTrackingService->trackPageView($request);

        return view('storefront.home', $this->viewData([
            'latestProducts' => $this->storefrontService->latestProducts(),
            'topSellingProducts' => $this->storefrontService->topSellingProducts(),
            'storefrontSliders' => $this->storefrontContentService->activeSliders(),
            'personalization' => $this->personalizationService->storefrontBlocks($request),
            'featuredReviews' => $this->reviewService->featuredReviews(3),
            'seoPayload' => $this->seoService->homepage($request),
        ]));
    }

    public function products(Request $request): View
    {
        $this->facebookTrackingService->trackPageView($request);

        return view('storefront.products.index', $this->viewData([
            'products' => $this->storefrontService->paginatedProducts(12, trim((string) $request->query('q', ''))),
        ]));
    }

    /** AJAX search autocomplete — product suggestions as the shopper types. */
    public function searchSuggest(Request $request): \Illuminate\Http\JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $products = Product::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%'))
            ->with('thumbnail')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$term.'%'])
            ->orderBy('name')
            ->limit(6)
            ->get();

        return response()->json([
            'results' => $products->map(fn (Product $p) => [
                'name' => $p->name,
                'price' => (float) $p->price,
                'url' => route('storefront.product.show', $p->slug),
                'image' => $p->thumbnail ? $this->mediaService->url($p->thumbnail) : null,
            ])->values(),
        ]);
    }

    public function productShow(Product $product, Request $request): View
    {
        abort_unless($product->status === 'active', 404);

        $product = $this->storefrontService->productForDisplay($product);
        $this->facebookTrackingService->trackPageView($request);
        $viewContentEvent = $this->facebookTrackingService->trackViewContent($product, $request);
        $this->behaviorEventService->record($request, BehaviorEventService::EVENT_PRODUCT_VIEWED, [
            'product_id' => $product->id,
            'metadata' => [
                'active_package_count' => $product->activeVariants?->count() ?? $product->variants?->count() ?? 0,
                'base_price' => (float) $product->price,
            ],
        ]);

        return view('storefront.products.show', $this->viewData([
            'product' => $product,
            'productPersonalization' => $this->personalizationService->productDetailBlocks($request, $product),
            'reviewSummary' => $this->reviewService->productSummary($product),
            'facebookViewContentEventId' => $viewContentEvent?->event_id,
            'seoPayload' => $this->seoService->product($product, $request),
        ]));
    }

    public function categoryShow(Category $category, Request $request): View
    {
        abort_unless($category->status === 'active', 404);
        $category = $this->storefrontService->categoryForDisplay($category);
        $this->facebookTrackingService->trackPageView($request);

        return view('storefront.categories.show', $this->viewData([
            'category' => $category,
            'products' => $this->storefrontService->categoryProducts($category),
            'seoPayload' => $this->seoService->category($category, $request),
        ]));
    }

    public function landingPageShow(LandingPage $landingPage, Request $request): View
    {
        abort_unless($landingPage->status === 'active', 404);

        // Spec §3.8: remember which landing page a checkout should be
        // attributed to — read by CheckoutService::createOrder().
        session(['zc_source_landing_page_id' => $landingPage->id]);

        $landingPage = $this->storefrontService->landingPageForDisplay($landingPage);
        $this->facebookTrackingService->trackPageView($request);

        // Products shown inside the on-page order form. Landing pages are
        // one-page: the CTA scrolls to this form and the shopper orders without
        // a separate checkout. We resolve the product list in priority order so
        // the form always appears when there's something to sell:
        //   1. Curated suggested_products (admin's exact picks + order).
        //   2. A cta_url that points at one of our own product pages -> feature
        //      that product (older "product link" pages become one-page too).
        //   3. No curation and no product link -> the live catalogue, so the
        //      shopper can pick any active product.
        //   4. A genuinely external cta_url -> keep it as a plain link (no form).
        $ids = $landingPage->suggested_products ?? [];
        $variantLoad = ['thumbnail', 'category', 'variants' => fn ($q) => $q->where('status', 'active')->where('show_on_storefront', true)->orderBy('sort_order')->with('image')];

        // A cta_url may reference a specific product either as a product page
        // (/products/{slug}) or as a checkout link (?product_id={id}) — either
        // way, feature that product in the one-page form instead of linking off.
        $ctaUrl = (string) $landingPage->cta_url;
        $ctaProductId = null;
        if (filled($ctaUrl)) {
            if (preg_match('#/products/([^/?\#]+)#', $ctaUrl, $m)) {
                $ctaProductId = Product::where('slug', $m[1])->where('status', 'active')->value('id');
            } elseif (preg_match('#[?&]product_id=(\d+)#', $ctaUrl, $m)) {
                $ctaProductId = Product::whereKey((int) $m[1])->where('status', 'active')->value('id');
            }
        }

        // Only a genuinely off-site link (different host, no product resolved)
        // keeps its link with no form; everything else is one-page.
        $stripWww = fn ($h) => preg_replace('/^www\./', '', (string) $h);
        $ctaHost = filled($ctaUrl) ? parse_url($ctaUrl, PHP_URL_HOST) : null;
        $ctaIsExternal = $ctaHost && $stripWww($ctaHost) !== $stripWww($request->getHost()) && ! $ctaProductId;

        if (! empty($ids)) {
            $suggestedProducts = Product::whereIn('id', $ids)->where('status', 'active')
                ->with($variantLoad)->get()
                ->sortBy(fn ($p) => array_search($p->id, $ids))->values();
        } elseif ($ctaIsExternal) {
            $suggestedProducts = collect();
        } elseif ($ctaProductId) {
            $suggestedProducts = Product::whereKey($ctaProductId)->with($variantLoad)->get();
        } else {
            $suggestedProducts = Product::where('status', 'active')
                ->with($variantLoad)->latest()->limit(50)->get();
        }

        $delivery = app(\App\Modules\Checkout\Services\DeliveryChargeService::class);
        $deliveryZones = collect(\App\Modules\Checkout\Services\DeliveryChargeService::ZONES)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'charge' => (float) $delivery->feeFor($key, 1)])
            ->values()->all();

        $galleryIds = $landingPage->gallery ?? [];
        $galleryImages = empty($galleryIds) ? collect() : \App\Modules\Media\Models\Media::whereIn('id', $galleryIds)->get()
            ->sortBy(fn ($m) => array_search($m->id, $galleryIds))->values();

        $landingReviews = ($landingPage->show_reviews && ! empty($ids))
            ? \App\Modules\Review\Models\ProductReview::whereIn('product_id', $ids)->where('status', 'approved')->latest()->limit(12)->get()
            : collect();

        return view('storefront.landing-pages.show', $this->viewData([
            'landingPage' => $landingPage,
            'suggestedProducts' => $suggestedProducts,
            'deliveryZones' => $deliveryZones,
            'freeDeliveryThreshold' => (float) $delivery->freeDeliveryThreshold(),
            'freeDeliveryAll' => (bool) $delivery->freeDeliveryForAllOrders(),
            'galleryImages' => $galleryImages,
            'landingReviews' => $landingReviews,
            'seoPayload' => $this->seoService->landingPage($landingPage, $request),
        ]));
    }

    public function cmsPageShow(CmsPage $cmsPage, Request $request): View
    {
        abort_unless($cmsPage->active, 404);

        $cmsPage = $this->storefrontContentService->cmsPageForDisplay($cmsPage);
        $this->facebookTrackingService->trackPageView($request);

        return view('storefront.cms-pages.show', $this->viewData([
            'cmsPage' => $cmsPage,
            'seoPayload' => $this->seoService->cmsPage($cmsPage, $request),
        ]));
    }

    protected function viewData(array $data = []): array
    {
        return array_merge([
            'activeCategories' => $this->storefrontService->activeCategories(),
            'cmsFooterPages' => $this->storefrontContentService->footerPages(),
            'themeSettings' => $this->themeService->settings(),
            'themeMediaUrl' => fn (string $key): ?string => $this->themeService->mediaUrl($key),
            'mediaUrl' => fn (?Media $media): ?string => $media ? $this->mediaService->url($media) : null,
            'imageAttributes' => fn (?Media $media, string $alt, string $class = '', bool $eager = false, string $sizes = '100vw'): array => $this->imageOptimizationService->attributes($media, $alt, $class, $eager, $sizes),
        ], $data);
    }
}
