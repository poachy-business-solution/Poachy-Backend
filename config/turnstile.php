<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (bot protection on public forms)
    |--------------------------------------------------------------------------
    |
    | 'cloudflare' — verify tokens with Cloudflare (use the official TEST secret locally:
    |                1x0000000000000000000000000000000AA always passes).
    | 'fake'       — no network: the token "fail" fails, anything else passes (tests).
    |
    */
    'driver' => env('TURNSTILE_DRIVER', 'cloudflare'),
    'secret' => env('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA'),
    'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',

];
