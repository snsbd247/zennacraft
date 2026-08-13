<?php

namespace App\Modules\Marketing\Support;

class MarketingCampaignRegistry
{
    public static function all(): array
    {
        return [
            'coupon_campaign' => [
                'key' => 'coupon_campaign',
                'label' => 'Coupon Campaign',
                'description' => 'Promotes an eligible coupon to selected customers.',
            ],
            'vip_campaign' => [
                'key' => 'vip_campaign',
                'label' => 'VIP Campaign',
                'description' => 'Targets high-value or VIP customers with a loyalty message.',
            ],
            'winback_campaign' => [
                'key' => 'winback_campaign',
                'label' => 'Winback Campaign',
                'description' => 'Targets inactive, dormant, or lost customers.',
            ],
            'announcement_campaign' => [
                'key' => 'announcement_campaign',
                'label' => 'Announcement Campaign',
                'description' => 'Queues a general store announcement.',
            ],
            'product_campaign' => [
                'key' => 'product_campaign',
                'label' => 'Product Promotion Campaign',
                'description' => 'Promotes products to selected audiences.',
            ],
            'abandoned_checkout_campaign' => [
                'key' => 'abandoned_checkout_campaign',
                'label' => 'Abandoned Checkout Campaign',
                'description' => 'Targets customers with open checkout recovery opportunities.',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
