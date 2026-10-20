<?php

/*
| Marketplace payouts: Poachy collects online payments and pays each shop by M-Pesa B2C, in one
| nightly batch, for orders handed over at least `hold_hours` earlier. Processing fees come off
| the payout; the commission engine exists but its rate is 0% (own-link orders are always 0%).
| Fee percentages are defaults until the real Daraja / Paystack tariffs are confirmed.
*/

return [
    'hold_hours' => (int) env('PAYOUT_HOLD_HOURS', 24),

    // Below this (whole shillings) a shop's balance waits for the next night.
    'min_amount' => (int) env('PAYOUT_MIN_AMOUNT', 10),

    'fees' => [
        'mpesa_percent' => (float) env('PAYOUT_FEE_MPESA_PERCENT', 0.55),
        'card_percent' => (float) env('PAYOUT_FEE_CARD_PERCENT', 2.9),
        // Flat cost of the B2C transfer itself, per payout (KSh).
        'transfer_fee' => (float) env('PAYOUT_TRANSFER_FEE', 0),
    ],

    // Minutes to wait before re-sending a failed payout; after the last one it needs a person.
    'retry_delays' => [60, 360, 1440],

    // Clawbacks payouts have not covered after this many days come off the shop's Poachy balance.
    'settle_after_days' => 7,

    // Where failure alerts go (Poachy finance).
    'alert_email' => env('PAYOUT_ALERT_EMAIL', 'payments@poachy.test'),
];
