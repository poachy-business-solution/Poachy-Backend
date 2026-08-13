<?php

namespace Tests\Feature\Central\Http;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Central\Concerns\InteractsWithCentralHttpAuth;
use Tests\TestCase;

class CentralRouteMiddlewareSmokeTest extends TestCase
{
    use InteractsWithCentralHttpAuth;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy_central_test'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');
    }

    protected function tearDown(): void
    {
        $this->cleanupCentralHttpUsers();

        parent::tearDown();
    }

    public static function protectedCentralRoutes(): array
    {
        return [
            'admin me' => ['GET', '/api/v1/central/auth/admin/me'],
            'customer profile' => ['GET', '/api/v1/central/customer/profile'],
            'tenant profiles' => ['GET', '/api/v1/central/tenant-profiles'],
            'admin tenants' => ['GET', '/api/v1/central/tenants'],
            'reports funnel' => ['GET', '/api/v1/central/reports/funnel'],
        ];
    }

    public static function adminOnlyCentralRoutes(): array
    {
        return [
            'admin tenant index' => ['GET', '/api/v1/central/tenants'],
            'business review queue' => ['GET', '/api/v1/central/business-details/pending'],
            'review moderation queue' => ['GET', '/api/v1/central/marketplace/pending-reviews'],
            'analytics reports' => ['GET', '/api/v1/central/reports/funnel'],
            'admin create' => ['POST', '/api/v1/central/auth/admin/create'],
        ];
    }

    public static function adminReadableCentralRoutes(): array
    {
        return [
            'admin tenant index' => ['GET', '/api/v1/central/tenants'],
            'business review queue' => ['GET', '/api/v1/central/business-details/pending'],
            'review moderation queue' => ['GET', '/api/v1/central/marketplace/pending-reviews'],
            'analytics reports' => ['GET', '/api/v1/central/reports/funnel'],
        ];
    }

    public function test_public_central_route_is_reachable_without_authentication(): void
    {
        $this->getJson('/api/v1/central/subscription-plans')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    #[DataProvider('protectedCentralRoutes')]
    public function test_protected_central_route_rejects_unauthenticated_requests(string $method, string $uri): void
    {
        $this->json($method, $uri)
            ->assertUnauthorized();
    }

    public function test_authenticated_central_user_can_reach_auth_guarded_route(): void
    {
        $user = $this->createCentralUserWithRole('customer');
        $this->actingAsCentral($user);

        $this->getJson('/api/v1/central/auth/admin/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_authenticated_customer_can_reach_customer_profile_route(): void
    {
        $user = $this->createCentralUserWithRole('customer');
        $this->createCentralCustomerProfile($user);
        $this->actingAsCentral($user);

        $this->getJson('/api/v1/central/customer/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email);
    }

    #[DataProvider('adminOnlyCentralRoutes')]
    public function test_non_admin_central_user_is_forbidden_from_admin_routes(string $method, string $uri): void
    {
        $user = $this->createCentralUserWithRole('customer');
        $this->actingAsCentral($user);

        $this->json($method, $uri)
            ->assertForbidden();
    }

    #[DataProvider('adminReadableCentralRoutes')]
    public function test_admin_central_user_can_reach_admin_routes(string $method, string $uri): void
    {
        $user = $this->createCentralUserWithRole('admin');
        $this->actingAsCentral($user);

        $this->json($method, $uri)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }
}
