<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('trigger_key');
            $table->string('status')->default('draft');
            $table->json('conditions_json')->nullable();
            $table->json('actions_json')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            $table->index('slug');
            $table->index('trigger_key');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_workflows');
    }
};
