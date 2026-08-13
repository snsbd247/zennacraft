<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_message_id')->constrained('communication_messages')->cascadeOnDelete();
            $table->string('channel');
            $table->string('status');
            $table->string('provider')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index('communication_message_id');
            $table->index('channel');
            $table->index('status');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
