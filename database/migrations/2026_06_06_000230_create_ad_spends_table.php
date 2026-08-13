<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_spends', function (Blueprint $table) {
            $table->id();
            $table->date('spend_date')->unique();
            $table->string('platform');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            $table->index('platform');
            $table->index('staff_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spends');
    }
};
