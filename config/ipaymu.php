<?php

return [
    // sandbox | production
    'env' => env('IPAYMU_ENV', 'sandbox'),

    // https://sandbox.ipaymu.com (sandbox) or https://my.ipaymu.com (production)
    'base_url' => rtrim(
        env('IPAYMU_BASE_URL') ?: (env('IPAYMU_ENV', 'sandbox') === 'production'
            ? 'https://my.ipaymu.com'
            : 'https://sandbox.ipaymu.com'),
        '/'
    ),

    // Credentials differ between sandbox and production. Never commit real values.
    'va' => env('IPAYMU_VA'),
    'api_key' => env('IPAYMU_API_KEY'),

    // Optional overrides. Locally, point IPAYMU_CALLBACK_URL at a public tunnel (ngrok, cloudflared).
    'callback_url' => env('IPAYMU_CALLBACK_URL'),
    'return_url' => env('IPAYMU_RETURN_URL'),
    'cancel_url' => env('IPAYMU_CANCEL_URL'),

    // MERCHANT keeps the callback `amount` equal to the order total, which the verification relies on.
    'fee_direction' => env('IPAYMU_FEE_DIRECTION', 'MERCHANT'),

    'timeout' => 20,
];
