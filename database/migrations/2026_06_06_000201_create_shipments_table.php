<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_provider_id')->nullable()->constrained('courier_providers')->nullOnDelete();
            $table->string('tracking_number')->nullable();
            $table->string('consignment_id')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->decimal('courier_cost', 12, 2)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index('courier_provider_id');
            $table->index('tracking_number');
            $table->index('consignment_id');
            $table->index('status');
            $table->index('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
