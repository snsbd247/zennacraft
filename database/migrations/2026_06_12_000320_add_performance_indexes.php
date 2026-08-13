<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'products_status_created_at_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['status', 'sort_order', 'name'], 'categories_status_sort_name_index');
        });

        Schema::table('landing_pages', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'landing_pages_status_updated_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            $table->index(['customer_id', 'status', 'created_at'], 'orders_customer_status_created_at_index');
        });

        Schema::table('fraud_events', function (Blueprint $table) {
            $table->index('created_at', 'fraud_events_created_at_index');
        });

        Schema::table('customer_blacklists', function (Blueprint $table) {
            $table->index(['phone', 'active'], 'customer_blacklists_phone_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_blacklists', function (Blueprint $table) {
            $table->dropIndex('customer_blacklists_phone_active_index');
        });

        Schema::table('fraud_events', function (Blueprint $table) {
            $table->dropIndex('fraud_events_created_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_status_created_at_index');
            $table->dropIndex('orders_status_created_at_index');
        });

        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropIndex('landing_pages_status_updated_at_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_status_sort_name_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_created_at_index');
        });
    }
};
