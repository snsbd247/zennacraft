<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra fields for the Studio Category manager (SEO + sub-category commerce). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_keywords')->nullable()->after('meta_description');
            $table->text('meta_content')->nullable()->after('meta_keywords');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('sort_order');
            $table->decimal('merchant_commission', 12, 2)->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['meta_keywords', 'meta_content', 'discount_percent', 'merchant_commission']);
        });
    }
};
