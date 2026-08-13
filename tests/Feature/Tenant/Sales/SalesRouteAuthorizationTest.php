<?php

namespace Tests\Feature\Tenant\Sales;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function viewSalesRoutes(): array
    {
        return [
            'customer search' => ['GET', 'api/v1/tenant/sales/customers/search'],
            'calculate sale' => ['POST', 'api/v1/tenant/sales/calculate'],
            'list sales' => ['GET', 'api/v1/tenant/sales'],
            'sale show' => ['GET', 'api/v1/tenant/sales/{sale}'],
            'sale receipt' => ['GET', 'api/v1/tenant/sales/{sale}/receipt'],
            'refundable items' => ['GET', 'api/v1/tenant/sales/{sale}/refundable-items'],
            'sale refunds' => ['GET', 'api/v1/tenant/sales/{sale}/refunds'],
            'refund index' => ['GET', 'api/v1/tenant/refunds'],
            'refund show' => ['GET', 'api/v1/tenant/refunds/{refund}'],
            'refund receipt' => ['GET', 'api/v1/tenant/refunds/{refund}/receipt'],
        ];
    }

    public static function processRefundRoutes(): array
    {
        return [
            'initiate refund' => ['POST', 'api/v1/tenant/sales/{sale}/refunds'],
            'initiate exchange' => ['POST', 'api/v1/tenant/sales/{sale}/exchange'],
            'cancel refund' => ['PATCH', 'api/v1/tenant/refunds/{refund}/cancel'],
        ];
    }

    public static function viewMarketplaceSalesRoutes(): array
    {
        return [
            'marketplace sale index' => ['GET', 'api/v1/tenant/marketplace-sales'],
            'marketplace sale show' => ['GET', 'api/v1/tenant/marketplace-sales/{id}'],
        ];
    }

    public static function manageMarketplaceSalesRoutes(): array
    {
        return [
            'update fulfillment status' => ['PATCH', 'api/v1/tenant/marketplace-sales/{id}/fulfillment-status'],
            'update delivery location' => ['PATCH', 'api/v1/tenant/marketplace-sales/{id}/location'],
        ];
    }

    #[DataProvider('viewSalesRoutes')]
    public function test_sales_read_route_requires_view_sales(string $method, string $uri): void
    {
        $this->assertContains('permission:view-sales,tenant', $this->middlewareFor($method, $uri));
    }

    public function test_create_sale_route_requires_create_sales(): void
    {
        $middleware = $this->middlewareFor('POST', 'api/v1/tenant/sales');

        $this->assertContains('permission:view-sales,tenant', $middleware);
        $this->assertContains('permission:create-sales,tenant', $middleware);
    }

    #[DataProvider('processRefundRoutes')]
    public function test_refund_mutation_route_requires_process_refunds(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-sales,tenant', $middleware);
        $this->assertContains('permission:process-refunds,tenant', $middleware);
    }

    #[DataProvider('viewMarketplaceSalesRoutes')]
    public function test_marketplace_sales_read_route_requires_view_marketplace_sales(string $method, string $uri): void
    {
        $this->assertContains('permission:view-marketplace-sales,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('manageMarketplaceSalesRoutes')]
    public function test_marketplace_sales_mutation_route_requires_manage_marketplace_sales(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-marketplace-sales,tenant', $middleware);
        $this->assertContains('permission:manage-marketplace-sales,tenant', $middleware);
    }
}
