<?php

namespace Tests\Feature\Tenant\Sales;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesReportRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function dailySalesReportRoutes(): array
    {
        return [
            'index' => ['GET', 'api/v1/tenant/reports/daily-sales'],
            'range' => ['GET', 'api/v1/tenant/reports/daily-sales/range'],
            'summary' => ['GET', 'api/v1/tenant/reports/daily-sales/summary'],
            'top selling' => ['GET', 'api/v1/tenant/reports/daily-sales/top-selling'],
            'top revenue' => ['GET', 'api/v1/tenant/reports/daily-sales/top-revenue'],
            'by category' => ['GET', 'api/v1/tenant/reports/daily-sales/by-category'],
            'recalculate' => ['POST', 'api/v1/tenant/reports/daily-sales/recalculate'],
        ];
    }

    #[DataProvider('dailySalesReportRoutes')]
    public function test_daily_sales_report_route_requires_view_financial_reports(string $method, string $uri): void
    {
        $this->assertContains('permission:view-financial-reports,tenant', $this->middlewareFor($method, $uri));
    }

    public static function shiftSalesSummaryRoutes(): array
    {
        return [
            'show' => ['GET', 'api/v1/tenant/shifts/{shiftAssignment}/sales-summary'],
            'recalculate' => ['POST', 'api/v1/tenant/shifts/{shiftAssignment}/recalculate-summary'],
            'cash reconciliation' => ['GET', 'api/v1/tenant/shifts/{shiftAssignment}/cash-reconciliation'],
        ];
    }

    #[DataProvider('shiftSalesSummaryRoutes')]
    public function test_shift_sales_summary_route_requires_view_sales_reports(string $method, string $uri): void
    {
        $this->assertContains('permission:view-sales-reports,tenant', $this->middlewareFor($method, $uri));
    }
}
