<?php

namespace Tests\Feature\Tenant\Customer;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function manageCustomersGatedRoutes(): array
    {
        return [
            'destroy' => ['DELETE', 'api/v1/tenant/customers/{customer}'],
            'restore' => ['POST', 'api/v1/tenant/customers/{customer}/restore'],
            'toggle status' => ['PATCH', 'api/v1/tenant/customers/{customer}/toggle-status'],
            'toggle marketing' => ['PATCH', 'api/v1/tenant/customers/{customer}/toggle-marketing'],
        ];
    }

    #[DataProvider('manageCustomersGatedRoutes')]
    public function test_route_requires_manage_customers(string $method, string $uri): void
    {
        $this->assertContains('permission:manage-customers,tenant', $this->middlewareFor($method, $uri));
    }
}
