<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('system');
            $table->json('rules_json')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_segments');
    }
};
