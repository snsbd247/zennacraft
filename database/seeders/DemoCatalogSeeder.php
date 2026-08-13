<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = collect([
            ['name' => 'Nokshi Kantha', 'slug' => 'demo-nokshi-kantha', 'description' => 'Premium hand-stitched quilts inspired by Bengali heritage.'],
            ['name' => 'Handmade Bags', 'slug' => 'demo-handmade-bags', 'description' => 'Artisan bags designed for everyday elegance.'],
            ['name' => 'Home Decor', 'slug' => 'demo-home-decor', 'description' => 'Warm handcrafted accents for modern homes.'],
            ['name' => 'Gift Collection', 'slug' => 'demo-gift-collection', 'description' => 'Gift-ready handmade pieces for thoughtful moments.'],
            ['name' => 'Table Decor', 'slug' => 'demo-table-decor', 'description' => 'Textile table accents and craft-led dining details.'],
            ['name' => 'Cushion Covers', 'slug' => 'demo-cushion-covers', 'description' => 'Soft embroidered cushion covers for cozy rooms.'],
        ])->map(function (array $category, int $index): Category {
            return Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => 'active',
                    'sort_order' => $index + 1,
                    'meta_title' => $category['name'].' | Zenna Craft Demo',
                    'meta_description' => $category['description'],
                ]
            );
        });

        $products = [
            ['Nokshi Kantha Heritage Quilt', 'demo-nokshi-kantha-heritage-quilt', 'demo-nokshi-kantha', 2850, 3600, 1350, '#1d2147'],
            ['Indigo Nakshi Baby Kantha', 'demo-indigo-nakshi-baby-kantha', 'demo-nokshi-kantha', 1650, 2100, 760, '#2f4172'],
            ['Terracotta Story Kantha', 'demo-terracotta-story-kantha', 'demo-nokshi-kantha', 3250, 4100, 1520, '#a85f3d'],
            ['Artisan Everyday Tote', 'demo-artisan-everyday-tote', 'demo-handmade-bags', 1450, 1850, 630, '#b88a2f'],
            ['Kantha Patch Sling Bag', 'demo-kantha-patch-sling-bag', 'demo-handmade-bags', 1250, 1600, 520, '#725438'],
            ['Handwoven Wall Accent', 'demo-handwoven-wall-accent', 'demo-home-decor', 2200, 2850, 980, '#5b4a82'],
            ['Gift Ready Kantha Box', 'demo-gift-ready-kantha-box', 'demo-gift-collection', 3950, 4850, 1800, '#bf6a48'],
            ['Embroidered Table Runner', 'demo-embroidered-table-runner', 'demo-table-decor', 1850, 2450, 820, '#87671f'],
            ['Nokshi Dining Placemat Set', 'demo-nokshi-dining-placemat-set', 'demo-table-decor', 1550, 2050, 680, '#1f655a'],
            ['Ivory Stitch Cushion Pair', 'demo-ivory-stitch-cushion-pair', 'demo-cushion-covers', 1750, 2300, 790, '#704b38'],
        ];

        foreach ($products as $index => [$name, $slug, $categorySlug, $price, $comparePrice, $costPrice, $accent]) {
            $category = $categories->firstWhere('slug', $categorySlug);
            $thumbnail = $this->demoMedia($slug, $name, $accent);
            $gallery = $this->demoMedia($slug.'-detail', $name.' detail', $accent, 1200, 1200);

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category?->id,
                    'thumbnail_id' => $thumbnail->id,
                    'name' => $name,
                    'sku' => 'ZC-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'short_description' => 'A demo handmade piece for QA testing premium storefront and Commerce OS workflows.',
                    'description' => 'This demo item is intentionally seeded for local and staging QA. It lets teams verify product cards, packages, cart, checkout, reviews, and analytics without relying on production data.',
                    'video_url' => 'https://example.com/demo-zenna-craft-video',
                    'craft_story' => 'Inspired by Bengali craft traditions, this demo piece carries the look and workflow metadata needed for Zenna Craft QA.',
                    'materials' => 'Cotton textile, artisan thread, soft lining.',
                    'dimensions' => 'Approx. '.(40 + $index * 3).' x '.(50 + $index * 2).' cm.',
                    'care_guide' => 'Hand wash gently in cold water and dry in shade.',
                    'faq_json' => [
                        ['question' => 'Is this demo product handmade?', 'answer' => 'Yes, this QA record represents a handmade Zenna Craft product.'],
                        ['question' => 'Can it be ordered with COD?', 'answer' => 'Yes, the demo checkout uses the existing COD flow.'],
                    ],
                    'price' => $price,
                    'compare_price' => $comparePrice,
                    'cost_price' => $costPrice,
                    'stock' => 35 + $index,
                    'status' => 'active',
                    'meta_title' => $name.' | Demo Product',
                    'meta_description' => 'Demo QA product for Zenna Craft: '.$name,
                ]
            );

            $product->galleryMedia()->syncWithoutDetaching([
                $gallery->id => ['collection' => 'gallery', 'sort_order' => 1],
            ]);

            $this->seedPackages($product, $price, $comparePrice, $costPrice, $accent);
        }
    }

    protected function seedPackages(Product $product, float $basePrice, float $baseComparePrice, float $baseCost, string $accent): void
    {
        $packages = [
            ['Single Piece', 'single_piece', 'Popular', 1.00, 1.00, 1.00, true],
            ['Double Piece', 'double_piece', 'Best Value', 1.82, 1.90, 1.72, true],
            ['Gift Pack', 'gift_pack', 'Gift Ready', 2.18, 2.35, 2.05, false],
            ['Premium Pack', 'premium_pack', 'Limited', 2.75, 3.10, 2.55, false],
        ];

        foreach ($packages as $index => [$name, $type, $badge, $priceFactor, $compareFactor, $costFactor, $featured]) {
            $media = $this->demoMedia($product->slug.'-'.$type, $product->name.' '.$name, $accent, 1000, 1000);

            ProductVariant::updateOrCreate(
                ['product_id' => $product->id, 'sku' => $product->sku.'-'.Str::upper(Str::replace('_', '-', $type))],
                [
                    'image_id' => $media->id,
                    'name' => $name,
                    'badge' => $badge,
                    'short_description' => $name.' demo package for QA package selector testing.',
                    'package_type' => $type,
                    'price' => round($basePrice * $priceFactor, 2),
                    'compare_price' => round($baseComparePrice * $compareFactor, 2),
                    'cost_price' => round($baseCost * $costFactor, 2),
                    'stock' => 12 + ($index * 8),
                    'weight' => round(0.55 + ($index * 0.35), 2),
                    'dimensions' => (30 + $index * 8).' x '.(40 + $index * 8).' x 6 cm',
                    'sort_order' => $index + 1,
                    'is_featured' => $featured,
                    'show_on_storefront' => true,
                    'status' => 'active',
                    'option_values' => ['package' => $name, 'demo' => true],
                ]
            );
        }
    }
}
