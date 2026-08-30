<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->string('offsite_status')->nullable()->after('restore_ready');
            $table->string('offsite_path')->nullable()->after('offsite_status');
            $table->timestamp('offsite_uploaded_at')->nullable()->after('offsite_path');
        });
    }

    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->dropColumn(['offsite_status', 'offsite_path', 'offsite_uploaded_at']);
        });
    }
};
