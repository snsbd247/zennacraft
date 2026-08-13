<?php

namespace App\Modules\Promotion\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CouponService
{
    public function __construct(
        private AuditService $auditService,
        private PhoneService $phoneService,
        private MarketingSegmentEngineService $marketingSegmentEngine,
    ) {}

    public function activeCoupons(): Builder
    {
        return Coupon::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()
            ->with('targets')
            ->where('code', $this->normalizeCode($code))
            ->first();
    }

    public function validateCoupon(string $code, array $cartData, ?Customer $customer = null): array
    {
        $coupon = $this->findByCode($code);

        if (! $coupon) {
            return $this->invalidResult('Coupon code is invalid.', $cartData);
        }

        if ($coupon->status !== 'active') {
            return $this->invalidResult('Coupon is not active.', $cartData, $coupon);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return $this->invalidResult('Coupon is not active yet.', $cartData, $coupon);
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return $this->invalidResult('Coupon has expired.', $cartData, $coupon);
        }

        if ($this->subtotal($cartData) < (float) $coupon->min_order_amount) {
            return $this->invalidResult('Minimum order amount is not satisfied.', $cartData, $coupon);
        }

        if ($coupon->usage_limit && $coupon->usages()->count() >= (int) $coupon->usage_limit) {
            return $this->invalidResult('Coupon usage limit has been reached.', $cartData, $coupon);
        }

        if ($coupon->usage_limit_per_customer) {
            $phone = (string) ($cartData['customer_phone'] ?? $customer?->phone ?? '');
            $customerUsageCount = $this->customerUsageCount($coupon, $customer, $phone);

            if ($customerUsageCount >= (int) $coupon->usage_limit_per_customer) {
                return $this->invalidResult('Coupon has already been used by this customer.', $cartData, $coupon);
            }
        }

        if (! $this->matchesTargets($coupon, $cartData)) {
            return $this->invalidResult('Coupon is not valid for the selected item.', $cartData, $coupon);
        }

        return $this->calculateDiscount($coupon, $cartData, $customer);
    }

    public function calculateDiscount(Coupon $coupon, array $cartData, ?Customer $customer = null): array
    {
        $subtotal = $this->subtotal($cartData);
        $deliveryFee = $this->deliveryFee($cartData);
        $totalBeforeDiscount = $subtotal + $deliveryFee;
        $discountAmount = 0.0;
        $freeShipping = false;
        $payableDeliveryFee = $deliveryFee;

        if ($coupon->discount_type === 'free_shipping') {
            $freeShipping = true;
            $discountAmount = min($deliveryFee, $totalBeforeDiscount);
            $payableDeliveryFee = 0.0;
        } elseif ($coupon->discount_type === 'fixed') {
            $discountAmount = min((float) $coupon->discount_value, $subtotal);
        } elseif ($coupon->discount_type === 'percentage') {
            $discountAmount = $subtotal * ((float) $coupon->discount_value / 100);

            if ($coupon->max_discount_amount !== null) {
                $discountAmount = min($discountAmount, (float) $coupon->max_discount_amount);
            }

            $discountAmount = min($discountAmount, $subtotal);
        }

        $discountAmount = round(max(0, $discountAmount), 2);
        $totalAfterDiscount = max(0, ($subtotal + $payableDeliveryFee) - ($freeShipping ? 0 : $discountAmount));

        return [
            'valid' => true,
            'message' => 'Coupon applied successfully.',
            'coupon' => $coupon,
            'discount_amount' => $discountAmount,
            'free_shipping' => $freeShipping,
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round($payableDeliveryFee, 2),
            'total_before_discount' => round($totalBeforeDiscount, 2),
            'total_after_discount' => round($totalAfterDiscount, 2),
        ];
    }

    public function applyToCheckout(string $code, array $cartData, ?Customer $customer = null): array
    {
        return $this->validateCoupon($code, $cartData, $customer);
    }

    /**
     * validateCoupon() already checked the usage limits, but that check runs
     * outside any transaction as a cheap, fast-fail UX pre-check — it reads
     * an unlocked count, so two concurrent checkouts on a near-cap coupon can
     * both pass it before either has committed a usage row. This is the
     * authoritative, race-safe check: lock the coupon row and re-verify both
     * limits immediately before writing the usage record, inside a
     * transaction, so a second concurrent caller blocks here until the first
     * commits and then sees its usage counted. Same lock-then-recheck shape
     * as CourierService::assignShipment().
     */
    public function recordUsage(Coupon $coupon, Order $order, float $discountAmount): CouponUsage
    {
        $usage = DB::transaction(function () use ($coupon, $order, $discountAmount) {
            $lockedCoupon = Coupon::where('id', $coupon->id)->lockForUpdate()->first();

            if (! $lockedCoupon) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'Coupon is no longer available.',
                ]);
            }

