<?php

namespace App\Modules\Automation\Support;

class AutomationConditionRegistry
{
    public static function all(): array
    {
        return [
            'always' => [
                'key' => 'always',
                'label' => 'Always',
                'description' => 'Always passes.',
            ],
            'customer_in_segment' => [
                'key' => 'customer_in_segment',
                'label' => 'Customer In Segment',
                'description' => 'Passes when the customer belongs to the selected marketing segment.',
            ],
            'customer_not_in_segment' => [
                'key' => 'customer_not_in_segment',
                'label' => 'Customer Not In Segment',
                'description' => 'Passes when the customer does not belong to the selected marketing segment.',
            ],
            'customer_not_blacklisted' => [
                'key' => 'customer_not_blacklisted',
                'label' => 'Customer Not Blacklisted',
                'description' => 'Passes when no active blacklist record exists for the customer.',
            ],
            'order_status_is' => [
                'key' => 'order_status_is',
                'label' => 'Order Status Is',
                'description' => 'Passes when the order has the selected status.',
            ],
            'order_total_greater_than' => [
                'key' => 'order_total_greater_than',
                'label' => 'Order Total Greater Than',
                'description' => 'Passes when order total is greater than the configured amount.',
            ],
            'fraud_level_is' => [
                'key' => 'fraud_level_is',
                'label' => 'Fraud Level Is',
                'description' => 'Passes when the fraud severity matches.',
            ],
            'coupon_code_is' => [
                'key' => 'coupon_code_is',
                'label' => 'Coupon Code Is',
                'description' => 'Passes when the coupon code matches.',
            ],
            'customer_intent_is' => [
                'key' => 'customer_intent_is',
                'label' => 'Customer Intent Is',
                'description' => 'Passes when the rule-based customer intent label is hot, warm, or cold.',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
