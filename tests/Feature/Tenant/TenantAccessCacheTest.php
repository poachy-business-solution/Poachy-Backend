<?php

namespace Tests\Feature\Tenant;

use App\Services\Tenant\TenantAccessService;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** Suspending (or restoring) a shop from central takes effect at once, not after the 24 h cache. */
class TenantAccessCacheTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_clearing_from_central_clears_the_shops_own_cached_access(): void
    {
        $this->configureTenantHttpDatabases();
        config(['cache.default' => 'redis']); // as in production; the array store is rebuilt on every tenancy switch
        $domain = 'access-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $key = "tenant_access:{$tenant->id}";

        try {
            // The shop's access check cached its answer (inside tenancy: tagged with the shop).
            $tenant->run(fn () => Cache::put($key, ['allowed' => true], now()->addDay()));
            $this->assertTrue($tenant->run(fn () => Cache::has($key)));

            // An admin suspends the shop from central.
            app(TenantAccessService::class)->clearTenantAccessCache((string) $tenant->id);

            $this->assertFalse($tenant->run(fn () => Cache::has($key)));
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
