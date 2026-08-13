<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('staff_user_id');
            $table->index('outcome');
            $table->index('next_follow_up_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_verification_attempts');
    }
};
