<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Studio "Combo Products" list shows each bundle with a thumbnail, a code, and
 * a Price / Sale Price / Discount trio. `price` already stores the sale price;
 * `regular_price` is the crossed-out "was" price the discount is measured
 * against, `code` is the shown SKU-style label, and `image_id` is the card
 * thumbnail. All additive/nullable so existing combos keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->string('code')->nullable()->after('slug');
            $table->decimal('regular_price', 12, 2)->default(0)->after('price');
            $table->foreignId('image_id')->nullable()->after('code')->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_id');
            $table->dropColumn(['code', 'regular_price']);
        });
    }
};
