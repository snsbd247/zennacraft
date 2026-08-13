<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_user_role', function (Blueprint $table) {
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            $table->unique(['staff_user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_user_role');
    }
};
