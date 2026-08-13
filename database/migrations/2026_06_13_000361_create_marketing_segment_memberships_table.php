<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_segment_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_segment_id')->constrained('marketing_segments')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['marketing_segment_id', 'customer_id'], 'marketing_segment_customer_unique');
            $table->index('customer_id');
            $table->index('marketing_segment_id');
            $table->index('joined_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_segment_memberships');
    }
};
