<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for development/testing and 'production' for live.
    | This gates which credential block is used throughout the application.
    |
    */
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Gateway driver
    |--------------------------------------------------------------------------
    |
    | 'fake'   — never calls Safaricom; requests are recorded locally and callbacks
    |            are delivered with `php artisan poachy:simulate-mpesa` (default).
    | 'daraja' — the real Daraja API (needs the credentials below).
    |
    */
    'driver' => env('MPESA_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Credentials
    |--------------------------------------------------------------------------
    |
    | Obtain these from https://developer.safaricom.co.ke after creating
    | a sandbox app. The shortcode and passkey below are Safaricom's public
    | sandbox defaults and work for testing without a registered app.
    |
    */
    'sandbox' => [
        'base_url' => 'https://sandbox.safaricom.co.ke',
        'consumer_key' => env('MPESA_SANDBOX_CONSUMER_KEY', ''),
        'consumer_secret' => env('MPESA_SANDBOX_CONSUMER_SECRET', ''),
        'shortcode' => env('MPESA_SANDBOX_SHORTCODE', '174379'),
        'passkey' => env('MPESA_SANDBOX_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),
        // B2C payouts and reversals (initiator + encrypted security credential from the Daraja portal)
        'initiator_name' => env('MPESA_SANDBOX_INITIATOR_NAME', ''),
        'security_credential' => env('MPESA_SANDBOX_SECURITY_CREDENTIAL', ''),
        'b2c_shortcode' => env('MPESA_SANDBOX_B2C_SHORTCODE', env('MPESA_SANDBOX_SHORTCODE', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Credentials
    |--------------------------------------------------------------------------
    |
    | These are your live Daraja credentials. Never commit real values here.
    | Always load from environment variables.
    |
    */
    'production' => [
        'base_url' => 'https://api.safaricom.co.ke',
        'consumer_key' => env('MPESA_PRODUCTION_CONSUMER_KEY', ''),
        'consumer_secret' => env('MPESA_PRODUCTION_CONSUMER_SECRET', ''),
        'shortcode' => env('MPESA_PRODUCTION_SHORTCODE', ''),
        'passkey' => env('MPESA_PRODUCTION_PASSKEY', ''),
        // B2C payouts and reversals (initiator + encrypted security credential from the Daraja portal)
        'initiator_name' => env('MPESA_PRODUCTION_INITIATOR_NAME', ''),
        'security_credential' => env('MPESA_PRODUCTION_SECURITY_CREDENTIAL', ''),
        'b2c_shortcode' => env('MPESA_PRODUCTION_B2C_SHORTCODE', env('MPESA_PRODUCTION_SHORTCODE', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback / Webhook URLs
    |--------------------------------------------------------------------------
    |
    | All URLs must be publicly reachable HTTPS endpoints.
    | For local development, use ngrok or Laravel Sail's expose feature.
    |
    */

    // STK Push (M-Pesa Express) — marketplace checkout
    'stk_callback_url' => env('MPESA_STK_CALLBACK_URL', ''),

    // STK Push — subscription payments initiated from the app
    'subscription_stk_callback_url' => env('MPESA_SUBSCRIPTION_STK_CALLBACK_URL', ''),

    // C2B (Paybill) — Safaricom calls us to validate a payment before completing it
    'c2b_validation_url' => env('MPESA_C2B_VALIDATION_URL', ''),

    // C2B (Paybill) — Safaricom calls us once a payment is confirmed and final
    'c2b_confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL', ''),

    // B2C payouts / reversals — Safaricom posts results and timeouts here
    'b2c_result_url' => env('MPESA_B2C_RESULT_URL', ''),
    'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL', ''),
    'reversal_result_url' => env('MPESA_REVERSAL_RESULT_URL', ''),
    'reversal_timeout_url' => env('MPESA_REVERSAL_TIMEOUT_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Tenant Paybill Account Number
    |--------------------------------------------------------------------------
    |
    | Each tenant gets a unique account number auto-assigned at creation.
    | Format: {prefix}{zero-padded-counter} e.g. POA00001, POA00002
    |
    */
    'account_prefix' => env('MPESA_ACCOUNT_PREFIX', 'POA'),

];
