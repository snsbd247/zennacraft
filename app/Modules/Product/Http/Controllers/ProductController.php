<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductAttribute;
use App\Modules\Product\Models\ProductAttributeValue;
use App\Modules\Product\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    private const PER_PAGE_OPTIONS = [20, 50, 100];

    // The attribute checkboxes on the product form (matching the reference).
    public const SIZE_OPTIONS = [
        'M', 'L', 'XL', 'XXL', 'S', '28', '32', '31', '34', '36', '38', '40', '20',
        '45', 'PXL', '42', 'XXXL', '37', '39', '50', '51', '30', 'FREE SIZE',
        '8-10 YEARS', '10-12 YEARS', '12-14 YEARS', '14-16 YEARS', '52', '54', '56',
    ];

    public const COLOR_OPTIONS = [
        'BLACK', 'WHITE', 'RED', 'BLUE', 'GREEN', 'YELLOW', 'MAROON', 'NAVY',
        'GREY', 'PINK', 'PURPLE', 'ORANGE', 'BROWN', 'OLIVE', 'SKY', 'BEIGE',
    ];

    public function __construct(
        private ProductService $productService,
        private MediaService $mediaService,
    ) {}

    /**
     * "Manage Products" — the filterable product list.
     */
    public function index(Request $request): View
    {
        $filters = array_filter([
            'q' => $request->query('q'),
            'category_id' => $request->query('category_id'),
            'published' => $request->query('published'),
            'stock' => $request->query('stock'),
        ], fn ($v) => filled($v));

        $perPage = (int) $request->query('per_page', 50);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 50;
        }

        $products = $this->productService->paginate($perPage, $filters);

        return view('studio.products.index', [
            'products' => $products,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'lowStockCount' => $this->productService->countByStockStatus('low'),
            'outStockCount' => $this->productService->countByStockStatus('out'),
            'totalProducts' => (int) Product::query()->count(),
            'publishedCount' => (int) Product::query()->where('status', 'active')->count(),
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ]);
    }

    public function create(): View
    {
        return view('studio.products.form', $this->formData(new Product));
    }

    public function store(Request $request): RedirectResponse
    {
        [$attributes, $variants, $images] = $this->validated($request);

        $attributes['sku'] = filled($attributes['sku'] ?? null) ? $attributes['sku'] : 'ZC-'.strtoupper(Str::random(6));

        $product = $this->productService->create($attributes);
        $this->syncImages($product, $images);
        $this->syncVariants($product, $variants);

        // Auto-generate a one-page landing page for the new product (on by
        // default; toggle in Landing Page → Manage). Never let a landing hiccup
        // block the product create.
        $landing = null;
        if (filter_var(app(\App\Modules\Settings\Services\SettingService::class)->get('general', 'auto_landing_for_products', true), FILTER_VALIDATE_BOOLEAN)) {
            try {
                $landing = app(\App\Modules\LandingPage\Services\LandingPageService::class)->createForProduct($product);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $note = $landing ? ' A landing page was created — edit it in Landing Page → Manage.' : '';

        return redirect()->route('products.index')->with('success', $product->name.' created.'.$note);
    }

    public function edit(Product $product): View
    {
        $product->load(['category', 'thumbnail', 'sizeChart', 'galleryMedia', 'variants.image']);

        return view('studio.products.form', $this->formData($product));
    }

    public function update(Product $product, Request $request): RedirectResponse
    {
        [$attributes, $variants, $images] = $this->validated($request, $product);

        $this->productService->update($product, $attributes);
        $this->syncImages($product, $images);
        $this->syncVariants($product, $variants);

        return redirect()->route('products.index')->with('success', $product->name.' updated.');
    }

    /** Shared form payload for create + edit. */
    private function formData(Product $product): array
    {
        $categories = Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']);

        // Ancestor chain [l1, l2, l3] for pre-selecting the cascading selects.
        $chain = [];
        $cat = $product->exists ? $categories->firstWhere('id', $product->category_id) : null;
        while ($cat) {
            array_unshift($chain, $cat->id);
            $cat = $cat->parent_id ? $categories->firstWhere('id', $cat->parent_id) : null;
        }

        // Colour / size pickers come from the editable attribute catalog (seeded
        // with the old defaults). Colour = the attribute named colour/color; the
        // sizes are the values of every other attribute, so anything the store
        // adds under "Attribute/Size" shows up here too.
        $allAttrs = ProductAttribute::with(['values' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order')->orderBy('name')])->get();
        $colourAttr = $allAttrs->first(fn ($a) => in_array(mb_strtolower(trim($a->name)), ['colour', 'color'], true));

        $colorOptions = ($colourAttr ? $colourAttr->values : collect())
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values();

        $sizeOptions = $allAttrs
            ->filter(fn ($a) => ! $colourAttr || $a->id !== $colourAttr->id)
            ->flatMap(fn ($a) => $a->values)
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
            ->unique('name')->values();

        return [
            'product' => $product,
            'categories' => $categories,
            'catChain' => $chain,
            'brands' => Brand::where('status', 'active')->orderBy('position')->orderBy('name')->get(['id', 'name']),
            'sizeOptions' => $sizeOptions,
            'colorOptions' => $colorOptions,
            'mediaUrl' => fn ($media) => $media ? $this->mediaService->url($media) : null,
        ];
    }

    /** AJAX: add a colour/size option to the shared attribute catalog. */
    public function storeAttributeOption(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'in:colour,size'],
            'name' => ['required', 'string', 'max:60'],
        ]);

        $canonical = $data['group'] === 'colour' ? 'Colour' : 'Size';
        $lookups = $data['group'] === 'colour' ? ['colour', 'color'] : ['size'];
        $attr = ProductAttribute::all()->first(fn ($a) => in_array(mb_strtolower(trim($a->name)), $lookups, true))
            ?? ProductAttribute::create(['name' => $canonical, 'status' => 'active']);

        $value = $attr->values()->firstOrCreate(
            ['name' => trim($data['name'])],
            ['status' => 'active', 'sort_order' => (int) $attr->values()->max('sort_order') + 1],
        );

        return response()->json(['ok' => true, 'id' => $value->id, 'name' => $value->name]);
    }

    /** AJAX: remove a colour/size option from the shared attribute catalog. */
    public function destroyAttributeOption(ProductAttributeValue $attributeValue): JsonResponse
    {
        $attributeValue->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Validates the shared product form and returns [attributes, variants, images].
     *
     * @return array{0: array<string,mixed>, 1: array<int,array<string,mixed>>, 2: array<string,mixed>}
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'video_url' => ['nullable', 'string', 'max:1000'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'artisan_origin' => ['nullable', 'string', 'max:255'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
            'size_chart' => ['nullable', 'image', 'max:4096'],
            'gallery.*' => ['nullable', 'image', 'max:4096'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
            'variants' => ['nullable', 'array'],
            'variants.*.label' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.type' => ['nullable', 'string', 'in:Size,Color'],
            'variants.*.color' => ['nullable', 'string', 'max:60'],
            'variants.*.size' => ['nullable', 'string', 'max:60'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.image' => ['nullable', 'image', 'max:4096'],
        ]);

        $list = (float) $data['list_price'];
        $discount = (float) ($data['discount'] ?? 0);

        $attributes = [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'sku' => $data['sku'] ?? $product?->sku,
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'compare_price' => $list,
            'price' => max(0, $list - $discount),
            'cost_price' => $data['cost_price'] ?? $product?->cost_price ?? 0,
            'stock' => (int) $data['stock'],
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'artisan_origin' => $data['artisan_origin'] ?? null,
        ];

        $images = [
            'cover' => $request->file('cover_photo'),
            'size_chart' => $request->file('size_chart'),
            'gallery' => $request->file('gallery', []),
            'remove' => $data['remove_media'] ?? [],
        ];

        // Attach the per-variant image upload (nested file) to each row.
        $variants = $data['variants'] ?? [];
        foreach (array_keys($variants) as $i) {
            $variants[$i]['image'] = $request->file("variants.$i.image");
        }

        return [$attributes, $variants, $images];
    }

    private function syncImages(Product $product, array $images): void
    {
        if ($images['cover']) {
            $product->update(['thumbnail_id' => $this->mediaService->upload($images['cover'], $product->name, null, 'product')->id]);
        }
        if ($images['size_chart']) {
            $product->update(['size_chart_id' => $this->mediaService->upload($images['size_chart'], $product->name.' size chart', null, 'product')->id]);
        }
        foreach (array_filter((array) $images['gallery']) as $i => $file) {
            $media = $this->mediaService->upload($file, $product->name, null, 'product');
            $product->galleryMedia()->attach($media->id, ['collection' => 'gallery', 'sort_order' => $i]);
        }
        foreach ($images['remove'] as $mediaId) {
            $product->galleryMedia()->detach($mediaId);
            if ((int) $product->thumbnail_id === (int) $mediaId) {
                $product->update(['thumbnail_id' => null]);
            }
            Media::find($mediaId)?->delete();
        }
    }

    /**
     * Reconciles variants to the submitted set: keeps/updates matching
     * labels, creates new ones, removes the rest.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $product->loadMissing('variants');
        $desired = collect($variants);
        $desiredLabels = $desired->pluck('label')->map(fn ($l) => trim((string) $l))->all();

        foreach ($product->variants as $existing) {
            if (! in_array($existing->name, $desiredLabels, true)) {
                $existing->delete();
            }
        }

        foreach ($desired as $row) {
            $color = trim((string) ($row['color'] ?? ''));
            $size = trim((string) ($row['size'] ?? ''));

            // A row with BOTH colour and size is a combination SKU; otherwise it's
            // a single-attribute variant (backwards-compatible with the old form).
            if ($color !== '' && $size !== '') {
                $label = $color.' / '.$size;
                $optionValues = ['Color' => $color, 'Size' => $size];
            } elseif ($color !== '') {
                $label = $color;
                $optionValues = ['Color' => $color];
            } elseif ($size !== '') {
                $label = $size;
                $optionValues = ['Size' => $size];
            } else {
                $label = trim((string) $row['label']);
                $type = ($row['type'] ?? 'Size') === 'Color' ? 'Color' : 'Size';
                $optionValues = [$type => $label];
            }

            if ($label === '') {
                continue;
            }

            $price = (float) ($row['price'] ?? 0) ?: (float) $product->price;
            $stock = (int) ($row['stock'] ?? 0);
            $customSku = filled($row['sku'] ?? null) ? trim((string) $row['sku']) : null;
            $imageId = ($row['image'] ?? null) instanceof \Illuminate\Http\UploadedFile
                ? $this->mediaService->upload($row['image'], $product->name.' '.$label, null, 'product')->id
                : null;

            $variant = $product->variants()->where('name', $label)->first();
            if ($variant) {
                $variant->update(array_filter([
                    'price' => $price,
                    'stock' => $stock,
                    'sku' => $customSku,          // keep existing SKU when left blank
                    'option_values' => $optionValues,
                    'image_id' => $imageId,
                ], fn ($v) => $v !== null));

                continue;
            }

            $product->variants()->create([
                'name' => $label,
                'sku' => $customSku ?: $product->sku.'-'.strtoupper(Str::slug($label, '')).'-'.strtoupper(Str::random(3)),
                'price' => $price,
                'stock' => $stock,
                'status' => 'active',
                'show_on_storefront' => true,
                'option_values' => $optionValues,
                'image_id' => $imageId,
            ]);
        }
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $this->productService->update($product, array_merge(
            $product->only(['name', 'slug', 'sku', 'category_id', 'price', 'stock']),
            ['status' => $product->status === 'active' ? 'inactive' : 'active'],
        ));

        return back()->with('success', $product->name.' is now '.($product->fresh()->status === 'active' ? 'published' : 'unpublished').'.');
    }

    /**
     * "Copy Product" — clones the product and its variants; the copy is
     * unpublished with a fresh slug/SKU.
     */
    public function duplicate(Product $product): RedirectResponse
    {
        $product->load('variants');
        $suffix = strtoupper(Str::random(4));

        $copy = $this->productService->create(array_merge(
            $product->only([
                'category_id', 'thumbnail_id', 'name', 'short_description', 'description',
                'video_url', 'craft_story', 'materials', 'artisan_origin', 'dimensions',
                'care_guide', 'price', 'compare_price', 'cost_price', 'stock',
            ]),
            [
                'name' => $product->name.' (Copy)',
                'slug' => $product->slug.'-copy-'.strtolower($suffix),
                'sku' => $product->sku.'-COPY-'.$suffix,
                'status' => 'inactive',
            ],
        ));

        foreach ($product->variants as $variant) {
            $copy->variants()->create(array_merge(
                $variant->only(['product_color_id', 'product_size_id', 'name', 'badge', 'short_description', 'package_type', 'price', 'compare_price', 'cost_price', 'stock', 'status', 'show_on_storefront', 'option_values']),
                ['sku' => $variant->sku.'-C'.$suffix],
            ));
        }

        return redirect()->route('products.edit', $copy)->with('success', 'Product copied — review and publish the copy.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $this->productService->delete($product);

        return back()->with('success', $name.' deleted.');
    }

    /**
     * "Export Customers" — CSV of every customer who has ordered this
     * product (from real order items), for outreach/remarketing.
     */
    public function exportCustomers(Product $product): StreamedResponse
    {
        $rows = OrderItem::query()
            ->where('product_id', $product->id)
            ->with('order:id,order_number,customer_name,customer_phone,customer_email,created_at')
            ->get()
            ->map(fn (OrderItem $item) => $item->order)
            ->filter()
            ->unique('customer_phone')
            ->values();

        $filename = 'customers-'.Str::slug($product->name).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Order', 'Ordered At']);
            foreach ($rows as $order) {
                fputcsv($out, [$order->customer_name, $order->customer_phone, $order->customer_email, $order->order_number, $order->created_at?->format('Y-m-d')]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function printLabel(Product $product): View
    {
        return view('studio.products.print-label', ['product' => $product]);
    }
}
