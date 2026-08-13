<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('not_expense'); // fixed_expense | not_expense
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_purposes');
    }
};
