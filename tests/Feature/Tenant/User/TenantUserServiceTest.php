<?php

namespace Tests\Feature\Tenant\User;

use App\Mail\Tenant\Auth\TenantUserCredentialsMail;
use App\Models\Tenant\User as TenantUser;
use App\Services\Central\Admin\Tenant\TenantUserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class TenantUserServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        // sendCredentialsEmail() looks up the tenant's domain on the central connection
        // for the login URL — point it at the real central DB rather than the sqlite
        // default, matching the pattern used by the Central/Marketplace test files.
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Mail::fake();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): TenantUserService
    {
        return new TenantUserService;
    }

    private function seedRoles(): void
    {
        foreach (['owner', 'manager', 'cashier'] as $name) {
            Role::create(['name' => $name, 'guard_name' => 'tenant']);
        }
        $permission = Permission::create(['name' => 'manage-products', 'guard_name' => 'tenant']);
        Role::where('name', 'owner')->first()->givePermissionTo($permission);
    }

    private function createUser(array $overrides = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'name' => 'Staff Member',
            'email' => 'staff-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ], $overrides));
    }

    private function actingAsUser(TenantUser $user): void
    {
        // The real request path goes through the `auth:tenant` middleware, which calls
        // Auth::shouldUse('tenant') on success — that's what makes the service's
        // guard-less Auth::id() calls resolve against the tenant guard. Replicate both.
        Auth::guard('tenant')->setUser($user);
        Auth::shouldUse('tenant');
    }

    // =========================================================================
    // getAllUsers()
    // =========================================================================

    public function test_get_all_users_returns_paginated_with_roles(): void
    {
        $user = $this->createUser();
        $user->assignRole('cashier');
        $this->createUser();

        $result = $this->makeService()->getAllUsers(15);

        $this->assertSame(2, $result->total());
        $this->assertTrue($result->first()->relationLoaded('roles'));
    }

    // =========================================================================
    // createUser()
    // =========================================================================

    public function test_create_user_hashes_password_and_defaults_active(): void
    {
        $user = $this->makeService()->createUser([
            'name' => 'New Hire', 'email' => 'new-hire@example.com',
        ]);

        $this->assertTrue($user->is_active);
        $this->assertNotEmpty($user->password);
        $this->assertNotSame('password', $user->password);
    }

    public function test_create_user_respects_explicit_inactive_status(): void
    {
        $user = $this->makeService()->createUser([
            'name' => 'New Hire', 'email' => 'new-hire2@example.com', 'is_active' => false,
        ]);

        $this->assertFalse($user->is_active);
    }

    public function test_create_user_assigns_role_when_provided(): void
    {
        $user = $this->makeService()->createUser([
            'name' => 'Cashier Person', 'email' => 'cashier@example.com', 'role' => 'cashier',
        ]);

        $this->assertTrue($user->fresh('roles')->hasRole('cashier'));
    }

    public function test_create_user_sends_credentials_email(): void
    {
        $this->makeService()->createUser([
            'name' => 'New Hire', 'email' => 'new-hire3@example.com', 'role' => 'cashier',
        ]);

        // TenantUserCredentialsMail implements ShouldQueue, so Mailer::sendMailable()
        // auto-redirects even a plain ->send() call to the queue — assertQueued, not assertSent.
        Mail::assertQueued(TenantUserCredentialsMail::class, function ($mail) {
            return $mail->hasTo('new-hire3@example.com');
        });
    }

    // =========================================================================
    // updateUser()
    // =========================================================================

    public function test_update_user_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->updateUser(999999, ['name' => 'X']);
    }

    public function test_update_user_only_touches_provided_fields(): void
    {
        $user = $this->createUser(['name' => 'Old Name', 'phone' => '0712345678']);

        $updated = $this->makeService()->updateUser($user->id, ['name' => 'New Name']);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('0712345678', $updated->phone);
    }

    // =========================================================================
    // assignRole()
    // =========================================================================

    public function test_assign_role_replaces_existing_role(): void
    {
        $user = $this->createUser();
        $user->assignRole('cashier');

        $updated = $this->makeService()->assignRole($user->id, 'manager');

        $this->assertTrue($updated->hasRole('manager'));
        $this->assertFalse($updated->hasRole('cashier'));
        $this->assertCount(1, $updated->roles);
    }

    public function test_assign_role_throws_for_unknown_role(): void
    {
        $user = $this->createUser();

        $this->expectException(RoleDoesNotExist::class);

        $this->makeService()->assignRole($user->id, 'nonexistent-role');
    }

    // =========================================================================
    // deleteUser()
    // =========================================================================

    public function test_delete_user_throws_when_deleting_self(): void
    {
        $user = $this->createUser();
        $this->actingAsUser($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot delete your own account');

        $this->makeService()->deleteUser($user->id);
    }

    public function test_delete_user_throws_when_deleting_last_owner(): void
    {
        $owner = $this->createUser();
        $owner->assignRole('owner');
        $actingUser = $this->createUser();
        $actingUser->assignRole('manager');
        $this->actingAsUser($actingUser);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the last owner');

        $this->makeService()->deleteUser($owner->id);
    }

    public function test_delete_user_succeeds_when_another_owner_exists(): void
    {
        $ownerA = $this->createUser();
        $ownerA->assignRole('owner');
        $ownerB = $this->createUser();
        $ownerB->assignRole('owner');
        $this->actingAsUser($ownerB);

        $this->makeService()->deleteUser($ownerA->id);

        $this->assertDatabaseMissing('users', ['id' => $ownerA->id], connection: 'tenant');
    }

    public function test_delete_user_succeeds_for_non_owner(): void
    {
        $cashier = $this->createUser();
        $cashier->assignRole('cashier');
        $actingUser = $this->createUser();
        $actingUser->assignRole('owner');
        $this->actingAsUser($actingUser);

        $this->makeService()->deleteUser($cashier->id);

        $this->assertDatabaseMissing('users', ['id' => $cashier->id], connection: 'tenant');
    }

    // =========================================================================
    // getRoles()
    // =========================================================================

    public function test_get_roles_returns_roles_with_permissions_count(): void
    {
        $roles = $this->makeService()->getRoles();

        $owner = $roles->firstWhere('name', 'owner');
        $cashier = $roles->firstWhere('name', 'cashier');

        $this->assertSame(1, $owner['permissions_count']);
        $this->assertSame(0, $cashier['permissions_count']);
        $this->assertContains('manage-products', $owner['permissions']->all());
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach ([
            'role_has_permissions', 'model_has_roles', 'model_has_permissions',
            'roles', 'permissions', 'users',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
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
}
