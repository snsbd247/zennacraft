<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('recovery_id')->nullable()->constrained('checkout_recoveries')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('channel');
            $table->string('recipient')->nullable();
            $table->string('recipient_hash')->nullable();
            $table->string('template');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('variables')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('recipient_hash');
            $table->index('template');
            $table->index('status');
            $table->index('queued_at');
            $table->index('sent_at');
            $table->index('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
