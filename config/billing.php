<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Limited mode
    |--------------------------------------------------------------------------
    |
    | A shop whose grace day has passed without a top-up keeps selling and syncing, but these
    | tenant API routes answer 402 until it pays. Patterns are matched against the path after
    | "api/v1/tenant/" (fnmatch, "*" spans "/"). "any" blocks every method, "write" blocks all
    | but GET/HEAD. Selling, refunds, catalogue sync, tills, auth and billing are never listed.
    |
    */

    'limited_mode' => [
        'any' => [
            'reports', 'reports/*',        // reports (incl. the owner's Today card)
            'shift-analytics', 'shift-analytics/*',
            'budgets', 'budgets/*',
            'audit-logs', 'audit-logs/*',
        ],
        'write' => [
            'purchase-orders', 'purchase-orders/*',   // receiving new stock
            'batches', 'batches/*',
            'products/*/receive-stock',
            'transfers', 'transfers/*',               // stock transfers
            'users', 'users/*', 'roles', 'roles/*',   // staff admin
            'stores', 'stores/*',                     // branch admin
            'tax-rates', 'tax-rates/*',
            'units-of-measure', 'units-of-measure/*',
            'tills/settings',
            'business-details/settings',
        ],
    ],

    // Notices go out between these Nairobi hours (no SMS at night).
    'notice_hours' => [8, 20],

];
