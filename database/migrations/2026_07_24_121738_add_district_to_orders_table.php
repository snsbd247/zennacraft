<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Checkout has always collected this (resources/views/storefront/
            // checkout/index.blade.php, validated in CheckoutRequest) but it
            // was silently discarded — never persisted. This just gives it
            // somewhere to actually land.
            $table->string('district')->nullable()->after('address');
            $table->index('district');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['district']);
            $table->dropColumn('district');
        });
    }
};
