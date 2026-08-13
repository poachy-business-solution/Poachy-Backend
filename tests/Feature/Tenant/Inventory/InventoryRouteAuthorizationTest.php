<?php

namespace Tests\Feature\Tenant\Inventory;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function viewProductStoreRoutes(): array
    {
        return [
            'store products index' => ['GET', 'api/v1/tenant/stores/{store?}/products'],
            'store products stats' => ['GET', 'api/v1/tenant/stores/{store?}/products/stats'],
            'store product show' => ['GET', 'api/v1/tenant/stores/{store?}/products/{product}'],
        ];
    }

    public static function manageProductStoreRoutes(): array
    {
        return [
            'store product assign' => ['POST', 'api/v1/tenant/stores/{store?}/products'],
            'store product update' => ['PATCH', 'api/v1/tenant/stores/{store?}/products/{product}'],
            'store product availability' => ['PATCH', 'api/v1/tenant/stores/{store?}/products/{product}/availability'],
            'store product remove' => ['DELETE', 'api/v1/tenant/stores/{store?}/products/{product}'],
        ];
    }

    public static function viewInventoryRoutes(): array
    {
        return [
            'inventory index' => ['GET', 'api/v1/tenant/inventory'],
            'inventory check availability' => ['POST', 'api/v1/tenant/inventory/check-availability'],
            'low stock' => ['GET', 'api/v1/tenant/inventory/low-stock/list'],
            'out of stock' => ['GET', 'api/v1/tenant/inventory/out-of-stock/list'],
            'inventory value' => ['GET', 'api/v1/tenant/inventory/value/calculate'],
            'inventory summary' => ['GET', 'api/v1/tenant/inventory/summary'],
            'inventory show' => ['GET', 'api/v1/tenant/inventory/{id}'],
            'product inventory' => ['GET', 'api/v1/tenant/inventory/product/{productId}'],
            'movement index' => ['GET', 'api/v1/tenant/inventory-movements'],
            'movement show' => ['GET', 'api/v1/tenant/inventory-movements/{id}'],
            'reservation index' => ['GET', 'api/v1/tenant/inventory-reservations'],
            'reservation show' => ['GET', 'api/v1/tenant/inventory-reservations/{id}'],
            'transfer index' => ['GET', 'api/v1/tenant/transfers'],
            'transfer show' => ['GET', 'api/v1/tenant/transfers/{id}'],
            'purchase order index' => ['GET', 'api/v1/tenant/purchase-orders'],
            'purchase order show' => ['GET', 'api/v1/tenant/purchase-orders/{id}'],
            'batch index' => ['GET', 'api/v1/tenant/batches'],
            'batch valuation' => ['GET', 'api/v1/tenant/batches/valuation/calculate'],
            'batch cogs' => ['GET', 'api/v1/tenant/batches/cogs/calculate'],
            'batch show' => ['GET', 'api/v1/tenant/batches/{id}'],
            'serial index' => ['GET', 'api/v1/tenant/serials'],
            'serial lookup' => ['GET', 'api/v1/tenant/serials/lookup/{serialNumber}'],
            'serial show' => ['GET', 'api/v1/tenant/serials/{id}'],
        ];
    }

    public static function adjustStockRoutes(): array
    {
        return [
            'inventory adjustment' => ['POST', 'api/v1/tenant/inventory-movements/adjustment'],
            'inventory damage' => ['POST', 'api/v1/tenant/inventory-movements/damage'],
        ];
    }

    public static function transferStockRoutes(): array
    {
        return [
            'pending approvals' => ['GET', 'api/v1/tenant/transfers/pending/approvals'],
            'transfer store' => ['POST', 'api/v1/tenant/transfers'],
            'transfer approve' => ['POST', 'api/v1/tenant/transfers/{id}/approve'],
            'transfer send' => ['POST', 'api/v1/tenant/transfers/{id}/send'],
            'transfer receive' => ['POST', 'api/v1/tenant/transfers/{id}/receive'],
            'transfer cancel' => ['POST', 'api/v1/tenant/transfers/{id}/cancel'],
        ];
    }

    public static function manageInventoryRoutes(): array
    {
        return [
            'purchase order store' => ['POST', 'api/v1/tenant/purchase-orders'],
            'purchase order update' => ['PATCH', 'api/v1/tenant/purchase-orders/{id}'],
            'purchase order send' => ['POST', 'api/v1/tenant/purchase-orders/{id}/send'],
            'purchase order cancel' => ['POST', 'api/v1/tenant/purchase-orders/{id}/cancel'],
            'batch receive' => ['POST', 'api/v1/tenant/batches/receive'],
            'mark expired batches' => ['POST', 'api/v1/tenant/batches/expired/mark'],
        ];
    }

    public static function stockAlertRoutes(): array
    {
        return [
            'stock alert index' => ['GET', 'api/v1/tenant/stock-alerts'],
            'stock alert show' => ['GET', 'api/v1/tenant/stock-alerts/{id}'],
            'store stock alerts' => ['GET', 'api/v1/tenant/stores/{storeId}/stock-alerts'],
            'store stock alert summary' => ['GET', 'api/v1/tenant/stores/{storeId}/stock-alerts/summary'],
            'store stock alert dashboard' => ['GET', 'api/v1/tenant/stores/{storeId}/stock-alerts/dashboard'],
        ];
    }

    public static function expiryAlertRoutes(): array
    {
        return [
            'expiry alert index' => ['GET', 'api/v1/tenant/expiry-alerts'],
            'expiry alert show' => ['GET', 'api/v1/tenant/expiry-alerts/{id}'],
            'store expiry alerts' => ['GET', 'api/v1/tenant/stores/{storeId}/expiry-alerts'],
            'store expiry alert summary' => ['GET', 'api/v1/tenant/stores/{storeId}/expiry-alerts/summary'],
            'store expiry alert dashboard' => ['GET', 'api/v1/tenant/stores/{storeId}/expiry-alerts/dashboard'],
        ];
    }

    public static function viewWasteRoutes(): array
    {
        return [
            'waste index' => ['GET', 'api/v1/tenant/inventory-waste'],
            'waste show' => ['GET', 'api/v1/tenant/inventory-waste/{id}'],
            'store waste summary' => ['GET', 'api/v1/tenant/stores/{storeId}/inventory-waste/summary'],
        ];
    }

    public static function manageWasteRoutes(): array
    {
        return [
            'waste store' => ['POST', 'api/v1/tenant/inventory-waste'],
            'waste update' => ['PATCH', 'api/v1/tenant/inventory-waste/{id}'],
            'waste approve' => ['POST', 'api/v1/tenant/inventory-waste/{id}/approve'],
            'waste reject' => ['POST', 'api/v1/tenant/inventory-waste/{id}/reject'],
            'waste destroy' => ['DELETE', 'api/v1/tenant/inventory-waste/{id}'],
        ];
    }

    #[DataProvider('viewProductStoreRoutes')]
    public function test_store_product_read_route_requires_view_products(string $method, string $uri): void
    {
        $this->assertContains('permission:view-products,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('manageProductStoreRoutes')]
    public function test_store_product_mutation_route_requires_manage_products(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-products,tenant', $middleware);
        $this->assertContains('permission:manage-products,tenant', $middleware);
    }

    #[DataProvider('viewInventoryRoutes')]
    public function test_inventory_read_route_requires_view_inventory(string $method, string $uri): void
    {
        $this->assertContains('permission:view-inventory,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('adjustStockRoutes')]
    public function test_inventory_adjustment_route_requires_adjust_stock(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-inventory,tenant', $middleware);
        $this->assertContains('permission:adjust-stock,tenant', $middleware);
    }

    #[DataProvider('transferStockRoutes')]
    public function test_transfer_route_requires_transfer_stock(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-inventory,tenant', $middleware);
        $this->assertContains('permission:transfer-stock,tenant', $middleware);
    }

    #[DataProvider('manageInventoryRoutes')]
    public function test_manage_inventory_route_requires_manage_inventory(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-inventory,tenant', $middleware);
        $this->assertContains('permission:manage-inventory,tenant', $middleware);
    }

    #[DataProvider('stockAlertRoutes')]
    public function test_stock_alert_read_route_requires_view_stock_alerts(string $method, string $uri): void
    {
        $this->assertContains('permission:view-stock-alerts,tenant', $this->middlewareFor($method, $uri));
    }

    public function test_stock_alert_resolve_route_requires_resolve_stock_alerts(): void
    {
        $middleware = $this->middlewareFor('POST', 'api/v1/tenant/stock-alerts/{id}/resolve');

        $this->assertContains('permission:view-stock-alerts,tenant', $middleware);
        $this->assertContains('permission:resolve-stock-alerts,tenant', $middleware);
    }

    #[DataProvider('expiryAlertRoutes')]
    public function test_expiry_alert_read_route_requires_view_expiry_alerts(string $method, string $uri): void
    {
        $this->assertContains('permission:view-expiry-alerts,tenant', $this->middlewareFor($method, $uri));
    }

    public function test_expiry_alert_resolve_route_requires_resolve_expiry_alerts(): void
    {
        $middleware = $this->middlewareFor('POST', 'api/v1/tenant/expiry-alerts/{id}/resolve');

        $this->assertContains('permission:view-expiry-alerts,tenant', $middleware);
        $this->assertContains('permission:resolve-expiry-alerts,tenant', $middleware);
    }

    #[DataProvider('viewWasteRoutes')]
    public function test_waste_read_route_requires_view_waste_records(string $method, string $uri): void
    {
        $this->assertContains('permission:view-waste-records,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('manageWasteRoutes')]
    public function test_waste_mutation_route_requires_manage_waste_records(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-waste-records,tenant', $middleware);
        $this->assertContains('permission:manage-waste-records,tenant', $middleware);
    }
}
