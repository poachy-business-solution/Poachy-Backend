<?php

namespace Tests\Feature\Tenant\Supplier;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function viewSuppliersRoutes(): array
    {
        return [
            'supplier index' => ['GET', 'api/v1/tenant/suppliers'],
            'supplier options' => ['GET', 'api/v1/tenant/suppliers/supplier-options'],
            'supplier show' => ['GET', 'api/v1/tenant/suppliers/{supplier}'],
        ];
    }

    public static function manageSuppliersRoutes(): array
    {
        return [
            'supplier store' => ['POST', 'api/v1/tenant/suppliers'],
            'supplier personal details' => ['PATCH', 'api/v1/tenant/suppliers/{supplier}/personal-details'],
            'supplier financial details' => ['PATCH', 'api/v1/tenant/suppliers/{supplier}/financial-details'],
            'supplier toggle active' => ['PATCH', 'api/v1/tenant/suppliers/{supplier}/toggle-active'],
        ];
    }

    public static function viewSupplierPaymentsRoutes(): array
    {
        return [
            'supplier payment index' => ['GET', 'api/v1/tenant/supplier-payments'],
            'supplier payment show' => ['GET', 'api/v1/tenant/supplier-payments/{id}'],
            'supplier payments by supplier' => ['GET', 'api/v1/tenant/suppliers/{supplierId}/payments'],
            'supplier payment summary' => ['GET', 'api/v1/tenant/suppliers/{supplierId}/payment-summary'],
            'purchase order payments' => ['GET', 'api/v1/tenant/purchase-orders/{poId}/payments'],
        ];
    }

    #[DataProvider('viewSuppliersRoutes')]
    public function test_supplier_read_route_requires_view_suppliers(string $method, string $uri): void
    {
        $this->assertContains('permission:view-suppliers,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('manageSuppliersRoutes')]
    public function test_supplier_mutation_route_requires_manage_suppliers(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-suppliers,tenant', $middleware);
        $this->assertContains('permission:manage-suppliers,tenant', $middleware);
    }

    #[DataProvider('viewSupplierPaymentsRoutes')]
    public function test_supplier_payment_read_route_requires_view_supplier_payments(string $method, string $uri): void
    {
        $this->assertContains('permission:view-supplier-payments,tenant', $this->middlewareFor($method, $uri));
    }

    public function test_supplier_payment_store_requires_manage_supplier_payments(): void
    {
        $middleware = $this->middlewareFor('POST', 'api/v1/tenant/supplier-payments');

        $this->assertContains('permission:view-supplier-payments,tenant', $middleware);
        $this->assertContains('permission:manage-supplier-payments,tenant', $middleware);
    }
}
