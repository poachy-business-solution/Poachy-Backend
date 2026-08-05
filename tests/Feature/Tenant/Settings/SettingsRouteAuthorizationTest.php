<?php

namespace Tests\Feature\Tenant\Settings;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function storeSettingsGatedRoutes(): array
    {
        return [
            'tax rate store' => ['POST', 'api/v1/tenant/tax-rates'],
            'tax rate toggle-active' => ['PATCH', 'api/v1/tenant/tax-rates/{taxRate}/toggle-active'],
            'tax rate toggle-default' => ['PATCH', 'api/v1/tenant/tax-rates/{taxRate}/toggle-default'],
            'tax rate effective-until' => ['PATCH', 'api/v1/tenant/tax-rates/{taxRate}/effective-until'],
            'uom store' => ['POST', 'api/v1/tenant/units-of-measure'],
            'uom update' => ['PATCH', 'api/v1/tenant/units-of-measure/{id}'],
            'uom set-base-unit' => ['POST', 'api/v1/tenant/units-of-measure/{id}/set-base-unit'],
            'uom remove-base-unit' => ['DELETE', 'api/v1/tenant/units-of-measure/{id}/remove-base-unit'],
            'uom conversion store' => ['POST', 'api/v1/tenant/uom-conversions'],
            'uom conversion update' => ['PATCH', 'api/v1/tenant/uom-conversions/{id}'],
            'uom conversion destroy' => ['DELETE', 'api/v1/tenant/uom-conversions/{id}'],
        ];
    }

    #[DataProvider('storeSettingsGatedRoutes')]
    public function test_mutating_settings_route_requires_manage_store_settings(string $method, string $uri): void
    {
        $this->assertContains('permission:manage-store-settings,tenant', $this->middlewareFor($method, $uri));
    }

    public static function openSettingsReadRoutes(): array
    {
        return [
            'tax rate index' => ['GET', 'api/v1/tenant/tax-rates'],
            'uom index' => ['GET', 'api/v1/tenant/units-of-measure'],
            'uom show' => ['GET', 'api/v1/tenant/units-of-measure/{id}'],
            'uom conversion-options' => ['GET', 'api/v1/tenant/units-of-measure/{id}/conversion-options'],
            'uom conversion convert' => ['POST', 'api/v1/tenant/uom-conversions/convert'],
        ];
    }

    #[DataProvider('openSettingsReadRoutes')]
    public function test_read_only_settings_route_does_not_require_manage_store_settings(string $method, string $uri): void
    {
        $this->assertNotContains('permission:manage-store-settings,tenant', $this->middlewareFor($method, $uri));
    }

    public static function financialReportsGatedBudgetRoutes(): array
    {
        return [
            'index' => ['GET', 'api/v1/tenant/budgets'],
            'current' => ['GET', 'api/v1/tenant/budgets/current'],
            'alerts' => ['GET', 'api/v1/tenant/budgets/alerts'],
            'over budget' => ['GET', 'api/v1/tenant/budgets/over-budget'],
            'performance' => ['GET', 'api/v1/tenant/budgets/performance'],
            'show' => ['GET', 'api/v1/tenant/budgets/{budget}'],
            'expenses' => ['GET', 'api/v1/tenant/budgets/{budget}/expenses'],
            'store' => ['POST', 'api/v1/tenant/budgets'],
            'update' => ['PATCH', 'api/v1/tenant/budgets/{budget}'],
            'destroy' => ['DELETE', 'api/v1/tenant/budgets/{budget}'],
            'recalculate' => ['POST', 'api/v1/tenant/budgets/{budget}/recalculate'],
        ];
    }

    #[DataProvider('financialReportsGatedBudgetRoutes')]
    public function test_budget_route_requires_view_financial_reports(string $method, string $uri): void
    {
        $this->assertContains('permission:view-financial-reports,tenant', $this->middlewareFor($method, $uri));
    }

    public static function manageExpensesGatedBudgetRoutes(): array
    {
        return [
            'store' => ['POST', 'api/v1/tenant/budgets'],
            'update' => ['PATCH', 'api/v1/tenant/budgets/{budget}'],
            'destroy' => ['DELETE', 'api/v1/tenant/budgets/{budget}'],
            'recalculate' => ['POST', 'api/v1/tenant/budgets/{budget}/recalculate'],
        ];
    }

    #[DataProvider('manageExpensesGatedBudgetRoutes')]
    public function test_budget_mutation_route_additionally_requires_manage_expenses(string $method, string $uri): void
    {
        $this->assertContains('permission:manage-expenses,tenant', $this->middlewareFor($method, $uri));
    }

    public static function readOnlyBudgetRoutes(): array
    {
        return [
            'index' => ['GET', 'api/v1/tenant/budgets'],
            'show' => ['GET', 'api/v1/tenant/budgets/{budget}'],
        ];
    }

    #[DataProvider('readOnlyBudgetRoutes')]
    public function test_read_only_budget_route_does_not_require_manage_expenses(string $method, string $uri): void
    {
        $this->assertNotContains('permission:manage-expenses,tenant', $this->middlewareFor($method, $uri));
    }
}
