<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plain columns only (no FK) — a constrained foreign key forces
        // SQLite to rebuild the table, which would drop the products'
        // soft-delete-aware partial unique indexes. The size_chart_id →
        // media relation is enforced at the application layer instead.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('reseller_price', 12, 2)->nullable()->after('cost_price');
            $table->unsignedBigInteger('size_chart_id')->nullable()->after('thumbnail_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reseller_price', 'size_chart_id']);
        });
    }
};
