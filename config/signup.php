<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace domains
    |--------------------------------------------------------------------------
    |
    | Domains created for a new shop; {slug} is the chosen workspace name. The first is the
    | primary. Production: "{slug}.poachy.com". Locally: *.localhost (resolves to 127.0.0.1) and
    | the Android emulator's nip.io host.
    |
    */
    'domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIGNUP_TENANT_DOMAINS', '{slug}.localhost,{slug}.10.0.2.2.nip.io'))))),

    // Names nobody can take (sub-domains we use or that would confuse people).
    'reserved' => [
        'www', 'api', 'app', 'admin', 'administrator', 'mail', 'email', 'smtp', 'ftp', 'support', 'help', 'status',
        'business', 'pay', 'market', 'marketplace', 'shop', 'shops', 'store', 'stores', 'poachy', 'static', 'assets',
        'cdn', 'img', 'images', 'docs', 'blog', 'news', 'dev', 'test', 'staging', 'demo', 'root', 'system', 'billing',
        'account', 'accounts', 'login', 'signup', 'register', 'auth', 'secure', 'm', 'mobile', 'web',
    ],

    // Phone + email codes
    'code_ttl_minutes' => 15,
    'max_code_attempts' => 5,
    'max_code_sends' => 3,

    // A sign-up left unfinished is forgotten after this many hours.
    'expires_after_hours' => 24,

    // Shop type used when the form does not say (drives the starter catalogue template).
    'default_business_category' => env('SIGNUP_DEFAULT_BUSINESS_CATEGORY', 'Supermarket'),

];