            if ($lockedCoupon->usage_limit && $lockedCoupon->usages()->count() >= (int) $lockedCoupon->usage_limit) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'Coupon usage limit has been reached.',
                ]);
            }

            if ($lockedCoupon->usage_limit_per_customer) {
                $phone = $this->phoneService->normalize((string) $order->customer_phone);
                $customerUsageCount = $this->customerUsageCount($lockedCoupon, $order->customer, $phone);

                if ($customerUsageCount >= (int) $lockedCoupon->usage_limit_per_customer) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Coupon has already been used by this customer.',
                    ]);
                }
            }

            return CouponUsage::updateOrCreate(
                [
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                ],
                [
                    'customer_id' => $order->customer_id,
                    'code' => $coupon->code,
                    'discount_amount' => $discountAmount,
                    'used_at' => now(),
                ]
            );
        });

        $this->logAudit('promotion.coupon.usage', [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
        ]);

        $this->evaluateMarketingSegments($order->customer, 'coupon_usage');
        $this->triggerAutomation($usage);

        return $usage;
    }

    public function usageStats(Coupon $coupon): array
    {
        $orderIds = $coupon->usages()
            ->whereNotNull('order_id')
            ->pluck('order_id');

        $orders = Order::query()->whereIn('id', $orderIds);

        return [
            'total_usage' => (int) $coupon->usages()->count(),
            'total_discount_given' => (float) $coupon->usages()->sum('discount_amount'),
            'total_revenue' => (float) (clone $orders)->sum('total'),
            'delivered_revenue' => (float) (clone $orders)->where('status', 'delivered')->sum('total'),
            'gross_profit' => (float) (clone $orders)->where('status', 'delivered')->sum('gross_profit'),
        ];
    }

    public function couponAnalytics(): array
    {
        return [
            'total_coupons' => Coupon::count(),
            'active_coupons' => Coupon::where('status', 'active')->count(),
            'total_usages' => CouponUsage::count(),
            'total_discount_given' => (float) CouponUsage::sum('discount_amount'),
        ];
    }

    public function defaultResult(array $cartData): array
    {
        $subtotal = $this->subtotal($cartData);
        $deliveryFee = $this->deliveryFee($cartData);
        $total = $subtotal + $deliveryFee;

        return [
            'valid' => true,
            'message' => '',
            'coupon' => null,
            'discount_amount' => 0.0,
            'free_shipping' => false,
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'total_before_discount' => round($total, 2),
            'total_after_discount' => round($total, 2),
        ];
    }

    protected function invalidResult(string $message, array $cartData, ?Coupon $coupon = null): array
    {
        $result = $this->defaultResult($cartData);
        $result['valid'] = false;
        $result['message'] = $message;
        $result['coupon'] = $coupon;

        return $result;
    }

    protected function matchesTargets(Coupon $coupon, array $cartData): bool
    {
        if ($coupon->applies_to === 'all') {
            return true;
        }

        $targetIds = $coupon->targets
            ->where('target_type', $coupon->applies_to)
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($targetIds === []) {
            return false;
        }

        return $this->items($cartData)->contains(function (array $item) use ($coupon, $targetIds): bool {
            $itemTargetId = match ($coupon->applies_to) {
                'product' => $item['product_id'] ?? null,
                'category' => $item['category_id'] ?? null,
                'landing_page' => $item['landing_page_id'] ?? null,
                default => null,
            };

            return $itemTargetId && in_array((int) $itemTargetId, $targetIds, true);
        });
    }

    protected function subtotal(array $cartData): float
    {
        return (float) ($cartData['subtotal'] ?? $this->items($cartData)->sum(fn (array $item) => (float) ($item['subtotal'] ?? 0)));
    }

    protected function deliveryFee(array $cartData): float
    {
        return (float) ($cartData['delivery_fee'] ?? 0);
    }

    protected function items(array $cartData): Collection
    {
        return collect($cartData['items'] ?? []);
    }

    protected function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    protected function customerUsageCount(Coupon $coupon, ?Customer $customer, string $phone): int
    {
        $normalizedPhone = $this->phoneService->normalize($phone);

        if (! $customer && $normalizedPhone === '') {
            return 0;
        }

        return $coupon->usages()
            ->where(function (Builder $query) use ($customer, $normalizedPhone): void {
                if ($customer) {
                    $query->where('customer_id', $customer->id);
                }

                if ($normalizedPhone !== '') {
                    $query->orWhereHas('order', function (Builder $orderQuery) use ($normalizedPhone): void {
                        $this->phoneService->whereNormalizedPhone($orderQuery, 'customer_phone', $normalizedPhone);
                    });
                }
            })
            ->count();
    }

    protected function logAudit(string $action, array $metadata = []): void
    {
        try {
            $this->auditService->log(
                'promotion',
                $action,
                'promotion',
                'Promotion coupon event recorded.',
                $metadata
            );
        } catch (Throwable $exception) {
            logger()->warning('Promotion audit logging failed', [
                'action' => $action,
                'metadata_keys' => array_keys($metadata),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function evaluateMarketingSegments(?Customer $customer, string $trigger): void
    {
        if (! $customer) {
            return;
        }

        try {
            $this->marketingSegmentEngine->evaluateCustomer($customer, $trigger);
        } catch (Throwable $exception) {
            logger()->warning('Marketing segment coupon evaluation failed', [
                'customer_id' => $customer->id,
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function triggerAutomation(CouponUsage $usage): void
    {
        try {
            app(AutomationEngineService::class)->handleTrigger('coupon.used', $usage->loadMissing(['customer', 'order']), [
                'coupon_id' => $usage->coupon_id,
                'order_id' => $usage->order_id,
                'customer_id' => $usage->customer_id,
                'coupon_code' => $usage->code,
            ]);
        } catch (Throwable $exception) {
            logger()->warning('Coupon automation trigger failed', [
                'coupon_usage_id' => $usage->id,
                'coupon_id' => $usage->coupon_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
