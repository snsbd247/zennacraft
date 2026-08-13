<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Recorded at order time by the Studio "Create Order" (POS) flow:
            // how much the customer paid up front and through which channel.
            // COD orders keep these at 0/null.
            $table->decimal('paid_amount', 12, 2)->default(0)->after('total');
            $table->string('paid_by')->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'paid_by']);
        });
    }
};
