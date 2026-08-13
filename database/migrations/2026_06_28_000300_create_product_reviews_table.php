<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_location')->nullable();
            $table->text('moderation_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('customer_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('rating');
            $table->index('is_verified_purchase');
            $table->index('is_featured');
            $table->index(['product_id', 'status', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
