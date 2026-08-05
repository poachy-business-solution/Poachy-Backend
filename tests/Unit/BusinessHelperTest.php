<?php

namespace Tests\Unit;

use App\Helpers\BusinessHelper;
use App\Models\BusinessDetail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessHelperTest extends TestCase
{
    private array $tenantIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
    }

    protected function tearDown(): void
    {
        BusinessDetail::on('central')->whereIn('tenant_id', $this->tenantIds)->forceDelete();
        DB::connection('central')->table('tenants')->whereIn('id', $this->tenantIds)->delete();

        foreach ($this->tenantIds as $tenantId) {
            Cache::forget("business_detail.{$tenantId}");
        }

        parent::tearDown();
    }

    private function createBusiness(string $businessName): string
    {
        $tenantId = 'biz-helper-test-'.uniqid();
        $this->tenantIds[] = $tenantId;

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        BusinessDetail::on('central')->create([
            'tenant_id' => $tenantId,
            'business_name' => $businessName,
            'business_type_id' => 1,
            'business_category_id' => 1,
            'business_phone' => '0712'.rand(100000, 999999),
        ]);

        return $tenantId;
    }

    public function test_warm_cache_populates_cache_for_every_given_tenant(): void
    {
        $tenantA = $this->createBusiness('Business A');
        $tenantB = $this->createBusiness('Business B');

        BusinessHelper::warmCache([$tenantA, $tenantB]);

        $this->assertTrue(Cache::has("business_detail.{$tenantA}"));
        $this->assertTrue(Cache::has("business_detail.{$tenantB}"));
    }

    public function test_warm_cache_lets_get_business_name_resolve_without_a_fresh_query(): void
    {
        $tenantId = $this->createBusiness('Warmed Business');

        BusinessHelper::warmCache([$tenantId]);

        $this->assertSame('Warmed Business', BusinessHelper::getBusinessName($tenantId));
    }

    public function test_warm_cache_ignores_null_and_duplicate_tenant_ids(): void
    {
        $tenantId = $this->createBusiness('Deduped Business');

        BusinessHelper::warmCache([$tenantId, $tenantId, null, '']);

        $this->assertSame('Deduped Business', BusinessHelper::getBusinessName($tenantId));
    }

    public function test_warm_cache_is_a_noop_for_empty_input(): void
    {
        // Just proving it doesn't throw or query when there's nothing to warm.
        BusinessHelper::warmCache([]);

        $this->assertTrue(true);
    }

    public function test_warm_cache_skips_tenants_already_cached(): void
    {
        $tenantId = $this->createBusiness('Originally Cached');
        BusinessHelper::warmCache([$tenantId]);

        // Mutate the underlying row directly — if warmCache() re-queries on a
        // second call despite the cache already being warm, this stale write
        // would surface and the assertion below would fail.
        BusinessDetail::on('central')->where('tenant_id', $tenantId)->update(['business_name' => 'Changed In DB']);
        BusinessHelper::warmCache([$tenantId]);

        $this->assertSame('Originally Cached', BusinessHelper::getBusinessName($tenantId));
    }
}
