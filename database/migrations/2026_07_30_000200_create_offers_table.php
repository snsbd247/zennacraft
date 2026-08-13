<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placement-based storefront offers (e.g. the cart "spend more, unlock a free
 * gift" bar). "placement" says WHERE the offer shows so it's managed clearly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('placement', 40)->default('cart_free_gift');
            $table->decimal('threshold_amount', 12, 2)->default(0);
            $table->string('reward_text')->nullable();
            $table->foreignId('reward_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['placement', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
