<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('status');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('event_time');
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('event_time');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
