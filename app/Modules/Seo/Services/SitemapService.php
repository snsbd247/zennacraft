<?php

namespace App\Modules\Seo\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Product\Models\Product;
use App\Modules\Storefront\Models\CmsPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SitemapService
{
    public function urls(): array
    {
        $urls = [
            $this->entry(route('storefront.home'), now(), 'daily', 1.0),
        ];

        Category::query()
            ->where('status', 'active')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($categories) use (&$urls): void {
                foreach ($categories as $category) {
                    $urls[] = $this->entry(
                        route('storefront.category.show', $category->slug),
                        $category->updated_at,
                        'weekly',
                        0.8
                    );
                }
            });

        Product::query()
            ->where('status', 'active')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$urls): void {
                foreach ($products as $product) {
                    $urls[] = $this->entry(
                        route('storefront.product.show', $product->slug),
                        $product->updated_at,
                        'weekly',
                        0.7
                    );
                }
            });

        LandingPage::query()
            ->where('status', 'active')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($landingPages) use (&$urls): void {
                foreach ($landingPages as $landingPage) {
                    $urls[] = $this->entry(
                        route('storefront.landing-pages.show', $landingPage->slug),
                        $landingPage->updated_at,
                        'monthly',
                        0.6
                    );
                }
            });

        CmsPage::query()
            ->where('active', true)
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($pages) use (&$urls): void {
                foreach ($pages as $page) {
                    $urls[] = $this->entry(
                        route('storefront.cms-pages.show', $page->slug),
                        $page->updated_at,
                        'monthly',
                        0.5
                    );
                }
            });

        return $urls;
    }

    public function xml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

        foreach ($this->urls() as $entry) {
            $xml .= "  <url>".PHP_EOL;
            $xml .= '    <loc>'.$this->escape($entry['loc']).'</loc>'.PHP_EOL;

            if (! empty($entry['lastmod'])) {
                $xml .= '    <lastmod>'.$this->escape($entry['lastmod']).'</lastmod>'.PHP_EOL;
            }

            $xml .= '    <changefreq>'.$this->escape($entry['changefreq']).'</changefreq>'.PHP_EOL;
            $xml .= '    <priority>'.number_format($entry['priority'], 1, '.', '').'</priority>'.PHP_EOL;
            $xml .= "  </url>".PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }

    protected function entry(string $loc, mixed $lastmod = null, string $changefreq = 'weekly', float $priority = 0.5): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $this->formatLastMod($lastmod),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    protected function formatLastMod(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
