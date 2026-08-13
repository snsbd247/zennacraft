<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Settings\Services\SettingService;

/**
 * COD is the default payment method; bKash / Nagad / Card are slots that
 * stay hidden until the owner enables one in Studio -> Settings -> Payment
 * gateways — this service is the single place both the Settings page and
 * the storefront checkout page ask "which gateways are enabled" so they
 * can never disagree. bKash is fully wired (see
 * App\Modules\Checkout\Services\Payment\BkashPaymentClient and
 * docs/courier-payment-providers.md); Nagad / Card remain reveal-slots
 * only — enabling them shows the option at checkout but nothing processes
 * the payment yet.
 */
class PaymentGatewayService
{
    public const GATEWAYS = [
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'card' => 'Card / SSLCommerz',
    ];

    public function __construct(private SettingService $settingService) {}

    public function isEnabled(string $gateway): bool
    {
        if (! array_key_exists($gateway, self::GATEWAYS)) {
            return false;
        }

        return filter_var($this->settingService->get('payment', "{$gateway}_enabled", false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, string> gateway key => label, enabled only
     */
    public function enabledGateways(): array
    {
        return collect(self::GATEWAYS)
            ->filter(fn (string $label, string $gateway): bool => $this->isEnabled($gateway))
            ->all();
    }

    public function isValidMethod(string $method): bool
    {
        return $method === 'cod' || $this->isEnabled($method);
    }
}
