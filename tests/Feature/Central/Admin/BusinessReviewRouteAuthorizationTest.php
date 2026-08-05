<?php

namespace Tests\Feature\Central\Admin;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessReviewRouteAuthorizationTest extends TestCase
{
    public static function businessReviewRoutes(): array
    {
        return [
            'index' => ['GET', 'api/v1/central/business-details'],
            'pending' => ['GET', 'api/v1/central/business-details/pending'],
            'approve' => ['POST', 'api/v1/central/business-details/{id}/approve'],
            'reject' => ['POST', 'api/v1/central/business-details/{id}/reject'],
            'verify' => ['POST', 'api/v1/central/business-details/{id}/verify'],
        ];
    }

    /**
     * The auth:central guard is shared with marketplace customers, so before
     * this fix any logged-in customer could approve/verify a business —
     * only reject() had its own hasRole('admin') check inside its FormRequest.
     */
    #[DataProvider('businessReviewRoutes')]
    public function test_business_review_route_requires_admin_role(string $method, string $uri): void
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn (Route $route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertNotNull($route, "No route registered for {$method} {$uri}");
        $this->assertContains('role:admin', $route->middleware());
    }
}
