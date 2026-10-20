<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/** Item 44: every tenant↔central sync endpoint checks the shared server-to-server token. */
class SyncTokenTest extends TestCase
{
    public function test_every_sync_route_carries_the_token_check(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes());
        $central = $routes->filter(fn (Route $r) => str_starts_with($r->uri(), 'api/v1/central/sync/') || str_ends_with($r->uri(), '/delivery/location'));
        $tenant = $routes->filter(fn (Route $r) => str_starts_with($r->uri(), 'api/v1/tenant/sync/inbound/'));

        $this->assertGreaterThanOrEqual(12, $central->count());
        $this->assertGreaterThanOrEqual(9, $tenant->count());
        foreach ($central as $route) {
            $this->assertContains('sync.token:central', $route->gatherMiddleware(), $route->uri());
        }
        foreach ($tenant as $route) {
            $this->assertContains('sync.token:tenant', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_a_missing_wrong_or_unset_token_is_refused_before_anything_else(): void
    {
        Config::set('database.default', 'central'); // Sanctum's bearer lookup uses the default connection
        Config::set('services.central_api.token', 'central-sync-token-for-tests');
        $url = '/api/v1/central/sync/inbound/product';

        $this->postJson($url, [])->assertForbidden()->assertJsonPath('message', 'Invalid sync token.');
        $this->postJson($url, [], ['Authorization' => 'Bearer nope'])->assertForbidden();
        $this->getJson('/api/v1/central/sync/inbound/1/status', ['Authorization' => 'Bearer nope'])->assertForbidden();

        Config::set('services.central_api.token', '');
        $this->postJson($url, [], ['Authorization' => 'Bearer '])->assertForbidden();
    }
}
