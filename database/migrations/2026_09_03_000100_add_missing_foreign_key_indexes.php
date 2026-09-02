<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('payment_transaction_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('size_chart_id');
        });

        Schema::table('product_damage_items', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('supplier_id');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_transaction_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['size_chart_id']);
        });

        Schema::table('product_damage_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
