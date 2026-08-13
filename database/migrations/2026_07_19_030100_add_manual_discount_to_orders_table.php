<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3E: a manual, staff-applied discount ("I negotiated ৳500 off on
 * the phone"), kept separate from coupon_discount_amount/coupon_id —
 * coupons are self-service codes tied to a real Coupon record, this is
 * a one-off adjustment on a single order with no code at all. Both can
 * coexist and each still means exactly what it already meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('manual_discount_type')->nullable()->after('coupon_free_shipping');
            $table->decimal('manual_discount_value', 12, 2)->nullable()->after('manual_discount_type');
            $table->decimal('manual_discount_amount', 12, 2)->default(0)->after('manual_discount_value');
            $table->text('manual_discount_reason')->nullable()->after('manual_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['manual_discount_type', 'manual_discount_value', 'manual_discount_amount', 'manual_discount_reason']);
        });
    }
};
