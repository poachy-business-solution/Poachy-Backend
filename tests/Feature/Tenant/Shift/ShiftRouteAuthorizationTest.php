<?php

namespace Tests\Feature\Tenant\Shift;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShiftRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public function test_shift_destroy_uses_delete_policy(): void
    {
        $this->assertContains('can:delete,shift', $this->middlewareFor('DELETE', 'api/v1/tenant/shifts/{shift}'));
    }

    public function test_shift_toggle_active_uses_update_policy(): void
    {
        $this->assertContains('can:update,shift', $this->middlewareFor('POST', 'api/v1/tenant/shifts/{shift}/toggle-active'));
    }

    public function test_shift_duplicate_uses_create_policy(): void
    {
        $this->assertContains(
            'can:create,App\Models\Tenant\Shift',
            $this->middlewareFor('POST', 'api/v1/tenant/shifts/{shift}/duplicate')
        );
    }

    public static function managerOnlyShiftReportingRoutes(): array
    {
        return [
            'shift statistics' => ['GET', 'api/v1/tenant/shifts/statistics'],
            'assignment statistics' => ['GET', 'api/v1/tenant/shift-assignments/statistics'],
            'assignments needing approval' => ['GET', 'api/v1/tenant/shift-assignments/needing-approval'],
            'store assignments' => ['GET', 'api/v1/tenant/stores/{storeId}/shift-assignments'],
            'attendance rate' => ['GET', 'api/v1/tenant/shift-analytics/attendance-rate'],
            'cash variances' => ['GET', 'api/v1/tenant/shift-analytics/cash-variances'],
            'top performers' => ['GET', 'api/v1/tenant/shift-analytics/top-performers'],
            'coverage report' => ['GET', 'api/v1/tenant/shift-analytics/coverage-report'],
            'overtime analysis' => ['GET', 'api/v1/tenant/shift-analytics/overtime-analysis'],
            'punctuality analysis' => ['GET', 'api/v1/tenant/shift-analytics/punctuality-analysis'],
            'dashboard summary' => ['GET', 'api/v1/tenant/shift-analytics/dashboard-summary'],
            'shift swap index' => ['GET', 'api/v1/tenant/shift-swaps'],
            'shift swap statistics' => ['GET', 'api/v1/tenant/shift-swaps/statistics'],
        ];
    }

    #[DataProvider('managerOnlyShiftReportingRoutes')]
    public function test_shift_reporting_route_requires_manager_role(string $method, string $uri): void
    {
        $this->assertContains(
            'role:owner|manager|admin,tenant',
            $this->middlewareFor($method, $uri)
        );
    }
}
