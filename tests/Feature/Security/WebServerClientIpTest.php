<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Item 44: per-IP limits must apply per visitor. The website's server forwards the visitor's IP
 * and is believed only with its key; anyone else's X-Forwarded-For is ignored.
 */
class WebServerClientIpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.web.server_key', 'web-server-key-for-tests-0123456789');
        Route::get('/api/_test/ip', fn (Request $request) => response()->json(['ip' => $request->ip(), 'key' => $request->headers->has('X-Poachy-Web-Key')]));
    }

    public function test_the_web_server_forwards_the_visitor_ip_and_nobody_else_can(): void
    {
        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '41.90.1.2', 'X-Poachy-Web-Key' => 'web-server-key-for-tests-0123456789'])
            ->assertJson(['ip' => '41.90.1.2', 'key' => false]); // the key never reaches the app
        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '41.90.1.2'])->assertJson(['ip' => '127.0.0.1']);
        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '41.90.1.2', 'X-Poachy-Web-Key' => 'wrong'])->assertJson(['ip' => '127.0.0.1']);
        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => 'not-an-ip', 'X-Poachy-Web-Key' => 'web-server-key-for-tests-0123456789'])->assertJson(['ip' => '127.0.0.1']);

        Config::set('services.web.server_key', '');
        $this->getJson('/api/_test/ip', ['X-Forwarded-For' => '41.90.1.2', 'X-Poachy-Web-Key' => ''])->assertJson(['ip' => '127.0.0.1']);
    }

    public function test_a_forwarded_private_or_loopback_address_is_never_believed(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '172.17.0.1', '192.168.1.10', '::1'] as $forged) {
            $this->withServerVariables(['REMOTE_ADDR' => '41.90.1.9'])
                ->getJson('/api/_test/ip', ['X-Forwarded-For' => $forged, 'X-Poachy-Web-Key' => 'web-server-key-for-tests-0123456789'])
                ->assertJson(['ip' => '41.90.1.9']);
        }
    }

    public function test_the_private_admin_api_checks_the_connections_own_address(): void
    {
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        \Illuminate\Support\Facades\DB::purge('central');
        \Illuminate\Support\Facades\DB::setDefaultConnection('central');
        Config::set('internal_admin.token', str_repeat('t', 40));
        Config::set('internal_admin.allowed_ips', ['41.90.1.2']);
        $headers = ['Authorization' => 'Bearer '.str_repeat('t', 40), 'X-Admin-Id' => '7', 'X-Admin-Role' => 'super'];

        // A visitor IP forwarded by the website (even an allow-listed one) does not open the admin API.
        $this->withServerVariables(['REMOTE_ADDR' => '41.90.1.9'])
            ->getJson('/api/internal/admin/marketplace/fees', $headers + ['X-Forwarded-For' => '41.90.1.2', 'X-Poachy-Web-Key' => 'web-server-key-for-tests-0123456789'])
            ->assertNotFound();
    }
}
