<?php

namespace Tests\Feature\Tenant\Http;

use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

class TenantRouteMiddlewareSmokeTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public static function protectedTenantRoutes(): array
    {
        return [
            'stores' => ['GET', '/api/v1/tenant/stores'],
            'products' => ['GET', '/api/v1/tenant/products'],
            'inventory' => ['GET', '/api/v1/tenant/inventory'],
            'sales' => ['GET', '/api/v1/tenant/sales'],
            'marketplace sales' => ['GET', '/api/v1/tenant/marketplace-sales'],
            'daily sales reports' => ['GET', '/api/v1/tenant/reports/daily-sales'],
        ];
    }

    public static function cashierForbiddenRoutes(): array
    {
        return [
            'manage locations' => ['POST', '/api/v1/tenant/stores'],
            'manage products' => ['POST', '/api/v1/tenant/categories'],
            'product creation request authorization' => ['POST', '/api/v1/tenant/products'],
            'inventory adjustment request authorization' => ['POST', '/api/v1/tenant/inventory-movements/adjustment'],
            'manage store settings' => ['POST', '/api/v1/tenant/tax-rates'],
            'financial reports' => ['GET', '/api/v1/tenant/reports/daily-sales'],
        ];
    }

    public static function managerAccessibleRoutes(): array
    {
        return [
            'manage products' => ['POST', '/api/v1/tenant/products'],
            'create sales' => ['POST', '/api/v1/tenant/sales'],
            'inventory adjustment' => ['POST', '/api/v1/tenant/inventory-movements/adjustment'],
            'daily sales reports' => ['GET', '/api/v1/tenant/reports/daily-sales'],
            'marketplace sales' => ['GET', '/api/v1/tenant/marketplace-sales'],
        ];
    }

    public function test_tenant_routes_run_through_real_host_auth_and_permission_middleware(): void
    {
        $this->configureTenantHttpDatabases();

        $domain = 'tenant-http-smoke-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);

        try {
            $this->withHeaders($this->tenantHeaders($domain))
                ->getJson($this->tenantUrl($domain, '/api/v1/tenant/stores'))
                ->assertUnauthorized();

            $tenant->run(function () use ($domain) {
                $cashier = $this->createTenantUserWithRole('cashier');
                $manager = $this->createTenantUserWithRole('manager');
                $owner = $this->createTenantUserWithRole('owner');
                $unprivilegedUser = $this->createTenantUserWithoutRole();
                $store = Store::create([
                    'name' => 'HTTP Smoke Store',
                    'address' => 'HTTP Smoke Street',
                    'city' => 'Nairobi',
                    'is_active' => true,
                ]);
                $shift = Shift::create([
                    'shift_name' => 'HTTP Smoke Shift',
                    'store_id' => $store->id,
                    'scheduled_start_time' => '08:00',
                    'scheduled_end_time' => '16:00',
                    'applicable_days' => [strtolower(now()->addDay()->format('l'))],
                    'is_active' => true,
                ]);
                $cashierAssignment = ShiftAssignment::create([
                    'shift_id' => $shift->id,
                    'store_id' => $store->id,
                    'user_id' => $cashier->id,
                    'shift_date' => now()->addDay()->toDateString(),
                ]);
                $managerAssignment = ShiftAssignment::create([
                    'shift_id' => $shift->id,
                    'store_id' => $store->id,
                    'user_id' => $manager->id,
                    'shift_date' => now()->addDay()->toDateString(),
                ]);

                $this->actingAsTenant($cashier);
                $this->withHeaders($this->tenantHeaders($domain))
                    ->getJson($this->tenantUrl($domain, '/api/v1/tenant/stores'))
                    ->assertOk()
                    ->assertJsonPath('success', true);

                $shiftAssignmentResponse = $this->withHeaders($this->tenantHeaders($domain))
                    ->getJson($this->tenantUrl($domain, "/api/v1/tenant/shift-assignments?user_id={$manager->id}"))
                    ->assertOk()
                    ->assertJsonPath('data.assignments.0.id', $cashierAssignment->id);

                $assignmentIds = collect($shiftAssignmentResponse->json('data.assignments'))->pluck('id');
                $this->assertFalse($assignmentIds->contains($managerAssignment->id));

                foreach (self::protectedTenantRoutes() as [$method, $uri]) {
                    $requestUri = $uri === '/api/v1/tenant/inventory'
                        ? "{$uri}?store_id={$store->id}"
                        : $uri;
                    $response = $this->withHeaders($this->tenantHeaders($domain))
                        ->json($method, $this->tenantUrl($domain, $requestUri));

                    $this->assertSame(
                        $uri === '/api/v1/tenant/reports/daily-sales' ? 403 : 200,
                        $response->getStatusCode(),
                        "{$method} {$uri}: {$response->getContent()}"
                    );
                }

                foreach (self::cashierForbiddenRoutes() as [$method, $uri]) {
                    $response = $this->withHeaders($this->tenantHeaders($domain))
                        ->json($method, $this->tenantUrl($domain, $uri));

                    $this->assertSame(403, $response->getStatusCode(), "{$method} {$uri}: {$response->getContent()}");
                }

                $this->actingAsTenant($unprivilegedUser);

                $this->withHeaders($this->tenantHeaders($domain))
                    ->postJson($this->tenantUrl($domain, '/api/v1/tenant/sales'))
                    ->assertForbidden();

                $this->actingAsTenant($manager);

                foreach (self::managerAccessibleRoutes() as [$method, $uri]) {
                    $requestUri = $uri === '/api/v1/tenant/reports/daily-sales'
                        ? "{$uri}?date=".now()->toDateString()."&store_id={$store->id}"
                        : $uri;
                    $response = $this->withHeaders($this->tenantHeaders($domain))
                        ->json($method, $this->tenantUrl($domain, $requestUri));

                    $this->assertSame($method === 'GET' ? 200 : 422, $response->getStatusCode(), "{$method} {$uri}: {$response->getContent()}");
                }

                $this->actingAsTenant($owner);

                $this->withHeaders($this->tenantHeaders($domain))
                    ->postJson($this->tenantUrl($domain, '/api/v1/tenant/stores'))
                    ->assertStatus(422);
            });
        } finally {
            tenancy()->end();
            DB::purge('tenant');
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
