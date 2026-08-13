<?php

namespace App\Modules\Marketing\Support;

use App\Modules\Marketing\Models\MarketingSegment;

class SystemMarketingSegments
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'New Customers',
                'slug' => 'NEW_CUSTOMER',
                'description' => 'Customers with zero delivered orders.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['delivered_orders' => 0],
            ],
            [
                'name' => 'Repeat Customers',
                'slug' => 'REPEAT_CUSTOMER',
                'description' => 'Customers with two or more delivered orders.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['delivered_orders' => ['>=', 2]],
            ],
            [
                'name' => 'VIP Customers',
                'slug' => 'VIP_CUSTOMER',
                'description' => 'Customers with VIP snapshot status, five or more delivered orders, or VIP lifetime delivered revenue.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['segment' => 'VIP', 'delivered_orders' => ['>=', 5], 'lifetime_revenue' => ['>=', 'configured_vip_threshold']],
            ],
            [
                'name' => 'High Value Customers',
                'slug' => 'HIGH_VALUE_CUSTOMER',
                'description' => 'Customers whose delivered lifetime revenue reaches the configured high value threshold.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['lifetime_revenue' => ['>=', 'configured_high_value_threshold']],
            ],
            [
                'name' => 'Dormant Customers',
                'slug' => 'DORMANT_CUSTOMER',
                'description' => 'Customers with at least one delivered order and no orders in the last 90 days.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['delivered_orders' => ['>=', 1], 'last_order_days' => ['>', 90]],
            ],
            [
                'name' => 'At Risk Customers',
                'slug' => 'AT_RISK_CUSTOMER',
                'description' => 'Customers with high RTO risk, at-risk retention, or at-risk lifecycle status.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['risk_level' => 'high', 'retention_or_lifecycle' => 'at_risk'],
            ],
            [
                'name' => 'Blacklisted Customers',
                'slug' => 'BLACKLISTED_CUSTOMER',
                'description' => 'Customers with an active blacklist entry by customer ID or normalized phone.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['active_blacklist' => true],
            ],
            [
                'name' => 'Fraud Risk Customers',
                'slug' => 'FRAUD_RISK_CUSTOMER',
                'description' => 'Customers with high or critical fraud profile signals or recent high/critical fraud events.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['fraud_level' => ['high', 'critical']],
            ],
            [
                'name' => 'Coupon Lovers',
                'slug' => 'COUPON_LOVER',
                'description' => 'Customers with two or more coupon usages.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['coupon_usage_count' => ['>=', 2]],
            ],
            [
                'name' => 'Abandoned Checkout Customers',
                'slug' => 'ABANDONED_CHECKOUT_CUSTOMER',
                'description' => 'Customers with at least one open checkout recovery.',
                'type' => MarketingSegment::TYPE_SYSTEM,
                'active' => true,
                'rules_json' => ['open_checkout_recoveries' => ['>=', 1]],
            ],
        ];
    }
}
