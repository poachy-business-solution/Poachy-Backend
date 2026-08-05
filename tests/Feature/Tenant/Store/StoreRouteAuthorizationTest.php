<?php

namespace Tests\Feature\Tenant\Store;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreRouteAuthorizationTest extends TestCase
{
    private function routeFor(string $method, string $uri): Route
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route;
    }

    public static function mutatingStoreRoutes(): array
    {
        return [
            'create store' => ['POST', 'api/v1/tenant/stores'],
            'update details' => ['PATCH', 'api/v1/tenant/stores/{id}/details'],
            'set as main' => ['PATCH', 'api/v1/tenant/stores/{id}/set-main'],
            'activate' => ['PATCH', 'api/v1/tenant/stores/{id}/activate'],
            'deactivate' => ['PATCH', 'api/v1/tenant/stores/{id}/deactivate'],
            'assign manager' => ['POST', 'api/v1/tenant/stores/{id}/assign-manager'],
            'remove manager' => ['DELETE', 'api/v1/tenant/stores/{id}/remove-manager'],
        ];
    }

    #[DataProvider('mutatingStoreRoutes')]
    public function test_mutating_store_route_requires_manage_locations(string $method, string $uri): void
    {
        $route = $this->routeFor($method, $uri);

        $this->assertContains('permission:manage-locations,tenant', $route->middleware());
    }

    public static function readOnlyStoreRoutes(): array
    {
        return [
            'index' => ['GET', 'api/v1/tenant/stores'],
            'show' => ['GET', 'api/v1/tenant/stores/{id}'],
        ];
    }

    #[DataProvider('readOnlyStoreRoutes')]
    public function test_read_only_store_route_does_not_require_manage_locations(string $method, string $uri): void
    {
        $route = $this->routeFor($method, $uri);

        $this->assertNotContains('permission:manage-locations,tenant', $route->middleware());
    }
}
