<?php

namespace Tests\Feature\Central\Admin;

use App\Mail\Tenant\Auth\TenantCredentialsMail;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Central\Admin\Tenant\TenantUserService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * createTenantUser() is the "admin bootstraps a brand-new tenant's first owner"
 * path — it calls a *real* tenancy()->initialize($tenant) against a real,
 * migrated+seeded per-tenant database, unlike every other TenantUserService
 * method (covered in tests/Feature/Tenant/User/TenantUserServiceTest.php via a
 * faked TenantContract). That file's scope note deliberately deferred this
 * method for "whenever Central admin tenant-provisioning gets its own pass" —
 * this is that pass, sharing the real-tenant harness built for TenantServiceTest.
 */
class TenantUserServiceCreateTenantUserTest extends TestCase
{
    private ?Tenant $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');

        Mail::fake();
    }

    protected function tearDown(): void
    {
        if ($this->tenant) {
            $this->tenant->delete();
        }

        parent::tearDown();
    }

    private function makeService(): TenantUserService
    {
        return new TenantUserService;
    }

    /**
     * Real provisioning (~15-20s): database creation + migration + seeding,
     * which is what makes assignRole('owner') resolvable below — the seeded
     * TenantRolesAndPermissionsSeeder already created that role for real.
     * Only called by tests that actually need a live tenant database, so the
     * unknown-tenant guard test stays fast.
     */
    private function provisionRealTenant(): Tenant
    {
        $this->tenant = Tenant::create([]);
        $this->tenant->domains()->create(['domain' => 'create-user-test-'.uniqid().'.poachy.test']);

        return $this->tenant;
    }

    public function test_creates_owner_user_in_tenant_database_and_sends_credentials(): void
    {
        $this->provisionRealTenant();

        $user = $this->makeService()->createTenantUser($this->tenant->id, [
            'name' => 'First Owner',
            'email' => 'owner@example.com',
            'phone' => '0712345678',
        ]);

        $this->assertInstanceOf(TenantUser::class, $user);
        $this->assertNotEmpty($user->password);

        Mail::assertQueued(TenantCredentialsMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // createTenantUser()'s finally block already ran tenancy()->end(), which
        // (by Stancl design — DatabaseManager::purgeTenantConnection()) nulls out
        // config('database.connections.tenant') so any stray post-tenancy query
        // against it fails loudly instead of silently hitting a stale database.
        // Re-point it at this tenant's real database before asserting further —
        // $user's lazy-loaded 'roles' relation would otherwise throw too. The
        // whole 'tenant' key was nulled (not just its 'database' sub-key), so
        // the full connection array has to be rebuilt, not patched.
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => env('TENANT_DB_HOST', '127.0.0.1'),
            'port' => env('TENANT_DB_PORT', '3306'),
            'database' => $this->tenant->getDatabaseName(),
            'username' => env('TENANT_DB_USERNAME', 'root'),
            'password' => env('TENANT_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);
        DB::purge('tenant');

        $this->assertTrue($user->hasRole('owner'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'owner@example.com'], 'tenant');
    }

    public function test_ends_tenancy_context_even_though_it_ran_successfully(): void
    {
        $this->provisionRealTenant();

        $this->makeService()->createTenantUser($this->tenant->id, [
            'name' => 'First Owner', 'email' => 'owner2@example.com',
        ]);

        $this->assertNull(tenant(), 'tenancy should be ended after createTenantUser() returns');
    }

    public function test_skips_credentials_email_when_send_credentials_is_false(): void
    {
        $this->provisionRealTenant();

        $this->makeService()->createTenantUser($this->tenant->id, [
            'name' => 'Silent Owner', 'email' => 'owner3@example.com', 'send_credentials' => false,
        ]);

        Mail::assertNotQueued(TenantCredentialsMail::class);
    }

    public function test_throws_for_unknown_tenant(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->makeService()->createTenantUser('does-not-exist', [
            'name' => 'Nobody', 'email' => 'nobody@example.com',
        ]);
    }
}
