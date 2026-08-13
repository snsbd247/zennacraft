<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('type');
            $table->foreignId('account_purpose_id')->nullable()->after('purpose')->constrained('account_purposes')->nullOnDelete();
            $table->foreignId('fund_transfer_id')->nullable()->after('expense_id')->constrained('fund_transfers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_purpose_id');
            $table->dropConstrainedForeignId('fund_transfer_id');
            $table->dropColumn('purpose');
        });
    }
};
