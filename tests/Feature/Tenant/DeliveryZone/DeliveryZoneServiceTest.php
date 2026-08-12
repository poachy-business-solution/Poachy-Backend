<?php

namespace Tests\Feature\Tenant\DeliveryZone;

use App\Events\Tenant\DeliveryZoneMarketplaceSyncRequested;
use App\Repositories\Tenant\DeliveryZoneRepository;
use App\Services\Tenant\Business\DeliveryZoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class DeliveryZoneServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        // The DeliveryZoneObserver fires a real (synchronous) event on every
        // create/update/delete, whose ShouldQueue listener would otherwise try
        // to actually queue a job — fake it, matching the established
        // Queue::fake() convention used wherever this kind of observer runs.
        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): DeliveryZoneService
    {
        return new DeliveryZoneService(new DeliveryZoneRepository);
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'zone_name' => 'Nairobi CBD',
            'zone_type' => 'city',
            'cities' => ['Nairobi'],
            'standard_fee' => 200,
        ], $overrides);
    }

    // =========================================================================
    // store() / model boot normalization
    // =========================================================================

    public function test_store_creates_zone(): void
    {
        $zone = $this->makeService()->store($this->baseData());

        $this->assertSame('Nairobi CBD', $zone->zone_name);
        $this->assertDatabaseHas('tenant_delivery_zones', ['id' => $zone->id], 'tenant');
    }

    public function test_store_normalizes_cities_to_lowercase_trimmed(): void
    {
        $zone = $this->makeService()->store($this->baseData(['cities' => [' Nairobi ', 'MOMBASA']]));

        $this->assertSame(['nairobi', 'mombasa'], $zone->cities);
    }

    public function test_store_normalizes_counties_to_lowercase_trimmed(): void
    {
        $zone = $this->makeService()->store($this->baseData([
            'zone_type' => 'county', 'counties' => [' Kiambu ', 'NAKURU'], 'cities' => null,
        ]));

        $this->assertSame(['kiambu', 'nakuru'], $zone->counties);
    }

    public function test_store_defaults_supported_methods_when_omitted(): void
    {
        $zone = $this->makeService()->store($this->baseData());

        $this->assertSame(['standard'], $zone->supported_methods);
    }

    public function test_store_respects_explicit_supported_methods(): void
    {
        $zone = $this->makeService()->store($this->baseData(['supported_methods' => ['standard', 'express']]));

        $this->assertSame(['standard', 'express'], $zone->supported_methods);
    }

    public function test_store_dispatches_marketplace_sync_event(): void
    {
        Event::fake([DeliveryZoneMarketplaceSyncRequested::class]);

        $zone = $this->makeService()->store($this->baseData());

        Event::assertDispatched(DeliveryZoneMarketplaceSyncRequested::class, fn ($e) => $e->zoneDTO->zoneId === $zone->id && $e->action === 'create');
    }

    // =========================================================================
    // findOrFail()
    // =========================================================================

    public function test_find_or_fail_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->findOrFail(999999);
    }

    public function test_find_or_fail_returns_zone(): void
    {
        $zone = $this->makeService()->store($this->baseData());

        $found = $this->makeService()->findOrFail($zone->id);

        $this->assertSame($zone->id, $found->id);
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function test_update_changes_fields(): void
    {
        $zone = $this->makeService()->store($this->baseData(['standard_fee' => 200]));

        $updated = $this->makeService()->update($zone, ['standard_fee' => 350]);

        $this->assertEquals(350, $updated->standard_fee);
    }

    public function test_update_dispatches_marketplace_sync_event(): void
    {
        $zone = $this->makeService()->store($this->baseData());
        Event::fake([DeliveryZoneMarketplaceSyncRequested::class]);

        $this->makeService()->update($zone, ['standard_fee' => 999]);

        Event::assertDispatched(DeliveryZoneMarketplaceSyncRequested::class, fn ($e) => $e->action === 'update');
    }

    // =========================================================================
    // destroy()
    // =========================================================================

    public function test_destroy_removes_zone(): void
    {
        $zone = $this->makeService()->store($this->baseData());

        $this->makeService()->destroy($zone);

        $this->assertDatabaseMissing('tenant_delivery_zones', ['id' => $zone->id], 'tenant');
    }

    public function test_destroy_dispatches_marketplace_sync_event(): void
    {
        $zone = $this->makeService()->store($this->baseData());
        Event::fake([DeliveryZoneMarketplaceSyncRequested::class]);

        $this->makeService()->destroy($zone);

        Event::assertDispatched(DeliveryZoneMarketplaceSyncRequested::class, fn ($e) => $e->action === 'delete');
    }

    // =========================================================================
    // reorder()
    // =========================================================================

    public function test_reorder_updates_priorities(): void
    {
        $a = $this->makeService()->store($this->baseData(['zone_name' => 'Zone A', 'priority' => 10]));
        $b = $this->makeService()->store($this->baseData(['zone_name' => 'Zone B', 'priority' => 20]));

        $this->makeService()->reorder([
            ['id' => $a->id, 'priority' => 20],
            ['id' => $b->id, 'priority' => 10],
        ]);

        $this->assertSame(20, $a->fresh()->priority);
        $this->assertSame(10, $b->fresh()->priority);
    }

    // =========================================================================
    // getAll() filters
    // =========================================================================

    public function test_get_all_orders_by_priority_then_name(): void
    {
        $low = $this->makeService()->store($this->baseData(['zone_name' => 'Low Priority', 'priority' => 50]));
        $high = $this->makeService()->store($this->baseData(['zone_name' => 'High Priority', 'priority' => 5]));

        $result = $this->makeService()->getAll();

        $this->assertSame($high->id, $result->first()->id);
    }

    public function test_get_all_filters_by_active_status(): void
    {
        $active = $this->makeService()->store($this->baseData(['zone_name' => 'Active Zone', 'is_active' => true]));
        $inactive = $this->makeService()->store($this->baseData(['zone_name' => 'Inactive Zone', 'is_active' => false]));

        $result = $this->makeService()->getAll(['is_active' => true]);

        $this->assertTrue($result->contains('id', $active->id));
        $this->assertFalse($result->contains('id', $inactive->id));
    }

    public function test_get_all_filters_by_zone_type(): void
    {
        $city = $this->makeService()->store($this->baseData(['zone_name' => 'City Zone', 'zone_type' => 'city']));
        $county = $this->makeService()->store($this->baseData(['zone_name' => 'County Zone', 'zone_type' => 'county', 'cities' => null, 'counties' => ['Kiambu']]));

        $result = $this->makeService()->getAll(['zone_type' => 'county']);

        $this->assertTrue($result->contains('id', $county->id));
        $this->assertFalse($result->contains('id', $city->id));
    }

    public function test_get_all_filters_by_search(): void
    {
        $match = $this->makeService()->store($this->baseData(['zone_name' => 'Westlands Express']));
        $other = $this->makeService()->store($this->baseData(['zone_name' => 'Karen Standard']));

        $result = $this->makeService()->getAll(['search' => 'Westlands']);

        $this->assertTrue($result->contains('id', $match->id));
        $this->assertFalse($result->contains('id', $other->id));
    }

    // =========================================================================
    // hasActiveZone()
    // =========================================================================

    public function test_has_active_zone_false_when_none_exist(): void
    {
        $this->assertFalse($this->makeService()->hasActiveZone());
    }

    public function test_has_active_zone_true_when_active_zone_exists(): void
    {
        $this->makeService()->store($this->baseData(['is_active' => true]));

        $this->assertTrue($this->makeService()->hasActiveZone());
    }

    public function test_has_active_zone_false_when_only_inactive_zones_exist(): void
    {
        $this->makeService()->store($this->baseData(['is_active' => false]));

        $this->assertFalse($this->makeService()->hasActiveZone());
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        Schema::connection('tenant')->dropIfExists('tenant_delivery_zones');
    }

    private function createMinimalSchema(): void
    {
        Schema::connection('tenant')->create('tenant_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_name');
            $table->enum('zone_type', ['city', 'county', 'postal_code', 'radius'])->default('city');
            $table->json('cities')->nullable();
            $table->json('counties')->nullable();
            $table->json('postal_codes')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedInteger('radius_km')->nullable();
            $table->decimal('standard_fee', 10, 2)->default(0);
            $table->decimal('express_fee', 10, 2)->nullable();
            $table->decimal('scheduled_fee', 10, 2)->nullable();
            $table->decimal('free_delivery_threshold', 10, 2)->nullable();
            $table->string('standard_delivery_time')->nullable();
            $table->string('express_delivery_time')->nullable();
            $table->string('scheduled_delivery_time')->nullable();
            $table->json('supported_methods')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
