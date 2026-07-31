<?php

namespace Tests\Feature\Central\Admin;

use App\Models\TenantDeliveryZone;
use App\Services\Central\Admin\Tenant\TenantDeliveryZoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantDeliveryZoneServiceTest extends TestCase
{
    private string $tenantId;

    private string $otherTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'admin-dz-test-'.uniqid();
        $this->otherTenantId = 'admin-dz-other-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            ['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $this->otherTenantId, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        TenantDeliveryZone::on('central')->whereIn('tenant_id', [$this->tenantId, $this->otherTenantId])->delete();
        DB::connection('central')->table('tenants')->whereIn('id', [$this->tenantId, $this->otherTenantId])->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeService(): TenantDeliveryZoneService
    {
        return new TenantDeliveryZoneService(new TenantDeliveryZone);
    }

    private function createZone(string $tenantId, array $overrides = []): TenantDeliveryZone
    {
        return TenantDeliveryZone::on('central')->create(array_merge([
            'tenant_id' => $tenantId,
            'zone_name' => 'Zone '.uniqid(),
            'zone_type' => 'city',
            'cities' => ['nairobi'],
            'standard_fee' => 200,
            'is_active' => true,
            'sync_status' => 'synced',
        ], $overrides));
    }

    // =========================================================================
    // getAllDeliveryZones()
    // =========================================================================

    public function test_get_all_delivery_zones_eager_loads_tenant(): void
    {
        $this->createZone($this->tenantId);

        $result = $this->makeService()->getAllDeliveryZones();

        $this->assertTrue($result->getCollection()->first()->relationLoaded('tenant'));
    }

    public function test_get_all_delivery_zones_filters_by_tenant_id(): void
    {
        $mine = $this->createZone($this->tenantId);
        $other = $this->createZone($this->otherTenantId);

        $result = $this->makeService()->getAllDeliveryZones(['tenant_id' => $this->tenantId]);

        $this->assertTrue($result->getCollection()->contains('id', $mine->id));
        $this->assertFalse($result->getCollection()->contains('id', $other->id));
    }

    public function test_get_all_delivery_zones_filters_by_sync_status(): void
    {
        $synced = $this->createZone($this->tenantId, ['sync_status' => 'synced']);
        $pending = $this->createZone($this->tenantId, ['sync_status' => 'pending']);

        $result = $this->makeService()->getAllDeliveryZones(['sync_status' => 'pending']);

        $this->assertTrue($result->getCollection()->contains('id', $pending->id));
        $this->assertFalse($result->getCollection()->contains('id', $synced->id));
    }

    public function test_get_all_delivery_zones_filters_by_active_status(): void
    {
        $active = $this->createZone($this->tenantId, ['is_active' => true]);
        $inactive = $this->createZone($this->tenantId, ['is_active' => false]);

        $result = $this->makeService()->getAllDeliveryZones(['is_active' => true]);

        $this->assertTrue($result->getCollection()->contains('id', $active->id));
        $this->assertFalse($result->getCollection()->contains('id', $inactive->id));
    }

    public function test_get_all_delivery_zones_orders_by_priority(): void
    {
        $low = $this->createZone($this->tenantId, ['priority' => 50]);
        $high = $this->createZone($this->tenantId, ['priority' => 5]);

        $result = $this->makeService()->getAllDeliveryZones(['tenant_id' => $this->tenantId]);

        $this->assertSame($high->id, $result->getCollection()->first()->id);
    }

    // =========================================================================
    // getDeliveryZone()
    // =========================================================================

    public function test_get_delivery_zone_returns_zone_with_tenant(): void
    {
        $zone = $this->createZone($this->tenantId);

        $found = $this->makeService()->getDeliveryZone($zone->id);

        $this->assertSame($zone->id, $found->id);
        $this->assertTrue($found->relationLoaded('tenant'));
    }

    public function test_get_delivery_zone_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->getDeliveryZone(999999999);
    }
}
