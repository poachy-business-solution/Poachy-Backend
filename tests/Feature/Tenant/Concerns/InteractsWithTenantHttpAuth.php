<?php

namespace Tests\Feature\Tenant\Concerns;

use App\Models\BusinessDetail;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Services\Central\Admin\Tenant\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithTenantHttpAuth
{
    protected function configureTenantHttpDatabases(): void
    {
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy_central_test'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('database.connections.tenant.host', env('TENANT_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.tenant.port', env('TENANT_DB_PORT', '3306'));
        Config::set('database.connections.tenant.username', env('TENANT_DB_USERNAME', 'root'));
        Config::set('database.connections.tenant.password', env('TENANT_DB_PASSWORD', ''));
        DB::purge('central');
        DB::purge('tenant');
        DB::setDefaultConnection('central');
    }

    protected function createTenantHttpFixture(string $domain): Tenant
    {
        $this->deleteTenantHttpFixture($domain);

        $tenant = (new TenantService)->createTenant([
            'domain' => $domain,
            'tenant_name' => 'HTTP Smoke Tenant',
            'notes' => 'Created by TenantRouteMiddlewareSmokeTest',
        ]);

        $this->createTenantAccessRows($tenant);

        return $tenant;
    }

    protected function deleteTenantHttpFixture(string $domain): void
    {
        $tenantIds = DB::connection('central')
            ->table('domains')
            ->where('domain', $domain)
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            (new TenantService)->deleteTenant((string) $tenantId);
        }
    }

    protected function tenantHeaders(string $domain): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    protected function tenantUrl(string $domain, string $uri): string
    {
        return 'http://'.$domain.'/'.ltrim($uri, '/');
    }

    protected function createTenantUserWithRole(string $roleName): User
    {
        config(['permission.connection' => 'tenant']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create([
            'name' => 'Tenant HTTP '.ucfirst($roleName),
            'email' => sprintf('tenant-http-%s-%s@example.com', $roleName, uniqid()),
            'password' => Hash::make('password'),
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        $user->assignRole($roleName);

        return $user;
    }

    protected function createTenantUserWithoutRole(): User
    {
        config(['permission.connection' => 'tenant']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return User::create([
            'name' => 'Tenant HTTP Unprivileged',
            'email' => sprintf('tenant-http-unprivileged-%s@example.com', uniqid()),
            'password' => Hash::make('password'),
            'phone' => '0712345678',
            'is_active' => true,
        ]);
    }

    protected function actingAsTenant(User $user): void
    {
        Sanctum::actingAs($user, ['*'], 'tenant');
    }

    private function createTenantAccessRows(Tenant $tenant): void
    {
        $plan = SubscriptionPlan::on('central')
            ->where('is_active', true)
            ->orderBy('price')
            ->firstOrFail();

        $businessTypeId = DB::connection('central')->table('business_types')->value('id');
        $businessCategoryId = DB::connection('central')->table('business_categories')->value('id');

        BusinessDetail::on('central')->create([
            'tenant_id' => $tenant->id,
            'business_name' => 'HTTP Smoke Tenant',
            'business_type_id' => $businessTypeId,
            'business_category_id' => $businessCategoryId,
            'business_email' => 'tenant-http@example.com',
            'business_phone' => '+254700000000',
            'contact_person' => 'Tenant HTTP',
            'status' => 'active',
            'is_verified' => true,
            'verified_at' => now(),
            'onboarded_at' => now(),
        ]);

        BusinessSubscription::on('central')->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount_paid' => 0,
            'currency' => 'KES',
            'status' => 'active',
            'auto_renew' => false,
            'is_trial' => false,
        ]);

        Cache::forget("tenant_access:{$tenant->id}");
    }
}
