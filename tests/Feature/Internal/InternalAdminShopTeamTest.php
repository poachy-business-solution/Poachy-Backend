<?php

namespace Tests\Feature\Internal;

use App\Models\Tenant\Store;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** GET /api/internal/admin/shops/{shop}/team reads branches and staff from the shop's own database. */
class InternalAdminShopTeamTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    private const TOKEN = 'test-internal-admin-token-0123456789abcdef';

    public function test_branches_and_staff_for_any_admin_role(): void
    {
        $this->configureTenantHttpDatabases();
        Config::set('internal_admin.token', self::TOKEN);
        Config::set('internal_admin.allowed_ips', ['127.0.0.1']);
        $domain = 'iadmin-team-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);

        try {
            $tenant->run(function () {
                $this->createTenantUserWithRole('owner')->forceFill(['name' => 'Akinyi Owner'])->save();
                Store::query()->create(['name' => 'Main Branch', 'city' => 'Kisumu', 'address' => 'Kisumu', 'phone' => '+254700000001', 'email' => 'main@team.test', 'is_main_store' => true, 'is_active' => true]);
            });

            $headers = ['Authorization' => 'Bearer '.self::TOKEN, 'X-Admin-Id' => '3', 'X-Admin-Role' => 'support'];
            $this->getJson("/api/internal/admin/shops/{$tenant->id}/team", $headers)->assertOk()
                ->assertJsonPath('data.branches.0.name', 'Main Branch')
                ->assertJsonPath('data.branches.0.is_main', true)
                ->assertJsonPath('data.staff.0.name', 'Akinyi Owner')
                ->assertJsonPath('data.staff.0.roles', ['owner']);

            $this->getJson("/api/internal/admin/shops/{$tenant->id}/team")->assertUnauthorized();
            $this->getJson('/api/internal/admin/shops/no-such-shop/team', $headers)->assertNotFound();
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
