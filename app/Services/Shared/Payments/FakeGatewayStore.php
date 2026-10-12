<?php

namespace App\Services\Shared\Payments;

use Illuminate\Support\Facades\Redis;

/**
 * Where the fake M-Pesa and Paystack drivers remember their requests, so that
 * `poachy:simulate-*` (CLI, central context) can find a request made inside a tenant request.
 *
 * Plain Redis on purpose: the cache is tenant-scoped by Stancl's CacheTenancyBootstrapper,
 * Redis is not (RedisTenancyBootstrapper is off). Keys are namespaced by app environment so
 * test runs never see local-dev records. Records expire after a day.
 */
class FakeGatewayStore
{
    private const TTL_SECONDS = 86400;

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        $value = Redis::get(self::key($key));

        return $value === null ? null : json_decode($value, true);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function put(string $key, array $value): void
    {
        Redis::setex(self::key($key), self::TTL_SECONDS, json_encode($value));
    }

    public static function getString(string $key): ?string
    {
        return Redis::get(self::key($key));
    }

    public static function putString(string $key, string $value): void
    {
        Redis::setex(self::key($key), self::TTL_SECONDS, $value);
    }

    private static function key(string $key): string
    {
        return 'fake_gateways:'.app()->environment().':'.$key;
    }
}
