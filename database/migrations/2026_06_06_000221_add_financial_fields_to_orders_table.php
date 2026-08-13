<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('product_cost_total', 12, 2)->default(0)->after('total');
            $table->decimal('courier_cost_total', 12, 2)->default(0)->after('product_cost_total');
            $table->decimal('gross_profit', 12, 2)->default(0)->after('courier_cost_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'product_cost_total',
                'courier_cost_total',
                'gross_profit',
            ]);
        });
    }
};
