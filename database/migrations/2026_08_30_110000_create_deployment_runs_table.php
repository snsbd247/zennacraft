<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running'); // running | completed | failed
            $table->string('from_commit')->nullable();
            $table->string('to_commit')->nullable();
            $table->unsignedInteger('commits_pulled')->default(0);
            $table->boolean('migrations_ran')->default(false);
            $table->boolean('composer_ran')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_run_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('info'); // info | warning | error
            $table->string('step'); // fetch | stash | pull | composer | migrate | cache
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_logs');
        Schema::dropIfExists('deployment_runs');
    }
};
