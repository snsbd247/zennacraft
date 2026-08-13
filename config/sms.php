<?php

use App\Modules\Communication\Services\Sms\Drivers\AlphaSmsDriver;
use App\Modules\Communication\Services\Sms\Drivers\BdBulkSmsDriver;
use App\Modules\Communication\Services\Sms\Drivers\LogSmsDriver;
use App\Modules\Communication\Services\Sms\Drivers\MimSmsDriver;

return [
    // Switching providers is an env change only — no code change.
    // 'log' (default, safe no-op) | 'alpha' (Alpha SMS) | 'mim' (MiMSMS)
    // | 'bdbulk' (BD Bulk SMS). The live provider is normally chosen in
    // Studio -> Settings -> SMS Gateway, which overrides this default.
    'driver' => env('SMS_DRIVER', 'log'),

    // Shared across drivers that support a masked/branded sender id.
    // Masking generally requires provider-side business/trade-license
    // approval; leave SMS_MASKING_ENABLED=false to send from the
    // account's default/non-masked sender instead.
    'sender_id' => env('SMS_SENDER_ID'),
    'masking_enabled' => (bool) env('SMS_MASKING_ENABLED', false),

    'drivers' => [
        'log' => [
            'class' => LogSmsDriver::class,
        ],

        // Alpha SMS — https://www.alpha.net.bd/SMS/api/ (see docs/sms-providers.md)
        'alpha' => [
            'class' => AlphaSmsDriver::class,
            'api_key' => env('SMS_API_KEY'),
            'base_url' => env('SMS_ALPHA_BASE_URL', 'https://api.sms.net.bd/sendsms'),
        ],

        // MiMSMS — https://www.mimsms.com/ (see docs/sms-providers.md)
        // MiMSMS authenticates with a username *and* an api key.
        'mim' => [
            'class' => MimSmsDriver::class,
            'username' => env('SMS_MIM_USERNAME'),
            'api_key' => env('SMS_API_KEY'),
            'base_url' => env('SMS_MIM_BASE_URL', 'https://api.mimsms.com/api/SmsSending'),
        ],

        // BD Bulk SMS — https://bdbulksms.com/bd-bulk-sms-api.php
        // Authenticates with a single API token (stored in the shared
        // sms.api_key setting). Paste the token into the "API Key" field.
        'bdbulk' => [
            'class' => BdBulkSmsDriver::class,
            'token' => env('SMS_API_KEY'),
            'base_url' => env('SMS_BDBULK_BASE_URL', 'https://api.bdbulksms.net/api.php'),
        ],
    ],
];
