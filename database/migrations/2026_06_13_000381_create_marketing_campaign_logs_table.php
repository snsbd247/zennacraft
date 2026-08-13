<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('log_type');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('marketing_campaign_id');
            $table->index('log_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_logs');
    }
};
