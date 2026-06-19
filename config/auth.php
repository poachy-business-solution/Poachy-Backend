<?php

return [
    'defaults' => [
        'guard' => 'central',
        'passwords' => 'central_users',
    ],

    'guards' => [
        // Minimal web guard
        'web' => [
            'driver' => 'session',
            'provider' => 'central_users',
        ],

        // CENTRAL AUTH (API) 
        'central' => [
            'driver' => 'sanctum',
            'provider' => 'central_users',
        ],

        // TENANT AUTH (API) 
        'tenant' => [
            'driver' => 'sanctum',
            'provider' => 'tenant_users',
        ],
    ],

    'providers' => [

        'central_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        'tenant_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Tenant\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'central_users' => [
            'provider' => 'central_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
