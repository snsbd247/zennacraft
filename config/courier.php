<?php

use App\Modules\Courier\Services\Api\PathaoCourierClient;
use App\Modules\Courier\Services\Api\SteadfastCourierClient;

return [
    // Studio -> Settings -> Courier API Setup is where credentials are
    // entered (per docs/courier-payment-providers.md); these are just the
    // provider slug -> client class map plus a sane default base URL that
    // a saved Setting value overrides. Adding a new API-backed courier
    // later only means adding one entry here + one client class.
    'drivers' => [
        'pathao' => [
            'class' => PathaoCourierClient::class,
            'base_url' => env('PATHAO_BASE_URL', 'https://api-hermes.pathao.com'),
        ],

        'steadfast' => [
            'class' => SteadfastCourierClient::class,
            'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
        ],
    ],
];
