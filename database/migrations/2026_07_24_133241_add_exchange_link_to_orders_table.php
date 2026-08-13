<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // When set, this order was created as an exchange/replacement
            // for the referenced original order (Studio "Add Exchange
            // Order"). Nullable — the vast majority of orders are not
            // exchanges. self-referential, nulled if the original is ever
            // deleted so this order survives as a normal order.
            $table->foreignId('exchanged_from_order_id')
                ->nullable()
                ->after('source_landing_page_id')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchanged_from_order_id');
        });
    }
};
