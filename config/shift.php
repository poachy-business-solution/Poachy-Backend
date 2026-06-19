<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cash Variance Threshold
    |--------------------------------------------------------------------------
    |
    | The minimum cash variance amount (in base currency) that requires
    | manager approval. Variances below this threshold are automatically
    | approved.
    |
    */

    'cash_variance_threshold' => env('SHIFT_CASH_VARIANCE_THRESHOLD', 100),

    /*
    |--------------------------------------------------------------------------
    | Auto No-Show Detection
    |--------------------------------------------------------------------------
    |
    | Automatically mark shifts as "no_show" if employee doesn't clock in
    | within the specified grace period (in minutes) after shift start time.
    |
    */

    'no_show_grace_period_minutes' => env('SHIFT_NO_SHOW_GRACE_PERIOD', 30),
    'auto_mark_no_show' => env('SHIFT_AUTO_MARK_NO_SHOW', true),

    /*
    |--------------------------------------------------------------------------
    | Late/Early Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the number of minutes before considering a clock-in as "late"
    | or a clock-out as "early departure".
    |
    */

    'late_threshold_minutes' => env('SHIFT_LATE_THRESHOLD', 15),
    'early_departure_threshold_minutes' => env('SHIFT_EARLY_DEPARTURE_THRESHOLD', 15),

    /*
    |--------------------------------------------------------------------------
    | Overtime Calculation
    |--------------------------------------------------------------------------
    |
    | Enable automatic overtime calculation. Overtime is calculated as
    | the difference between actual duration and scheduled duration.
    |
    */

    'calculate_overtime' => env('SHIFT_CALCULATE_OVERTIME', true),
    'overtime_rate_multiplier' => env('SHIFT_OVERTIME_RATE', 1.5), // 1.5x normal rate

    /*
    |--------------------------------------------------------------------------
    | Shift Reminders
    |--------------------------------------------------------------------------
    |
    | Send reminders to employees before their shifts start.
    | Specify how many hours before the shift to send the reminder.
    |
    */

    'send_shift_reminders' => env('SHIFT_SEND_REMINDERS', true),
    'reminder_hours_before' => env('SHIFT_REMINDER_HOURS_BEFORE', 2),

    /*
    |--------------------------------------------------------------------------
    | Shift Swap Settings
    |--------------------------------------------------------------------------
    |
    | Configure shift swap request behavior.
    |
    */

    'allow_shift_swaps' => env('SHIFT_ALLOW_SWAPS', true),
    'swap_request_expires_hours' => env('SHIFT_SWAP_EXPIRES_HOURS', 48),
    'require_manager_approval_for_swaps' => env('SHIFT_SWAP_REQUIRE_MANAGER', true),

    /*
    |--------------------------------------------------------------------------
    | Assignment Rules
    |--------------------------------------------------------------------------
    |
    | Business rules for shift assignments.
    |
    */

    'prevent_overlapping_shifts' => env('SHIFT_PREVENT_OVERLAPPING', true),
    'allow_back_to_back_shifts' => env('SHIFT_ALLOW_BACK_TO_BACK', true),
    'minimum_rest_hours_between_shifts' => env('SHIFT_MIN_REST_HOURS', 0), // 0 = allow back-to-back

    /*
    |--------------------------------------------------------------------------
    | Multi-Store Assignment Rules
    |--------------------------------------------------------------------------
    |
    | Prevent users from being assigned to different stores on same day.
    |
    */

    'prevent_multi_store_same_day' => env('SHIFT_PREVENT_MULTI_STORE', true),

    /*
    |--------------------------------------------------------------------------
    | Approval Settings
    |--------------------------------------------------------------------------
    |
    | Configure approval workflow for shifts.
    |
    */

    'require_approval_for_completion' => env('SHIFT_REQUIRE_APPROVAL', false),
    'auto_approve_below_variance_threshold' => env('SHIFT_AUTO_APPROVE_SMALL_VARIANCE', true),
    'prevent_self_approval' => env('SHIFT_PREVENT_SELF_APPROVAL', true),

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Channels to use for shift notifications.
    |
    */

    'notification_channels' => [
        'email' => env('SHIFT_NOTIFY_EMAIL', true),
        'sms' => env('SHIFT_NOTIFY_SMS', false),
        'in_app' => env('SHIFT_NOTIFY_IN_APP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep historical shift data (in years).
    | Set to null for indefinite retention.
    |
    */

    'retention_years' => env('SHIFT_RETENTION_YEARS', null), // null = keep forever

];
