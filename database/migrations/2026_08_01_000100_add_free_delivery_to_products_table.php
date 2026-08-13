<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Free Delivery Products" (Campaign/Offer): flag individual products so any
 * order containing one ships free, regardless of zone/threshold. Additive —
 * defaults false, so nothing changes until a product is opted in from Studio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('free_delivery')->default(false)->after('is_featured');
            $table->index('free_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['free_delivery']);
            $table->dropColumn('free_delivery');
        });
    }
};
