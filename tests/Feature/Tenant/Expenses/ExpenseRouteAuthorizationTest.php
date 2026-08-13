<?php

namespace Tests\Feature\Tenant\Expenses;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpenseRouteAuthorizationTest extends TestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");

        return $route->middleware();
    }

    public static function viewExpenseRoutes(): array
    {
        return [
            'expense category index' => ['GET', 'api/v1/tenant/expense-categories'],
            'expense category tree' => ['GET', 'api/v1/tenant/expense-categories/tree'],
            'expense category recurring eligible' => ['GET', 'api/v1/tenant/expense-categories/recurring-eligible'],
            'expense category show' => ['GET', 'api/v1/tenant/expense-categories/{expense_category}'],
            'expense category children' => ['GET', 'api/v1/tenant/expense-categories/{expense_category}/children'],
            'expense index' => ['GET', 'api/v1/tenant/expenses'],
            'expense pending approval' => ['GET', 'api/v1/tenant/expenses/pending-approval'],
            'expense analytics' => ['GET', 'api/v1/tenant/expenses/analytics'],
            'expense show' => ['GET', 'api/v1/tenant/expenses/{expense}'],
            'expense recurrences' => ['GET', 'api/v1/tenant/expenses/{expense}/recurrences'],
        ];
    }

    public static function manageExpenseRoutes(): array
    {
        return [
            'expense category store' => ['POST', 'api/v1/tenant/expense-categories'],
            'expense category update' => ['PATCH', 'api/v1/tenant/expense-categories/{expense_category}'],
            'expense category destroy' => ['DELETE', 'api/v1/tenant/expense-categories/{expense_category}'],
            'expense category toggle' => ['POST', 'api/v1/tenant/expense-categories/{expense_category}/toggle-active'],
            'expense store' => ['POST', 'api/v1/tenant/expenses'],
            'expense update' => ['PATCH', 'api/v1/tenant/expenses/{expense}'],
            'expense destroy' => ['DELETE', 'api/v1/tenant/expenses/{expense}'],
            'expense approve' => ['POST', 'api/v1/tenant/expenses/{expense}/approve'],
            'expense reject' => ['POST', 'api/v1/tenant/expenses/{expense}/reject'],
            'expense upload receipt' => ['POST', 'api/v1/tenant/expenses/{expense}/upload-receipt'],
            'expense delete receipt' => ['DELETE', 'api/v1/tenant/expenses/{expense}/delete-receipt'],
            'expense set recurrence' => ['POST', 'api/v1/tenant/expenses/{expense}/set-recurrence'],
            'expense update recurrence' => ['PATCH', 'api/v1/tenant/expenses/{expense}/update-recurrence'],
            'expense cancel recurrence' => ['POST', 'api/v1/tenant/expenses/{expense}/cancel-recurrence'],
            'expense generate recurrence' => ['POST', 'api/v1/tenant/expenses/{expense}/generate-recurrence'],
        ];
    }

    #[DataProvider('viewExpenseRoutes')]
    public function test_expense_read_route_requires_view_expenses(string $method, string $uri): void
    {
        $this->assertContains('permission:view-expenses,tenant', $this->middlewareFor($method, $uri));
    }

    #[DataProvider('manageExpenseRoutes')]
    public function test_expense_mutation_route_requires_manage_expenses(string $method, string $uri): void
    {
        $middleware = $this->middlewareFor($method, $uri);

        $this->assertContains('permission:view-expenses,tenant', $middleware);
        $this->assertContains('permission:manage-expenses,tenant', $middleware);
    }
}
