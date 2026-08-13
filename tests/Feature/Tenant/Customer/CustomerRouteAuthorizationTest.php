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
            'store' => ['POST', 'api/v1/tenant/customers'],
            'update' => ['PATCH', 'api/v1/tenant/customers/{customer}'],
            'upgrade type' => ['PATCH', 'api/v1/tenant/customers/{customer}/upgrade-type'],
            'destroy' => ['DELETE', 'api/v1/tenant/customers/{customer}'],
            'restore' => ['POST', 'api/v1/tenant/customers/{customer}/restore'],
            'toggle status' => ['PATCH', 'api/v1/tenant/customers/{customer}/toggle-status'],
            'toggle marketing' => ['PATCH', 'api/v1/tenant/customers/{customer}/toggle-marketing'],
            'group store' => ['POST', 'api/v1/tenant/customer-groups'],
            'group update' => ['PATCH', 'api/v1/tenant/customer-groups/{customer_group}'],
            'group destroy' => ['DELETE', 'api/v1/tenant/customer-groups/{customer_group}'],
            'group toggle' => ['PATCH', 'api/v1/tenant/customer-groups/{customer_group}/toggle'],
            'group add member' => ['POST', 'api/v1/tenant/customer-groups/{customer_group}/members'],
            'group remove member' => ['DELETE', 'api/v1/tenant/customer-groups/{customer_group}/members/{customer}'],
            'group bulk add members' => ['POST', 'api/v1/tenant/customer-groups/{customer_group}/members/bulk'],
        ];
    }

    public static function viewCustomersGatedRoutes(): array
    {
        return [
            'search' => ['GET', 'api/v1/tenant/customers/search'],
            'marketing eligible' => ['GET', 'api/v1/tenant/customers/marketing-eligible'],
            'index' => ['GET', 'api/v1/tenant/customers'],
            'show' => ['GET', 'api/v1/tenant/customers/{customer}'],
            'group index' => ['GET', 'api/v1/tenant/customer-groups'],
            'group show' => ['GET', 'api/v1/tenant/customer-groups/{customer_group}'],
            'group members' => ['GET', 'api/v1/tenant/customer-groups/{customer_group}/members'],
        ];
    }

    public static function creditManagementGatedRoutes(): array
    {
        return [
            'credit index' => ['GET', 'api/v1/tenant/credit-transactions'],
            'record payment' => ['POST', 'api/v1/tenant/credit-transactions/record-payment'],
            'record adjustment' => ['POST', 'api/v1/tenant/credit-transactions/record-adjustment'],
            'record write off' => ['POST', 'api/v1/tenant/credit-transactions/record-write-off'],
            'credit analytics' => ['GET', 'api/v1/tenant/credit-transactions/analytics/overview'],
            'credit show' => ['GET', 'api/v1/tenant/credit-transactions/{id}'],
            'customer credit history' => ['GET', 'api/v1/tenant/customers/{customerId}/credit-transactions'],
        ];
    }

    public static function loyaltyTransactionsGatedRoutes(): array
    {
        return [
            'loyalty index' => ['GET', 'api/v1/tenant/loyalty-transactions'],
            'manual award' => ['POST', 'api/v1/tenant/loyalty-transactions/award-manual'],
            'loyalty analytics' => ['GET', 'api/v1/tenant/loyalty-transactions/analytics/overview'],
            'loyalty show' => ['GET', 'api/v1/tenant/loyalty-transactions/{id}'],
            'customer loyalty history' => ['GET', 'api/v1/tenant/customers/{customerId}/loyalty-transactions'],
        ];
    }

    #[DataProvider('viewCustomersGatedRoutes')]
    public function test_route_requires_view_customers(string $method, string $uri): void
    {
        $this->assertContains('permission:view-customers,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('manageCustomersGatedRoutes')]
    public function test_route_requires_manage_customers(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-customers,tenant', $middleware);
        $this->assertContains('permission:manage-customers,tenant', $middleware);
    }

    #[DataProvider('creditManagementGatedRoutes')]
    public function test_route_requires_credit_management(string $method, string $uri): void
    {
        $this->assertContains('permission:credit-management,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('loyaltyTransactionsGatedRoutes')]
    public function test_route_requires_loyalty_transactions(string $method, string $uri): void
    {
        $this->assertContains('permission:loyalty-transactions,tenant', $this->middlewareFor($method, $uri));
    }
}
