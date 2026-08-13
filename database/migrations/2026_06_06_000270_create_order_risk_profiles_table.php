<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level')->default('low');
            $table->json('risk_reasons')->nullable();
            $table->unsignedTinyInteger('customer_risk_score')->default(0);
            $table->unsignedTinyInteger('courier_risk_score')->default(0);
            $table->unsignedTinyInteger('verification_risk_score')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index('risk_score');
            $table->index('risk_level');
            $table->index('last_calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_risk_profiles');
    }
};
