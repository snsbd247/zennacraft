<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTOs\RenderedTemplate;

class TemplateEngineService
{
    public function render(string $template, array $variables = []): RenderedTemplate
    {
        $definition = $this->templates()[$template] ?? [
            'subject' => $template,
            'body' => '{{ message }}',
        ];

        return new RenderedTemplate(
            $template,
            $this->replaceVariables($definition['subject'], $variables),
            $this->replaceVariables($definition['body'], $variables),
            $this->safeVariables($variables),
        );
    }

    public function templates(): array
    {
        return [
            'order_created' => [
                'subject' => 'Order {{ order_number }} received',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} has been received.',
            ],
            'order_pending' => [
                'subject' => 'Order {{ order_number }} is pending',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} is pending confirmation.',
            ],
            'order_confirmed' => [
                'subject' => 'Order {{ order_number }} confirmed',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} has been confirmed.',
            ],
            'order_processing' => [
                'subject' => 'Order {{ order_number }} is processing',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} is being prepared.',
            ],
            'order_shipped' => [
                'subject' => 'Order {{ order_number }} shipped',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} has shipped. Tracking: {{ tracking_number }}.',
            ],
            'order_delivered' => [
                'subject' => 'Order {{ order_number }} delivered',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} has been delivered.',
            ],
            'order_cancelled' => [
                'subject' => 'Order {{ order_number }} cancelled',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} has been cancelled.',
            ],
            'order_returned' => [
                'subject' => 'Order {{ order_number }} returned',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} has been returned.',
            ],
            'otp_verification' => [
                'subject' => 'OTP verification',
                'body' => 'Use your verification code to continue. This template does not store OTP values in audit metadata.',
            ],
            'customer_otp' => [
                'subject' => 'Your Zenna Craft verification code',
                'body' => 'Your Zenna Craft verification code is {{ otp_code }}. It expires in 10 minutes. Do not share this code.',
            ],
            'coupon_campaign' => [
                'subject' => '{{ coupon_code }} offer from Zenna Craft',
                'body' => 'Hi {{ customer_name }}, {{ coupon_code }} is available for eligible orders.',
            ],
            'abandoned_checkout' => [
                'subject' => 'Checkout reminder',
                'body' => 'Hi {{ customer_name }}, your selected item is still waiting in checkout.',
            ],
            'recovery_reminder' => [
                'subject' => 'Complete your order',
                'body' => 'Hi {{ customer_name }}, complete your checkout when you are ready.',
            ],
            'review_request' => [
                'subject' => 'How was your Zenna Craft order?',
                'body' => 'Hi {{ customer_name }}, your order {{ order_number }} was delivered. Share an honest review from your account when you have a moment.',
            ],
            'review_thank_you' => [
                'subject' => 'Thank you for your review',
                'body' => 'Hi {{ customer_name }}, thank you for reviewing {{ product_name }}. Your feedback helps future Zenna Craft customers.',
            ],
            'vip_loyalty_message' => [
                'subject' => 'A note for our VIP customer',
                'body' => 'Hi {{ customer_name }}, thank you for being one of Zenna Craft’s most valued customers.',
            ],
        ];
    }

    protected function replaceVariables(string $content, array $variables): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use ($variables): string {
            $key = $matches[1];

            return (string) ($variables[$key] ?? '');
        }, $content) ?? $content;
    }

    protected function safeVariables(array $variables): array
    {
        $safe = [];

        foreach ($variables as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (str_contains($lowerKey, 'otp') || str_contains($lowerKey, 'token')) {
                $safe[$key] = '[redacted]';

                continue;
            }

            $safe[$key] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
        }

        return $safe;
    }
}
