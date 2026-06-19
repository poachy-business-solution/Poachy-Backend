<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CentralRolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Set connection
        config(['permission.connection' => 'central']);

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // Business/tenant Management
            'manage-businesses',
            'approve-businesses',
            'suspend-businesses',
            'view-businesses',

            // Category Management
            'manage-categories',
            'manage-business-types',

            // Subscription Management
            'manage-subscriptions',
            'view-subscriptions',

            // User Management
            'manage-users',
            'view-users',

            // Order Management
            'manage-orders',
            'view-orders',

            // Reports & Analytics
            'view-analytics',
            'view-reports',

            // System Settings
            'manage-settings',

            // Marketplace
            'browse-marketplace',
            'place-orders',
            'review-products',
            'manage-wishlist',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'central',
            ]);
        }

        $this->command->info('✓ Created ' . count($permissions) . ' permissions');

        // Flush the cache so givePermissionTo() can find the permissions we just created
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles and Assign Permissions

        // 1. ADMIN ROLE
        $admin = Role::create([
            'name' => 'admin',
            'guard_name' => 'central',
        ]);

        $admin->givePermissionTo([
            'manage-businesses',
            'approve-businesses',
            'suspend-businesses',
            'view-businesses',
            'manage-categories',
            'manage-business-types',
            'manage-subscriptions',
            'view-subscriptions',
            'manage-users',
            'view-users',
            'manage-orders',
            'view-orders',
            'view-analytics',
            'view-reports',
            'manage-settings',
        ]);

        $this->command->info('✓ Created role: Admin (Full system access)');

        // 2. CUSTOMER ROLE
        $customer = Role::create([
            'name' => 'customer',
            'guard_name' => 'central',
        ]);

        $customer->givePermissionTo([
            'browse-marketplace',
            'place-orders',
            'review-products',
            'manage-wishlist',
        ]);

        $this->command->info('✓ Created role: Customer (Marketplace user)');

        // 3. SUPPORT ROLE
        $support = Role::create([
            'name' => 'support',
            'guard_name' => 'central',
        ]);

        $support->givePermissionTo([
            'view-businesses',
            'view-subscriptions',
            'view-users',
            'manage-orders',
            'view-orders',
        ]);

        $this->command->info('✓ Created role: Support (Customer service)');

        $this->command->info("\n✓ Central roles and permissions seeded successfully!");
    }
}
