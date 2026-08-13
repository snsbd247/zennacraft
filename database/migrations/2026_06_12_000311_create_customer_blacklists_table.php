<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_blacklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('phone');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_blacklists');
    }
};
