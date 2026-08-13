<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('risk_hold_status')->default('released')->after('notes');
            $table->text('risk_hold_reason')->nullable()->after('risk_hold_status');
            $table->timestamp('risk_hold_at')->nullable()->after('risk_hold_reason');
            $table->timestamp('risk_hold_released_at')->nullable()->after('risk_hold_at');
            $table->foreignId('risk_hold_released_by')->nullable()->after('risk_hold_released_at')->constrained('staff_users')->nullOnDelete();
            $table->text('risk_hold_release_note')->nullable()->after('risk_hold_released_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_hold_released_by');
            $table->dropColumn([
                'risk_hold_status',
                'risk_hold_reason',
                'risk_hold_at',
                'risk_hold_released_at',
                'risk_hold_release_note',
            ]);
        });
    }
};
