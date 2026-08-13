<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_provider_id')->unique()->constrained('courier_providers')->cascadeOnDelete();
            $table->unsignedInteger('total_shipments')->default(0);
            $table->unsignedInteger('assigned_shipments')->default(0);
            $table->unsignedInteger('shipped_shipments')->default(0);
            $table->unsignedInteger('delivered_shipments')->default(0);
            $table->unsignedInteger('returned_shipments')->default(0);
            $table->unsignedInteger('cancelled_shipments')->default(0);
            $table->unsignedInteger('failed_shipments')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->decimal('return_rate', 5, 2)->default(0);
            $table->decimal('failure_rate', 5, 2)->default(0);
            $table->decimal('avg_delivery_days', 8, 2)->nullable();
            $table->decimal('total_cod_amount', 12, 2)->default(0);
            $table->decimal('total_delivery_charge', 12, 2)->default(0);
            $table->decimal('total_courier_cost', 12, 2)->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index('success_rate');
            $table->index('return_rate');
            $table->index('last_calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_metrics');
    }
};
