<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sliders now drive three distinct homepage banners (hero carousel, side
 * banner, mid promo banner). "placement" records which one a slider feeds so
 * each spot is managed from its own Studio page. Existing rows default to the
 * hero, matching how they rendered before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_sliders', function (Blueprint $table) {
            $table->string('placement', 40)->default('home_hero')->after('id');
            $table->index('placement');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_sliders', function (Blueprint $table) {
            $table->dropIndex(['placement']);
            $table->dropColumn('placement');
        });
    }
};
