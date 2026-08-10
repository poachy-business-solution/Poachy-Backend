<?php

namespace Tests\Feature\Central;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicWorkspaceLookupTest extends TestCase
{
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('tenancy.central_domains', ['poachy.test']);
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'workspace-lookup-'.uniqid();
    }

    protected function tearDown(): void
    {
        DB::connection('central')->table('business_details')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    public function test_public_workspace_lookup_resolves_by_subdomain_slug_without_authentication(): void
    {
        $this->insertWorkspace(
            domain: 'quick-shop.poachy.test',
            businessName: 'Quick Shop Ltd',
            businessEmail: 'quick-shop@example.test'
        );

        $response = $this->getJson('/api/v1/central/workspaces/lookup?q=quick-shop');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Workspace found successfully')
            ->assertJsonPath('data.workspace.tenant_id', $this->tenantId)
            ->assertJsonPath('data.workspace.name', 'Quick Shop Ltd')
            ->assertJsonPath('data.workspace.domain', 'quick-shop.poachy.test')
            ->assertJsonPath('data.workspace.login_url', 'https://quick-shop.poachy.test/login')
            ->assertJsonPath('data.workspace.business.status', 'active');

        $workspace = $response->json('data.workspace');
        $this->assertArrayNotHasKey('database_name', $workspace);
        $this->assertArrayNotHasKey('domains', $workspace);
        $this->assertArrayNotHasKey('metadata', $workspace);
    }

    public function test_public_workspace_lookup_resolves_by_exact_business_email(): void
    {
        $this->insertWorkspace(
            domain: 'email-shop.poachy.test',
            businessName: 'Email Shop Ltd',
            businessEmail: 'owner@email-shop.example'
        );

        $response = $this->getJson('/api/v1/central/workspaces/lookup?q=OWNER@email-shop.example');

        $response->assertOk()
            ->assertJsonPath('data.workspace.tenant_id', $this->tenantId)
            ->assertJsonPath('data.workspace.name', 'Email Shop Ltd')
            ->assertJsonPath('data.workspace.domain', 'email-shop.poachy.test');
    }

    public function test_public_workspace_lookup_returns_404_for_unknown_identifier(): void
    {
        $response = $this->getJson('/api/v1/central/workspaces/lookup?q=missing-workspace');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Workspace not found.');
    }

    private function insertWorkspace(string $domain, string $businessName, string $businessEmail): void
    {
        DB::connection('central')->table('tenants')->insert([
            'id' => $this->tenantId,
            'data' => json_encode(['tenant_name' => $businessName]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('domains')->insert([
            'domain' => $domain,
            'is_primary' => true,
            'tenant_id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('business_details')->insert([
            'tenant_id' => $this->tenantId,
            'business_name' => $businessName,
            'business_type_id' => 1,
            'business_category_id' => 1,
            'business_email' => $businessEmail,
            'business_phone' => '0712345678',
            'city' => 'Nairobi',
            'county' => 'Nairobi',
            'status' => 'active',
            'is_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
