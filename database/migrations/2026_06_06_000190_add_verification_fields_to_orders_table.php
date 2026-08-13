<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('verification_status')->default('unverified')->after('status');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('staff_users')->nullOnDelete();

            $table->index('verification_status');
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['verified_at']);
            $table->dropColumn(['verification_status', 'verified_at', 'verified_by']);
        });
    }
};
