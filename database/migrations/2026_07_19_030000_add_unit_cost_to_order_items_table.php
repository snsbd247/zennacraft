<?php

use App\Modules\Order\Models\OrderItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3E: order-item edits need to recompute order.product_cost_total
 * without depending on the product/variant's CURRENT cost_price (which
 * may have changed since the order was placed). Checkout never stored a
 * per-line cost snapshot — product_cost_total was only ever computed
 * once, at order-creation time, from the cost_price live at that moment
 * (CheckoutService::createOrder()). Backfilling with today's cost_price
 * is the best available approximation for historical rows; it does not
 * change any existing order's stored totals, only gives future edits on
 * those orders something accurate to recompute from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('price');
        });

        OrderItem::query()
            ->whereNull('unit_cost')
            ->with(['product', 'variant'])
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $cost = (float) ($item->variant?->cost_price ?? $item->product?->cost_price ?? 0);
                    $item->newQuery()->whereKey($item->id)->update(['unit_cost' => $cost]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
