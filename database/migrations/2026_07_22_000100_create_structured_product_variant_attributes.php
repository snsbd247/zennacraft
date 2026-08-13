<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: replaces the freeform option_values-only colour/size
 * "matrix" (Color/Size/ColorHex keys inside a JSON blob, no dedicated
 * columns) with a structured model — colour name+hex and size
 * name+dimension+price are now real per-product rows, and each stock
 * cell (a ProductVariant) references its colour/size via FK. Reason
 * (owner's, verbatim): freeform JSON can't reliably answer "how many
 * Madder Red / King do I have," and stock accuracy drives checkout,
 * RTO restocking, and order editing.
 *
 * option_values is deliberately left untouched and still gets written
 * on every save (see ProductVariantService::syncColorsAndSizes()) so
 * nothing that already reads it (e.g. the storefront's package-style
 * offer selector) breaks.
 *
 * Existing data: any ProductVariant whose option_values already had
 * both a Color and a Size key (the matrix-managed rows from the
 * previous pass) gets backfilled into real product_colors/
 * product_sizes rows and linked via the new FKs, deduped per product
 * by colour/size name. A size's price is seeded from the first variant
 * found using it (price was already per-size in practice); dimension
 * has no JSON source, so it's left null for the owner to fill in.
 * Variants with no Color/Size in their JSON (the older "package" rows
 * — Single Piece, Gift Pack, etc.) are left completely alone: no FK,
 * no data touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('hex')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('dimension')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('product_color_id')->nullable()->after('product_id')->constrained('product_colors')->nullOnDelete();
            $table->foreignId('product_size_id')->nullable()->after('product_color_id')->constrained('product_sizes')->nullOnDelete();
        });

        $this->backfillFromOptionValues();
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_color_id');
            $table->dropConstrainedForeignId('product_size_id');
        });

        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('product_colors');
    }

    protected function backfillFromOptionValues(): void
    {
        $colorIds = []; // "{product_id}|{name}" => id
        $sizeIds = [];  // "{product_id}|{name}" => id
        $colorSort = []; // product_id => next sort_order
        $sizeSort = [];

        DB::table('product_variants')->whereNotNull('option_values')->orderBy('id')->get()->each(function ($variant) use (&$colorIds, &$sizeIds, &$colorSort, &$sizeSort) {
            $options = json_decode((string) $variant->option_values, true);

            if (! is_array($options) || ! array_key_exists('Color', $options) || ! array_key_exists('Size', $options)) {
                return;
            }

            $productId = $variant->product_id;
            $colorName = trim((string) $options['Color']);
            $sizeName = trim((string) $options['Size']);

            if ($colorName === '' || $sizeName === '') {
                return;
            }

            $colorKey = $productId.'|'.$colorName;
            if (! isset($colorIds[$colorKey])) {
                $colorSort[$productId] = ($colorSort[$productId] ?? -1) + 1;
                $colorIds[$colorKey] = DB::table('product_colors')->insertGetId([
                    'product_id' => $productId,
                    'name' => $colorName,
                    'hex' => $options['ColorHex'] ?? null,
                    'sort_order' => $colorSort[$productId],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $sizeKey = $productId.'|'.$sizeName;
            if (! isset($sizeIds[$sizeKey])) {
                $sizeSort[$productId] = ($sizeSort[$productId] ?? -1) + 1;
                $sizeIds[$sizeKey] = DB::table('product_sizes')->insertGetId([
                    'product_id' => $productId,
                    'name' => $sizeName,
                    'dimension' => null,
                    'price' => $variant->price,
                    'sort_order' => $sizeSort[$productId],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('product_variants')->where('id', $variant->id)->update([
                'product_color_id' => $colorIds[$colorKey],
                'product_size_id' => $sizeIds[$sizeKey],
            ]);
        });
    }
};
