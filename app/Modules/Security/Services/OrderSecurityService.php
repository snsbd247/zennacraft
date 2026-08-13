<?php

namespace App\Modules\Security\Services;

use App\Modules\Customer\Models\Customer;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Fraud\Services\CustomerFraudService;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use App\Modules\Shared\Services\PhoneService;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderSecurityService
{
    protected const BLOCKABLE_EVENT_TYPES = [
        'duplicate_order_blocked',
        'checkout_spam',
        'blacklist_block',
        'coupon_abuse',
        'critical_fraud_reject',
    ];

    public function __construct(
        private SettingService $settingService,
        private CustomerFraudService $customerFraudService,
        private PhoneService $phoneService,
    ) {}

    public function validatePreCheckout(array $data, array $cartData = []): void
    {
        $phone = (string) ($data['phone'] ?? '');

        $this->assertValidPhone($phone);
        $normalizedPhone = $this->normalizePhone($phone);

        $this->assertNotBlacklisted($normalizedPhone);
        $this->assertPhoneCooldown($normalizedPhone);

        if (filled($data['coupon_code'] ?? null)) {
            $this->assertCouponNotLocked($normalizedPhone);
        }

        $this->assertNotRecentDuplicate($normalizedPhone, $cartData);
    }

    public function assertValidPhone(string $phone): void
    {
        if (! $this->phoneService->isValidBangladeshMobile($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid phone number.',
            ]);
        }
    }

    public function assertNotBlacklisted(string $phone): void
    {
        if (! $this->flag('block_blacklisted_checkout', true)) {
            return;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '' || ! $this->isBlacklisted($normalizedPhone)) {
            return;
        }

        $this->recordCheckoutAttempt($normalizedPhone, 'blacklist_block', [
            'severity' => 'critical',
            'score' => 100,
            'reason' => 'Blacklisted phone blocked before checkout.',
            'reason_code' => 'blacklist_block',
        ]);

        throw ValidationException::withMessages([
            'phone' => 'This phone number cannot place orders.',
        ]);
    }

    public function assertNotRecentDuplicate(string $phone, array $cartData): void
    {
        if (! $this->flag('duplicate_order_block_enabled', true)) {
            return;
        }

        $normalizedPhone = $this->normalizePhone($phone);
        $item = collect($cartData['items'] ?? [])->first();

        if ($normalizedPhone === '' || ! is_array($item) || empty($item['product_id'])) {
            return;
        }

        $productId = (int) $item['product_id'];
        $variantId = $item['variant_id'] ?? null;
        $total = round((float) ($cartData['total'] ?? $cartData['total_after_discount'] ?? 0), 2);

        $duplicateExists = Order::query()
            ->where('created_at', '>=', now()->subMinutes(30))
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereBetween('total', [$total - 0.01, $total + 0.01])
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $normalizedPhone))
            ->whereHas('items', function ($query) use ($productId, $variantId): void {
                $query->where('product_id', $productId);

                if ($variantId) {
                    $query->where('variant_id', $variantId);
                } else {
                    $query->whereNull('variant_id');
                }
            })
            ->exists();

        if (! $duplicateExists) {
            return;
        }

        $this->recordCheckoutAttempt($normalizedPhone, 'duplicate_order_blocked', [
            'severity' => 'medium',
            'score' => 35,
            'reason' => 'Duplicate order blocked before creation.',
            'reason_code' => 'duplicate_order',
            'product_id' => $productId,
            'variant_id' => $variantId ? (int) $variantId : null,
        ]);

        throw ValidationException::withMessages([
            'phone' => 'Duplicate order detected. Please wait before placing another order.',
        ]);
    }

    public function assertPhoneCooldown(string $phone): void
    {
        if (! $this->flag('phone_checkout_cooldown_enabled', true)) {
            return;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return;
        }

        $recentOrders = Order::query()
            ->where('created_at', '>=', now()->subMinutes(15))
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'customer_phone', $normalizedPhone))
            ->count();

        $recentEvents = FraudEvent::query()
            ->where('created_at', '>=', now()->subMinutes(15))
            ->whereIn('type', self::BLOCKABLE_EVENT_TYPES)
            ->where('metadata->phone_hash', $this->phoneHash($normalizedPhone))
            ->count();

        $attemptsBeforeCurrent = $recentOrders + $recentEvents;

        if ($attemptsBeforeCurrent < 4) {
            return;
        }

        $this->recordCheckoutAttempt($normalizedPhone, 'checkout_spam', [
            'severity' => 'medium',
            'score' => 25,
            'reason' => 'Too many checkout attempts detected.',
            'reason_code' => 'phone_cooldown',
        ]);

        throw ValidationException::withMessages([
            'phone' => 'Too many checkout attempts. Please try again later.',
        ]);
    }

    public function assertCouponNotLocked(string $phone): void
    {
        if (! $this->flag('coupon_abuse_lock_enabled', true)) {
            return;
        }

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return;
        }

        $invalidCouponAttempts = FraudEvent::query()
            ->where('type', 'coupon_abuse')
            ->where('created_at', '>=', now()->subHour())
            ->where('metadata->phone_hash', $this->phoneHash($normalizedPhone))
            ->count();

        if ($invalidCouponAttempts < 10) {
            return;
        }

        $this->recordCheckoutAttempt($normalizedPhone, 'coupon_abuse', [
            'severity' => 'medium',
            'score' => 30,
            'reason' => 'Coupon abuse lock triggered before checkout.',
            'reason_code' => 'coupon_abuse_lock',
        ]);

        throw ValidationException::withMessages([
            'coupon_code' => 'Too many coupon attempts. Please try again later.',
        ]);
    }

    public function recordCheckoutAttempt(string $phone, string $type, array $metadata = []): void
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return;
        }

        $severity = (string) ($metadata['severity'] ?? 'low');
        $score = (int) ($metadata['score'] ?? 0);
        $reason = (string) ($metadata['reason'] ?? 'Checkout security event recorded.');
        unset($metadata['severity'], $metadata['reason']);

        $metadata = $this->safeMetadata(array_merge($metadata, [
            'phone_hash' => $this->phoneHash($normalizedPhone),
        ]));

        try {
            $this->customerFraudService->recordFraudEvent(
                $this->findCustomerByPhone($normalizedPhone),
                null,
                $type,
                $severity,
                $score,
                $reason,
                $metadata
            );
        } catch (Throwable $exception) {
            logger()->warning('Checkout security event logging failed', [
                'type' => $type,
                'phone_hash' => $this->phoneHash($normalizedPhone),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function normalizePhone(string $phone): string
    {
        return $this->phoneService->normalize($phone);
    }

    public function phoneHash(string $phone): string
    {
        return $this->phoneService->hash($phone);
    }

    protected function flag(string $key, bool $default): bool
    {
        return filter_var(
            $this->settingService->get('general', $key, $default),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    protected function isBlacklisted(string $phone): bool
    {
        return CustomerBlacklist::query()
            ->where('active', true)
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $phone))
            ->exists();
    }

    protected function findCustomerByPhone(string $phone): ?Customer
    {
        return Customer::query()
            ->where(fn ($query) => $this->phoneService->whereNormalizedPhone($query, 'phone', $phone))
            ->first();
    }

    protected function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->except(['phone', 'email', 'address', 'customer_phone', 'customer_email', 'customer_address'])
            ->all();
    }
}
