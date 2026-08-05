<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CleanupExpiredTenantOtpsCommandTest extends TestCase
{
    private string $tenantId;

    private string $tenantDatabase;

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

        $this->tenantId = 'otp-cleanup-test-'.uniqid();
        $this->tenantDatabase = 'poachy_tenant_'.$this->tenantId;

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$this->tenantDatabase}`");
        Config::set('database.connections.tenant.database', $this->tenantDatabase);
        DB::purge('tenant');

        Schema::connection('tenant')->create('tenant_otps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('otp_code');
            $table->string('type');
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$this->tenantDatabase}`");
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();

        parent::tearDown();
    }

    private function insertOtp(array $overrides = []): void
    {
        DB::connection('tenant')->table('tenant_otps')->insert(array_merge([
            'otp_code' => (string) random_int(100000, 999999),
            'type' => 'login',
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * tenancy()->end() (called when $tenant->run() completes) clears the
     * 'tenant' connection config entirely, not just its database name —
     * reconnect with a full config before asserting against it, rather than
     * assume it's still selected.
     */
    private function reconnectToTestTenantDatabase(): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => env('TENANT_DB_HOST', '127.0.0.1'),
            'port' => env('TENANT_DB_PORT', '3306'),
            'database' => $this->tenantDatabase,
            'username' => env('TENANT_DB_USERNAME', 'root'),
            'password' => env('TENANT_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);
        DB::purge('tenant');
    }

    public function test_deletes_expired_and_used_otps_for_specific_tenant(): void
    {
        $this->insertOtp(['expires_at' => now()->subMinutes(5)]);
        $this->insertOtp(['is_used' => true, 'used_at' => now()]);
        $this->insertOtp(['expires_at' => now()->addMinutes(10)]);

        $this->artisan('otp:cleanup-tenant', ['--tenant' => $this->tenantId])
            ->assertExitCode(0);

        $this->reconnectToTestTenantDatabase();
        $this->assertSame(1, DB::connection('tenant')->table('tenant_otps')->count());
    }

    public function test_fails_for_unknown_tenant_option(): void
    {
        $this->artisan('otp:cleanup-tenant', ['--tenant' => 'does-not-exist'])
            ->assertExitCode(1);
    }

    public function test_processes_tenant_when_iterating_all_tenants(): void
    {
        $this->insertOtp(['expires_at' => now()->subMinutes(5)]);

        $this->artisan('otp:cleanup-tenant')->assertExitCode(0);

        $this->reconnectToTestTenantDatabase();
        $this->assertSame(0, DB::connection('tenant')->table('tenant_otps')->count());
    }
}
