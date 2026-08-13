<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Studio product-editor redesign: adds the "Bestseller" / "Feature on
 * home page" toggles and the optional "Artisan / origin" field from the
 * drawer mockup — none of these existed as real columns before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_bestseller')->default(false)->after('status');
            $table->boolean('is_featured')->default(false)->after('is_bestseller');
            $table->string('artisan_origin')->nullable()->after('materials');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_bestseller', 'is_featured', 'artisan_origin']);
        });
    }
};
