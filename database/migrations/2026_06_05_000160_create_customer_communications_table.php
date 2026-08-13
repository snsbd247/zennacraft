<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->enum('type', ['call', 'sms', 'whatsapp', 'email', 'note']);
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['success', 'failed', 'pending'])->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('order_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_communications');
    }
};
