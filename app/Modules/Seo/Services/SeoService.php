<?php

namespace App\Modules\Seo\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;
use App\Modules\Product\Models\Product;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Storefront\Models\CmsPage;
use App\Modules\Theme\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoService
{
    protected const TRACKING_PARAMS = [
        'fbclid',
        'gclid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function __construct(
        private SettingService $settingService,
        private ThemeService $themeService,
        private MediaService $mediaService,
    ) {}

    public function homepage(?Request $request = null): array
    {
        $siteName = $this->siteName();
        $title = $this->filledValue($this->settingService->get('general', 'seo_default_title')) ?: $siteName;
        $description = $this->cleanDescription(
            $this->settingService->get('general', 'seo_default_description')
        );
        $keywords = $this->normalizeKeywords(
            $this->settingService->get('general', 'seo_default_keywords')
        );
        $canonicalUrl = $this->canonicalUrl($request);
        $imageUrl = $this->defaultOgImageUrl();

        return $this->buildPayload(
            title: $title,
            description: $description,
            keywords: $keywords,
            canonicalUrl: $canonicalUrl,
            ogType: 'website',
            ogImage: $imageUrl,
            schema: $this->homepageSchema($canonicalUrl, $imageUrl, $description),
        );
    }

    public function product(Product $product, ?Request $request = null): array
    {
        $product->loadMissing(['category', 'thumbnail', 'galleryMedia']);

        $siteName = $this->siteName();
        $canonicalUrl = $this->canonicalUrl($request);
        $title = $this->filledValue($product->meta_title) ?: $this->pageTitle($product->name);
        $description = $this->filledValue($product->meta_description)
            ?: $this->cleanDescription($product->short_description)
            ?: $this->cleanDescription($product->description)
            ?: $this->cleanDescription($this->settingService->get('general', 'seo_default_description'));
        $keywords = $this->normalizeKeywords([
            $product->name,
            $product->category?->name,
            $product->sku,
        ]);
        $imageUrl = $this->productImageUrl($product);

        return $this->buildPayload(
            title: $title,
            description: $description,
            keywords: $keywords,
            canonicalUrl: $canonicalUrl,
            ogType: 'product',
            ogImage: $imageUrl,
            schema: $this->productSchema($product, $canonicalUrl, $imageUrl, $siteName, $description),
        );
    }

    public function category(Category $category, ?Request $request = null): array
    {
        $category->loadMissing(['image', 'parent.parent.parent']);

        $siteName = $this->siteName();
        $canonicalUrl = $this->canonicalUrl($request);
        $title = $this->filledValue($category->meta_title) ?: $this->pageTitle($category->name);
        $description = $this->filledValue($category->meta_description)
            ?: $this->cleanDescription($category->description)
            ?: $this->cleanDescription($this->settingService->get('general', 'seo_default_description'));
        $keywords = $this->normalizeKeywords([
            $category->name,
            $category->parent?->name,
        ]);
        $imageUrl = $this->mediaUrl($category->image) ?: $this->defaultOgImageUrl();

        return $this->buildPayload(
            title: $title,
            description: $description,
            keywords: $keywords,
            canonicalUrl: $canonicalUrl,
            ogType: 'website',
            ogImage: $imageUrl,
            schema: $this->categorySchema($category, $canonicalUrl, $siteName, $description),
        );
    }

    public function landingPage(LandingPage $landingPage, ?Request $request = null): array
    {
        $siteName = $this->siteName();
        $canonicalUrl = $this->canonicalUrl($request);
        $title = $this->filledValue($landingPage->meta_title) ?: $this->pageTitle($landingPage->title);
        $description = $this->filledValue($landingPage->meta_description)
            ?: $this->cleanDescription($landingPage->hero_subtitle)
            ?: $this->excerpt($landingPage->content)
            ?: $this->cleanDescription($this->settingService->get('general', 'seo_default_description'));
        $keywords = $this->normalizeKeywords([
            $landingPage->title,
            $landingPage->hero_title,
        ]);
        $imageUrl = $this->defaultOgImageUrl();

        return $this->buildPayload(
            title: $title,
            description: $description,
            keywords: $keywords,
            canonicalUrl: $canonicalUrl,
            ogType: 'article',
            ogImage: $imageUrl,
            schema: $this->landingPageSchema($landingPage, $canonicalUrl, $siteName, $description, $imageUrl),
        );
    }

    public function cmsPage(CmsPage $cmsPage, ?Request $request = null): array
    {
        $siteName = $this->siteName();
        $canonicalUrl = $this->canonicalUrl($request);
        $title = $this->filledValue($cmsPage->meta_title) ?: $this->pageTitle($cmsPage->title);
        $description = $this->filledValue($cmsPage->meta_description)
            ?: $this->excerpt($cmsPage->content)
            ?: $this->cleanDescription($this->settingService->get('general', 'seo_default_description'));
        $keywords = $this->normalizeKeywords([$cmsPage->title, $siteName]);
        $imageUrl = $this->defaultOgImageUrl();

        return $this->buildPayload(
            title: $title,
            description: $description,
            keywords: $keywords,
            canonicalUrl: $canonicalUrl,
            ogType: 'article',
            ogImage: $imageUrl,
            schema: $this->cmsPageSchema($cmsPage, $canonicalUrl, $description),
        );
    }

    public function canonicalUrl(?Request $request = null): string
    {
        if (! $request) {
            return url()->current();
        }

        return $request->fullUrlWithoutQuery(self::TRACKING_PARAMS);
    }

    protected function buildPayload(
        string $title,
        ?string $description,
        ?string $keywords,
        string $canonicalUrl,
        string $ogType,
        ?string $ogImage,
        array $schema,
    ): array {
        $twitterCard = $ogImage ? 'summary_large_image' : 'summary';

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical_url' => $canonicalUrl,
            'robots' => 'index,follow',
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_url' => $canonicalUrl,
            'og_type' => $ogType,
            'twitter_card' => $twitterCard,
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $ogImage,
            'schema' => $schema,
        ];
    }

    protected function homepageSchema(string $canonicalUrl, ?string $imageUrl, ?string $description): array
    {
        $sameAs = array_values(array_filter([
            $this->themeService->get('social_facebook', $this->themeService->get('facebook_url')),
            $this->themeService->get('social_instagram', $this->themeService->get('instagram_url')),
            $this->themeService->get('social_tiktok'),
            $this->themeService->get('social_youtube', $this->themeService->get('youtube_url')),
            $this->themeService->get('social_telegram'),
            $this->themeService->get('social_messenger'),
            $this->themeService->get('social_pinterest'),
            $this->themeService->get('social_linkedin'),
        ]));

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->siteName(),
            'url' => url('/'),
        ];

        if ($imageUrl) {
            $organization['logo'] = $imageUrl;
        }

        if ($this->publicEmail()) {
            $organization['email'] = $this->publicEmail();
        }

        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->siteName(),
            'url' => $canonicalUrl,
        ];

        if ($description) {
            $website['description'] = $description;
        }

        return [$organization, $website];
    }

    protected function productSchema(Product $product, string $canonicalUrl, ?string $imageUrl, string $siteName, ?string $description): array
    {
        $images = collect([
            $imageUrl,
        ])->filter()->values()->all();
        $sellingPrice = data_get($product, 'selling_price');
        $offerPrice = ($sellingPrice !== null && $sellingPrice !== '')
            ? $sellingPrice
            : $product->price;

        if ($product->relationLoaded('galleryMedia')) {
            foreach ($product->galleryMedia as $media) {
                $url = $this->mediaUrl($media);
                if ($url) {
                    $images[] = $url;
                }
            }
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $description,
            'image' => array_values(array_unique(array_filter($images))),
            'url' => $canonicalUrl,
            'brand' => [
                '@type' => 'Brand',
                'name' => $siteName,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => $this->currency(),
                'price' => (string) $offerPrice,
                'availability' => ((int) $product->stock > 0)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ], fn ($value) => $value !== null && $value !== []);
    }

    protected function categorySchema(Category $category, string $canonicalUrl, string $siteName, ?string $description): array
    {
        $items = [];
        $position = 1;

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $siteName,
            'item' => url('/'),
        ];

        foreach ($this->categoryAncestors($category) as $ancestor) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $ancestor->name,
                'item' => route('storefront.category.show', $ancestor->slug),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $category->name,
            'item' => $canonicalUrl,
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
            'description' => $description,
        ];
    }

    protected function landingPageSchema(LandingPage $landingPage, string $canonicalUrl, string $siteName, ?string $description, ?string $imageUrl): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $landingPage->title,
            'url' => $canonicalUrl,
        ];

        if ($description) {
            $schema['description'] = $description;
        }

        if ($imageUrl) {
            $schema['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $imageUrl,
            ];
        }

        return $schema;
    }

    protected function cmsPageSchema(CmsPage $cmsPage, string $canonicalUrl, ?string $description): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $cmsPage->title,
            'url' => $canonicalUrl,
            'description' => $description,
        ], fn ($value) => $value !== null && $value !== []);
    }

    protected function categoryAncestors(Category $category): array
    {
        $ancestors = [];
        $current = $category->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }

    protected function siteName(): string
    {
        return (string) $this->settingService->get('general', 'site_name', config('app.name', 'Zenna Craft'));
    }

    protected function currency(): string
    {
        return (string) $this->settingService->get('general', 'currency', 'BDT');
    }

    protected function publicEmail(): ?string
    {
        $email = trim((string) $this->settingService->get('general', 'site_email', ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (str_ends_with(strtolower($email), '@example.com')) {
            return null;
        }

        return $email;
    }

    protected function defaultOgImageUrl(): ?string
    {
        $settingImage = $this->mediaUrlFromSetting('seo_default_og_image');
        if ($settingImage) {
            return $settingImage;
        }

        return $this->themeMediaUrl('site_logo');
    }

    protected function themeMediaUrl(string $key): ?string
    {
        return $this->themeService->mediaUrl($key);
    }

    protected function mediaUrlFromSetting(string $key): ?string
    {
        $mediaId = $this->settingService->get('general', $key);

        if (! $mediaId) {
            return null;
        }

        $media = Media::find($mediaId);

        return $media ? $this->mediaUrl($media) : null;
    }

    protected function productImageUrl(Product $product): ?string
    {
        if ($product->relationLoaded('thumbnail') && $product->thumbnail) {
            return $this->mediaUrl($product->thumbnail);
        }

        if ($product->relationLoaded('galleryMedia')) {
            foreach ($product->galleryMedia as $media) {
                $url = $this->mediaUrl($media);
                if ($url) {
                    return $url;
                }
            }
        }

        return $this->defaultOgImageUrl();
    }

    protected function mediaUrl(?Media $media): ?string
    {
        return $media ? $this->mediaService->url($media) : null;
    }

    protected function pageTitle(string $value): string
    {
        return trim($value).' | '.$this->siteName();
    }

    protected function filledValue(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }

    protected function cleanDescription(mixed $value): ?string
    {
        $value = $this->filledValue($value);

        if (! $value) {
            return null;
        }

        $cleaned = Str::of(strip_tags($value))->squish()->toString();

        return $cleaned !== '' ? Str::limit($cleaned, 160) : null;
    }

    protected function excerpt(mixed $value): ?string
    {
        $value = $this->filledValue($value);

        if (! $value) {
            return null;
        }

        $cleaned = Str::of(strip_tags($value))->squish()->toString();

        return $cleaned !== '' ? Str::limit($cleaned, 160) : null;
    }

    protected function normalizeKeywords(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return null;
        }

        $keywords = collect($value)
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => trim((string) $item))
            ->unique()
            ->values()
            ->all();

        return $keywords === [] ? null : implode(', ', $keywords);
    }
}
