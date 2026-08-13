<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plain nullable column + index (no DB-level foreign key): a constrained
        // FK forces SQLite to rebuild the table, which drops the partial unique
        // index on `slug`/`sku` (WHERE deleted_at IS NULL). The brand() relation
        // and the `exists:brands,id` validation give us the integrity we need.
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('category_id');
                $table->index('brand_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'brand_id')) {
                $table->dropIndex(['brand_id']);
                $table->dropColumn('brand_id');
            }
        });
    }
};
