<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Generic across any online gateway (bKash today; Nagad/SSLCommerz
            // reuse the same three columns when wired up) — null/'' for COD
            // orders, which never touch this flow.
            $table->string('payment_gateway_reference')->nullable()->after('paid_by');
            $table->string('payment_transaction_id')->nullable()->after('payment_gateway_reference');
            $table->string('payment_status')->nullable()->after('payment_transaction_id');

            $table->index('payment_gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_gateway_reference', 'payment_transaction_id', 'payment_status']);
        });
    }
};
