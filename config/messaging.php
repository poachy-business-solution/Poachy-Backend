<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public links and contact details used in emails and SMS
    |--------------------------------------------------------------------------
    */

    'site_url' => env('POACHY_SITE_URL', 'http://localhost:3000'),
    'support_email' => env('POACHY_SUPPORT_EMAIL', 'help@poachy.com'),
    'support_phone' => env('POACHY_SUPPORT_PHONE', '+254 700 000 000'),

    // The Poachy Paybill shops pay into (account number = the shop's POA… number).
    'paybill_number' => env('POACHY_PAYBILL_NUMBER', env('MPESA_PRODUCTION_SHORTCODE') ?: env('MPESA_SANDBOX_SHORTCODE', '174379')),

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | 'log'            — writes the message to storage/logs/sms.log (default; local + tests).
    | 'africastalking' — Africa's Talking bulk SMS API (needs the keys below).
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender_id' => env('SMS_SENDER_ID', 'POACHY'),

        'africastalking' => [
            'username' => env('AFRICASTALKING_USERNAME', 'sandbox'),
            'api_key' => env('AFRICASTALKING_API_KEY', ''),
            'base_url' => env('AFRICASTALKING_BASE_URL', 'https://api.sandbox.africastalking.com'),
        ],
    ],

];
