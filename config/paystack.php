<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway driver
    |--------------------------------------------------------------------------
    |
    | 'fake'     — never calls Paystack; transactions are recorded locally and webhooks
    |              are delivered with `php artisan poachy:simulate-paystack` (default).
    | 'paystack' — the real Paystack API (needs the keys below).
    |
    */
    'driver' => env('PAYSTACK_DRIVER', 'fake'),

    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),

    // Never commit real keys. The secret key also signs webhooks (HMAC SHA-512).
    'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
    'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),

    'currency' => env('PAYSTACK_CURRENCY', 'KES'),

    // Where Paystack sends the buyer after paying, and where it posts webhooks.
    'callback_url' => env('PAYSTACK_CALLBACK_URL', ''),
    'webhook_url' => env('PAYSTACK_WEBHOOK_URL', ''),

];
