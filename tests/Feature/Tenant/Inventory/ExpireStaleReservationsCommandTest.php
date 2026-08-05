<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ExpireStaleReservationsCommandTest extends TestCase
{
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createCentralTenant(): string
    {
        $tenantId = 'expire-res-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $tenantId;
    }

    private function deleteCentralTenant(string $tenantId): void
    {
        DB::connection('central')->table('tenants')->where('id', $tenantId)->delete();
    }

    /**
     * Binding a mock into the container proves two things at once: the
     * command resolves StockReservationService fresh per tenant (via app(),
     * not constructor injection — the previous bug's fix), and it does so
     * from *inside* $tenant->run() — asserted here by checking tenant()->id
     * matches at the exact moment the service call happens, without needing
     * a real, physically-provisioned per-tenant database.
     */
    public function test_command_runs_service_inside_correct_tenant_context(): void
    {
        $tenantId = $this->createCentralTenant();

        try {
            $mock = Mockery::mock(StockReservationService::class);
            $mock->shouldReceive('expireStaleReservations')
                ->once()
                ->andReturnUsing(function () use ($tenantId) {
                    $this->assertSame($tenantId, tenant()->id);

                    return 3;
                });
            $this->app->instance(StockReservationService::class, $mock);

            $this->artisan('inventory:expire-reservations', ['--tenant' => $tenantId])
                ->assertExitCode(0);
        } finally {
            $this->deleteCentralTenant($tenantId);
        }
    }

    public function test_command_fails_for_unknown_tenant_option(): void
    {
        $this->artisan('inventory:expire-reservations', ['--tenant' => 'does-not-exist'])
            ->assertExitCode(1);
    }

    public function test_command_iterates_every_tenant_when_no_tenant_option_given(): void
    {
        $tenantA = $this->createCentralTenant();
        $tenantB = $this->createCentralTenant();

        try {
            $seenTenantIds = [];
            $mock = Mockery::mock(StockReservationService::class);
            $mock->shouldReceive('expireStaleReservations')
                ->atLeast()->times(2)
                ->andReturnUsing(function () use (&$seenTenantIds) {
                    $seenTenantIds[] = tenant()->id;

                    return 0;
                });
            $this->app->instance(StockReservationService::class, $mock);

            $this->artisan('inventory:expire-reservations')->assertExitCode(0);

            $this->assertContains($tenantA, $seenTenantIds);
            $this->assertContains($tenantB, $seenTenantIds);
        } finally {
            $this->deleteCentralTenant($tenantA);
            $this->deleteCentralTenant($tenantB);
        }
    }
}
