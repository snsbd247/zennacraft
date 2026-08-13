<?php

return [
    'vip_lifetime_value' => (float) env('MARKETING_SEGMENT_VIP_LIFETIME_VALUE', env('CUSTOMER_SEGMENT_VIP_LIFETIME_VALUE', 10000)),
    'high_value_lifetime_value' => (float) env('MARKETING_SEGMENT_HIGH_VALUE_LIFETIME_VALUE', env('CUSTOMER_SEGMENT_VIP_LIFETIME_VALUE', 10000)),
];
