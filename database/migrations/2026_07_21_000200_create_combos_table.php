<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New Combo Offers module (Studio: bundle products together at a
 * special price). sold_count starts and stays at 0 until a real
 * storefront combo-checkout path exists — deliberately not faked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('status')->default('inactive');
            $table->boolean('feature_on_home')->default(false);
            $table->unsignedInteger('sold_count')->default(0);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};
