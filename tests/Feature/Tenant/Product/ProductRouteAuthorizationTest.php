<?php

namespace Tests\Feature\Tenant\Product;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function manageProductsGatedRoutes(): array
    {
        return [
            'category store' => ['POST', 'api/v1/tenant/categories'],
            'category update' => ['PATCH', 'api/v1/tenant/categories/{category}'],
            'category activate' => ['PATCH', 'api/v1/tenant/categories/{category}/activate'],
            'category deactivate' => ['PATCH', 'api/v1/tenant/categories/{category}/deactivate'],
            'category destroy' => ['DELETE', 'api/v1/tenant/categories/{category}'],
            'brand store' => ['POST', 'api/v1/tenant/brands'],
            'brand activate' => ['PATCH', 'api/v1/tenant/brands/{brand}/activate'],
            'brand deactivate' => ['PATCH', 'api/v1/tenant/brands/{brand}/deactivate'],
            'brand feature' => ['PATCH', 'api/v1/tenant/brands/{brand}/feature'],
            'brand unfeature' => ['PATCH', 'api/v1/tenant/brands/{brand}/unfeature'],
            'brand logo' => ['POST', 'api/v1/tenant/brands/{brand}/logo'],
            'brand destroy' => ['DELETE', 'api/v1/tenant/brands/{brand}'],
            'product toggle-active' => ['PATCH', 'api/v1/tenant/products/{uuid}/toggle-active'],
            'product toggle-featured' => ['PATCH', 'api/v1/tenant/products/{uuid}/toggle-featured'],
            'product primary-image' => ['POST', 'api/v1/tenant/products/{uuid}/primary-image'],
            'product uom destroy' => ['DELETE', 'api/v1/tenant/products/{uuid}/uoms/{productUomId}'],
            'variant destroy' => ['DELETE', 'api/v1/tenant/variants/{id}'],
            'variant toggle-active' => ['PATCH', 'api/v1/tenant/variants/{id}/toggle-active'],
            'bundle destroy' => ['DELETE', 'api/v1/tenant/bundles/{id}'],
            'bundle remove item' => ['DELETE', 'api/v1/tenant/bundles/{id}/items/{itemId}'],
            'bundle toggle-active' => ['PATCH', 'api/v1/tenant/bundles/{id}/toggle-active'],
            'bundle toggle-online' => ['PATCH', 'api/v1/tenant/bundles/{id}/toggle-online'],
            'bundle remove image' => ['DELETE', 'api/v1/tenant/bundles/{id}/images'],
        ];
    }

    #[DataProvider('manageProductsGatedRoutes')]
    public function test_route_requires_manage_products(string $method, string $uri): void
    {
        $this->assertContains('permission:manage-products,tenant', $this->middlewareFor($method, $uri));
    }

    public static function openReadRoutes(): array
    {
        return [
            'category index' => ['GET', 'api/v1/tenant/categories'],
            'category show' => ['GET', 'api/v1/tenant/categories/{category}'],
            'brand index' => ['GET', 'api/v1/tenant/brands'],
            'brand show' => ['GET', 'api/v1/tenant/brands/{brand}'],
            'variant show' => ['GET', 'api/v1/tenant/variants/{id}'],
            'bundle show' => ['GET', 'api/v1/tenant/bundles/{id}'],
        ];
    }

    #[DataProvider('openReadRoutes')]
    public function test_read_only_route_does_not_require_manage_products(string $method, string $uri): void
    {
        $this->assertNotContains('permission:manage-products,tenant', $this->middlewareFor($method, $uri));
    }
}
