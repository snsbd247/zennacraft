<?php

return [
    // bKash Checkout (URL-based), v1.2.0-beta — see docs/courier-payment-providers.md.
    // App key/secret/username/password live in Studio -> Settings -> Payment
    // Gateway (encrypted at rest); this file only holds the two fixed hosts.
    'sandbox_base_url' => env('BKASH_SANDBOX_BASE_URL', 'https://checkout.sandbox.bka.sh/v1.2.0-beta'),
    'live_base_url' => env('BKASH_LIVE_BASE_URL', 'https://checkout.pay.bka.sh/v1.2.0-beta'),
];
