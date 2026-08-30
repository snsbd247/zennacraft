<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_states', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('license_key');
            $table->timestamp('activated_at')->nullable()->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('license_states', function (Blueprint $table) {
            $table->dropColumn(['domain', 'activated_at']);
        });
    }
};
