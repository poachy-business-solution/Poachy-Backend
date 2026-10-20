<?php

namespace Tests\Feature\Tenant\Marketplace;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * The shop's payouts are money: owners/managers only (view-financial-reports), signed in.
 */
class ShopPayoutRoutesTest extends TestCase
{
    public function test_payout_routes_need_a_signed_in_owner_or_manager(): void
    {
        foreach (['api/v1/tenant/marketplace/payouts', 'api/v1/tenant/marketplace/payouts/{id}'] as $uri) {
            $route = collect(RouteFacade::getRoutes())->first(fn (Route $r) => $r->uri() === $uri && in_array('GET', $r->methods(), true));
            $this->assertNotNull($route, $uri);
            $this->assertContains('permission:view-financial-reports,tenant', $route->middleware());
            $this->assertContains('auth:tenant', $route->gatherMiddleware());
        }
    }

    public function test_payout_callbacks_carry_a_secret_and_are_throttled(): void
    {
        foreach (['result', 'timeout'] as $kind) {
            $route = collect(RouteFacade::getRoutes())->first(fn (Route $r) => $r->uri() === "api/v1/central/mpesa/b2c/payout/{$kind}/{token}");
            $this->assertNotNull($route, $kind);
            $this->assertContains('throttle:60,1', $route->middleware());
        }
    }
}
