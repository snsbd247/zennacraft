<?php

namespace Tests\Feature\Promotion;

use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Promotion\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * validateCoupon()'s usage-limit check runs outside any transaction as a
 * cheap pre-check (correct — it gives fast UX feedback and doesn't need to
 * be authoritative). Two concurrent checkouts on a near-cap coupon can both
 * read the same "still under limit" count before either commits a usage
 * row, both pass that pre-check, and both then try to record usage. These
 * tests bypass the pre-check and call CouponService::recordUsage() directly
 * for a second order — exactly what a "concurrent" request that already
 * passed validation would do — to prove the write path itself is now the
 * authoritative, race-safe check.
 */
class CouponConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(string $phone, ?int $customerId = null): Order
    {
        return Order::create([
            'customer_id' => $customerId,
            'order_number' => 'ZC-CONC-'.uniqid(),
            'customer_name' => 'Concurrency Test Customer',
            'customer_phone' => $phone,
            'address' => '123 Test Road, Dhaka',
            'subtotal' => 1000,
            'delivery_fee' => 0,
            'total' => 900,
            'status' => 'pending',
        ]);
    }

    public function test_concurrent_checkouts_cannot_exceed_coupon_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'RACE10',
            'name' => 'Race condition test coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'usage_limit' => 1,
            'status' => 'active',
            'applies_to' => 'all',
        ]);

        $couponService = app(CouponService::class);

        $orderA = $this->makeOrder('01712340001');
        $orderB = $this->makeOrder('01712340002');

        // Order A: the "winning" concurrent request. Fills the one available slot.
        $couponService->recordUsage($coupon, $orderA, 100.0);

        // Order B: simulates a second request that already passed
        // validateCoupon()'s unlocked pre-check (both read count=0 before
        // either committed) and is now, "concurrently", trying to record
        // its own usage. Before the fix, recordUsage() never re-checked
        // the limit at write time and this would have silently succeeded,
        // producing 2 usage rows against a usage_limit of 1.
        $this->expectException(ValidationException::class);

        try {
            $couponService->recordUsage($coupon, $orderB, 100.0);
        } finally {
            $this->assertSame(
                1,
                CouponUsage::where('coupon_id', $coupon->id)->count(),
                'Usage limit of 1 must never be exceeded, regardless of how many callers already passed the pre-check.'
            );
        }
    }

    public function test_concurrent_checkouts_cannot_exceed_per_customer_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'RACEPERCUST',
            'name' => 'Per-customer race condition test coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'usage_limit_per_customer' => 1,
            'status' => 'active',
            'applies_to' => 'all',
        ]);

        $couponService = app(CouponService::class);

        $phone = '01712340003';
        $orderA = $this->makeOrder($phone);
        $orderB = $this->makeOrder($phone);

        $couponService->recordUsage($coupon, $orderA, 100.0);

        $this->expectException(ValidationException::class);

        try {
            $couponService->recordUsage($coupon, $orderB, 100.0);
        } finally {
            $this->assertSame(
                1,
                CouponUsage::where('coupon_id', $coupon->id)->count(),
                'Per-customer usage limit of 1 must never be exceeded for the same phone number.'
            );
        }
    }
}
