<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Settings\Services\SettingService;

/**
 * Delivery charge is a COD business's shipping-cost pricing, not a
 * profit-scope concern — kept deliberately separate from FinanceService.
 * Every tier amount and the master free-delivery override live in the
 * settings table (group 'delivery') so the owner can change them from
 * Studio without a deploy, per spec §6.12.
 */
class DeliveryChargeService
{
    public const ZONE_INSIDE_DHAKA = 'inside_dhaka';

    public const ZONE_SUBURBAN = 'suburban';

    public const ZONE_OUTSIDE_DHAKA = 'outside_dhaka';

    public const ZONES = [
        self::ZONE_INSIDE_DHAKA => 'Inside Dhaka',
        self::ZONE_SUBURBAN => 'Sub-urban',
        self::ZONE_OUTSIDE_DHAKA => 'Outside Dhaka',
    ];

    public function __construct(private SettingService $settingService) {}

    public function zones(): array
    {
        return self::ZONES;
    }

    public function tiers(): array
    {
        return [
            self::ZONE_INSIDE_DHAKA => $this->tierAmount(self::ZONE_INSIDE_DHAKA),
            self::ZONE_SUBURBAN => $this->tierAmount(self::ZONE_SUBURBAN),
            self::ZONE_OUTSIDE_DHAKA => $this->tierAmount(self::ZONE_OUTSIDE_DHAKA),
        ];
    }

    public function freeDeliveryThreshold(): float
    {
        return (float) $this->settingService->get('delivery', 'free_delivery_threshold', 5000);
    }

    public function freeDeliveryForAllOrders(): bool
    {
        return filter_var($this->settingService->get('delivery', 'free_delivery_all_orders', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The owner decides per case whether to charge or give free delivery:
     * a zone amount of 0 makes that zone free, and the master toggle
     * overrides every zone to free regardless of amount. Both checks
     * happen here so every caller (cart preview, checkout preview, real
     * order creation) shares one answer.
     */
    public function feeFor(?string $zone, float $subtotal = 0.0, bool $hasFreeDeliveryItem = false): float
    {
        // A single "Free Delivery Product" in the cart makes the whole order
        // ship free (Campaign/Offer > Free Delivery Products).
        if ($hasFreeDeliveryItem || $this->freeDeliveryForAllOrders()) {
            return 0.0;
        }

        $threshold = $this->freeDeliveryThreshold();

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        return $this->tierAmount($zone);
    }

    protected function tierAmount(?string $zone): float
    {
        return match ($zone) {
            self::ZONE_SUBURBAN => (float) $this->settingService->get('delivery', 'suburban_charge', 120),
            self::ZONE_OUTSIDE_DHAKA => (float) $this->settingService->get('delivery', 'outside_dhaka_charge', 150),
            default => (float) $this->settingService->get('delivery', 'inside_dhaka_charge', 0),
        };
    }
}
