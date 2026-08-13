<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Damage / stock-loss records (Damage Products page). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_damages', function (Blueprint $table) {
            $table->id();
            $table->date('damage_date');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('product_damage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_id')->constrained('product_damages')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_damage_items');
        Schema::dropIfExists('product_damages');
    }
};
