<?php

namespace App\Support;

/**
 * Daraja does not sign its callbacks, so every URL we give Safaricom (STK results, C2B validation
 * and confirmation) ends in a secret segment derived from APP_KEY. A forged "payment received"
 * posted to the bare path never reaches the handlers. Optional `mpesa.callback_ips` further limits
 * callers to Safaricom's published addresses in production.
 */
class MpesaCallbacks
{
    /** @var array<string, array{0: string, 1: string}> kind => [config key for the base URL, default path] */
    private const KINDS = [
        'stk' => ['mpesa.stk_callback_url', '/api/v1/central/mpesa/stk/callback'],
        'stk_subscription' => ['mpesa.subscription_stk_callback_url', '/api/v1/central/mpesa/stk/subscription/callback'],
        'stk_billing' => ['mpesa.billing_stk_callback_url', '/api/v1/central/mpesa/stk/billing/callback'],
        'c2b_validation' => ['mpesa.c2b_validation_url', '/api/v1/central/mpesa/c2b/validate'],
        'c2b_confirmation' => ['mpesa.c2b_confirmation_url', '/api/v1/central/mpesa/c2b/confirm'],
    ];

    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'mpesa-callbacks', (string) config('app.key')), 0, 40);
    }

    /** The full callback URL for one kind: the configured base (or app URL + default path) + the secret. */
    public static function url(string $kind): string
    {
        [$key, $path] = self::KINDS[$kind];
        $base = (string) config($key) ?: rtrim((string) config('app.url'), '/').$path;

        return rtrim($base, '/').'/'.self::token();
    }

    /** For logs and console output: the URL with its secret hidden. */
    public static function masked(string $kind): string
    {
        return str_replace(self::token(), '••••', self::url($kind));
    }
}
