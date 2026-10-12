<?php

/*
 * The private admin API (/api/internal/admin/*), called only by the poachy-admin app's server.
 * Three locks: the caller's network address must be on the allowlist (else 404, as if the route
 * did not exist), a shared service token (else 401), and the acting admin's id + role headers.
 * Production also keeps /api/internal off the public load balancer.
 */
return [
    // Shared secret (≥ 32 chars). Empty = the API is switched off (every call 404s).
    'token' => env('INTERNAL_ADMIN_TOKEN'),

    // Comma-separated IPs / CIDRs allowed to call it (the admin app's private address).
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTERNAL_ADMIN_ALLOWED_IPS', '127.0.0.1,::1'))))),

    // Admin roles (set by the admin app per staff member).
    'roles' => ['super', 'operations', 'finance', 'support'],

    // Largest single manual credit, in shillings.
    'max_credit' => (int) env('INTERNAL_ADMIN_MAX_CREDIT', 50000),
];
