<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plain indexed column (no DB FK) — links an auto-generated landing page
        // to the product it was created for, so we never make a duplicate and
        // can offer a direct "edit landing" link. No constrained FK: it keeps
        // SQLite from rebuilding the table (which would drop the slug unique
        // index), same rule as products.brand_id.
        Schema::table('landing_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('landing_pages', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('id');
                $table->index('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            if (Schema::hasColumn('landing_pages', 'product_id')) {
                $table->dropIndex(['product_id']);
                $table->dropColumn('product_id');
            }
        });
    }
};
