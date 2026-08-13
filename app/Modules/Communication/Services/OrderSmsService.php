<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Services\Sms\SmsDriverManager;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SettingService;
use Throwable;

/**
 * Sends the customer an order-confirmation SMS (with the order number/invoice)
 * the moment an order is submitted, when it's switched on in Studio →
 * Setting & Configuration → SMS Gateway → Order confirmation SMS. Delivery runs
 * through the shared, tested SmsDriverManager (Alpha / MiM / log providers), so
 * there's one place that actually talks to the SMS API.
 */
class OrderSmsService
{
    public function __construct(private SettingService $settings, private SmsDriverManager $driverManager) {}

    public function enabled(): bool
    {
        return filter_var($this->settings->get('sms', 'order_confirm_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Best-effort — never throws, so checkout is never blocked by SMS. */
    public function sendConfirmation(Order $order): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $template = (string) ($this->settings->get('sms', 'order_confirm_template')
            ?: 'Dear {name}, your order {order} (Tk {total}) has been received. Thank you for shopping with {store}.');

        $store = (string) $this->settings->get('theme', 'brand_name', config('app.name', 'Zenna Craft'));
        $message = strtr($template, [
            '{name}' => (string) $order->customer_name,
            '{order}' => (string) $order->order_number,
            '{total}' => number_format((float) $order->total),
            '{store}' => $store,
        ]);

        try {
            $this->driverManager->driver()->send((string) $order->customer_phone, $message);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
