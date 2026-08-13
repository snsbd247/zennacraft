<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_group');
            $table->string('setting_key');
            $table->longText('value')->nullable();
            $table->string('data_type')->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('setting_group');
            $table->index('setting_key');
            $table->unique(['setting_group', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
