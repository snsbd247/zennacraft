<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton table — always exactly one row (id = 1). A dedicated table
        // rather than the generic settings store so the hardening checks
        // sprinkled across the app (see LicenseGuard) can do a single fast,
        // predictable read without going through the settings cache layer.
        Schema::create('license_states', function (Blueprint $table) {
            $table->id();
            $table->text('license_key')->nullable(); // encrypted at rest
            $table->string('status')->nullable(); // active|grace|expired|suspended|revoked|denied
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('last_check_ok')->default(false);
            $table->text('message')->nullable();
            $table->text('signature')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_states');
    }
};
