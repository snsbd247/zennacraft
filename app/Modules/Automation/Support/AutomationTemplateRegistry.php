<?php

namespace App\Modules\Automation\Support;

class AutomationTemplateRegistry
{
    public static function all(): array
    {
        return [
            [
                'name' => 'Order Created Verification Kickoff',
                'category' => 'Order',
                'trigger_key' => 'order.created',
                'description' => 'Queue a customer order received message and create a verification follow-up task.',
                'conditions_json' => [
                    ['key' => 'always'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'order_created', 'channel' => 'sms'],
                    ['key' => 'create_followup_task', 'task' => 'verification_queue'],
                ],
            ],
            [
                'name' => 'Order Verified Confirmation',
                'category' => 'Verification',
                'trigger_key' => 'order.status_changed',
                'description' => 'Queue confirmation after an order moves to the confirmed status.',
                'conditions_json' => [
                    ['key' => 'order_status_is', 'status' => 'confirmed'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'order_confirmed', 'channel' => 'sms'],
                ],
            ],
            [
                'name' => 'Order Delivered Review Request',
                'category' => 'Customer Lifecycle',
                'trigger_key' => 'order.status_changed',
                'description' => 'Queue a post-delivery review request when an order is delivered.',
                'conditions_json' => [
                    ['key' => 'order_status_is', 'status' => 'delivered'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'review_request', 'channel' => 'sms', 'delay_minutes' => 60],
                ],
            ],
            [
                'name' => 'Review Approved Thank You',
                'category' => 'Trust',
                'trigger_key' => 'review.approved',
                'description' => 'Queue a thank-you message after Studio approves a real customer review.',
                'conditions_json' => [
                    ['key' => 'always'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'review_thank_you', 'channel' => 'sms'],
                ],
            ],
            [
                'name' => 'VIP Loyalty Message',
                'category' => 'Customer Lifecycle',
                'trigger_key' => 'customer.segment_entered',
                'description' => 'Future-ready loyalty message when a customer enters the VIP segment.',
                'conditions_json' => [
                    ['key' => 'customer_in_segment', 'segment_slug' => 'vip_customer'],
                    ['key' => 'customer_not_blacklisted'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'vip_loyalty_message', 'channel' => 'sms'],
                ],
            ],
            [
                'name' => 'Abandoned Cart Recovery Message',
                'category' => 'Recovery',
                'trigger_key' => 'checkout.abandoned',
                'description' => 'Queue a recovery reminder for open checkout recovery records.',
                'conditions_json' => [
                    ['key' => 'customer_not_blacklisted'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'abandoned_checkout', 'channel' => 'sms', 'delay_minutes' => 15],
                ],
            ],
            [
                'name' => 'Incomplete Checkout Callback Task',
                'category' => 'Recovery',
                'trigger_key' => 'checkout.abandoned',
                'description' => 'Create an internal callback task for checkout recoveries.',
                'conditions_json' => [
                    ['key' => 'customer_not_blacklisted'],
                ],
                'actions_json' => [
                    ['key' => 'create_followup_task', 'task' => 'callback_queue', 'delay_minutes' => 30],
                ],
            ],
            [
                'name' => 'Callback Failed Follow-up Reminder',
                'category' => 'Recovery',
                'trigger_key' => 'checkout.abandoned',
                'description' => 'Future-ready follow-up template for no-answer callback workflows.',
                'conditions_json' => [
                    ['key' => 'customer_not_blacklisted'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'recovery_reminder', 'channel' => 'sms', 'delay_minutes' => 120],
                ],
            ],
            [
                'name' => 'Courier Assigned Tracking Message',
                'category' => 'Courier',
                'trigger_key' => 'order.status_changed',
                'description' => 'Queue tracking details when an order moves to shipped.',
                'conditions_json' => [
                    ['key' => 'order_status_is', 'status' => 'shipped'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'order_shipped', 'channel' => 'sms'],
                ],
            ],
            [
                'name' => 'Dormant Customer Winback',
                'category' => 'Marketing',
                'trigger_key' => 'customer.segment_entered',
                'description' => 'Queue a winback message when a customer enters the dormant segment.',
                'conditions_json' => [
                    ['key' => 'customer_in_segment', 'segment_slug' => 'dormant_customer'],
                    ['key' => 'customer_not_blacklisted'],
                ],
                'actions_json' => [
                    ['key' => 'queue_communication', 'template' => 'recovery_reminder', 'channel' => 'sms'],
                ],
            ],
        ];
    }
}
