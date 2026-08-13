<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('backup_type')->default('manual');
            $table->string('backup_scope')->default('full');
            $table->string('status')->default('pending');
            $table->string('disk')->default('local');
            $table->string('directory')->nullable();
            $table->string('database_path')->nullable();
            $table->string('files_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->unsignedBigInteger('total_size')->default(0);
            $table->string('checksum')->nullable();
            $table->string('validation_status')->default('pending');
            $table->text('validation_message')->nullable();
            $table->boolean('restore_ready')->default(false);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index('backup_type');
            $table->index('backup_scope');
            $table->index('status');
            $table->index('validation_status');
            $table->index('restore_ready');
            $table->index('created_by');
            $table->index('started_at');
            $table->index('completed_at');
            $table->foreign('created_by')->references('id')->on('staff_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
