<?php

namespace App\Modules\Automation\Support;

class AutomationTriggerRegistry
{
    public static function all(): array
    {
        return [
            'order.created' => [
                'key' => 'order.created',
                'label' => 'Order Created',
                'description' => 'Runs after a checkout order is created.',
                'subject_type' => 'order',
            ],
            'order.status_changed' => [
                'key' => 'order.status_changed',
                'label' => 'Order Status Changed',
                'description' => 'Runs after staff changes an order status.',
                'subject_type' => 'order',
            ],
            'customer.created' => [
                'key' => 'customer.created',
                'label' => 'Customer Created',
                'description' => 'Runs after a new customer profile is created.',
                'subject_type' => 'customer',
            ],
            'customer.segment_entered' => [
                'key' => 'customer.segment_entered',
                'label' => 'Customer Entered Segment',
                'description' => 'Runs after a customer enters a marketing segment.',
                'subject_type' => 'customer',
            ],
            'customer.segment_left' => [
                'key' => 'customer.segment_left',
                'label' => 'Customer Left Segment',
                'description' => 'Runs after a customer leaves a marketing segment.',
                'subject_type' => 'customer',
            ],
            'coupon.used' => [
                'key' => 'coupon.used',
                'label' => 'Coupon Used',
                'description' => 'Runs after coupon usage is recorded.',
                'subject_type' => 'coupon_usage',
            ],
            'checkout.abandoned' => [
                'key' => 'checkout.abandoned',
                'label' => 'Checkout Abandoned',
                'description' => 'Runs when an open checkout recovery is created or updated.',
                'subject_type' => 'checkout_recovery',
            ],
            'fraud.event.created' => [
                'key' => 'fraud.event.created',
                'label' => 'Fraud Event Created',
                'description' => 'Runs after a fraud event is recorded.',
                'subject_type' => 'fraud_event',
            ],
            'blacklist.added' => [
                'key' => 'blacklist.added',
                'label' => 'Blacklist Added',
                'description' => 'Runs after an active customer blacklist entry is added.',
                'subject_type' => 'blacklist',
            ],
            'review.approved' => [
                'key' => 'review.approved',
                'label' => 'Review Approved',
                'description' => 'Runs after Studio approves a customer product review.',
                'subject_type' => 'product_review',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }
}
