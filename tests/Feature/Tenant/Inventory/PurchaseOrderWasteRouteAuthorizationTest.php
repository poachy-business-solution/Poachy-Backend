<?php

namespace Tests\Feature\Tenant\Inventory;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseOrderWasteRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function manageInventoryGatedPurchaseOrderRoutes(): array
    {
        return [
            'send' => ['POST', 'api/v1/tenant/purchase-orders/{id}/send'],
            'cancel' => ['POST', 'api/v1/tenant/purchase-orders/{id}/cancel'],
        ];
    }

    #[DataProvider('manageInventoryGatedPurchaseOrderRoutes')]
    public function test_purchase_order_route_requires_manage_inventory(string $method, string $uri): void
    {
        $this->assertContains('permission:manage-inventory,tenant', $this->middlewareFor($method, $uri));
    }

    public function test_inventory_waste_destroy_requires_manage_waste_records(): void
    {
        $this->assertContains(
            'permission:manage-waste-records,tenant',
            $this->middlewareFor('DELETE', 'api/v1/tenant/inventory-waste/{id}')
        );
    }
}
