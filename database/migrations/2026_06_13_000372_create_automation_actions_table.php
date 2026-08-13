<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->string('action_key');
            $table->string('status')->default('pending');
            $table->text('result_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index('automation_run_id');
            $table->index('action_key');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_actions');
    }
};
