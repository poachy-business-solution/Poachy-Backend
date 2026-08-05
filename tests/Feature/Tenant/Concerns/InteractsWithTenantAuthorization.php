<?php

namespace Tests\Feature\Tenant\Concerns;

use App\Models\Tenant\User as TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

trait InteractsWithTenantAuthorization
{
    protected function setUpTenantAuthorization(): void
    {
        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createTenantAuthorizationSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDownTenantAuthorization(): void
    {
        $this->dropTenantAuthorizationSchema();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
    }

    protected function createTenantAuthorizationSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection($conn)->create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection($conn)->create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection($conn)->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::connection($conn)->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::connection($conn)->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    protected function dropTenantAuthorizationSchema(): void
    {
        foreach ([
            'role_has_permissions', 'model_has_roles', 'model_has_permissions',
            'roles', 'permissions', 'users',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    protected function makeTenantUser(array $overrides = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'user-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ], $overrides));
    }

    protected function makeTenantUserWithPermission(string $permission): TenantUser
    {
        $user = $this->makeTenantUser();
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant'])
        );

        return $user;
    }

    protected function makeTenantUserWithRole(string $role, array $permissions = []): TenantUser
    {
        $user = $this->makeTenantUser();
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'tenant']);

        foreach ($permissions as $permission) {
            $roleModel->givePermissionTo(
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant'])
            );
        }

        $user->assignRole($roleModel);

        return $user;
    }
}
