<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant -> central API communication
    |
    */
    'central_api' => [
        'url' => env('CENTRAL_API_URL', 'http://0.0.0.0:80'),
        'token' => env('CENTRAL_API_TOKEN', '4Td2G0osKU6r8D5bcV8BX4j3DmpcemV5BkRwwLYoiMHkVshtpf0k2GmS3Vbf2Stj'),
        'timeout' => env('CENTRAL_API_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for central -> tenant API communication
    |
    */
    'tenant_api' => [
        'token' => env('TENANT_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
];
