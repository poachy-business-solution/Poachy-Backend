<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantRolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure Spatie uses the tenant connection, not central (which a prior
        // seeder may have set earlier in the same process).
        config(['permission.connection' => 'tenant']);

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // Product Management
            'manage-products',
            'view-products',
            'delete-products',

            // Inventory Management
            'manage-inventory',
            'view-inventory',
            'adjust-stock',
            'transfer-stock',

            // Sales Management
            'create-sales',
            'view-sales',
            'process-refunds',
            'apply-discounts',

            // Customer Management
            'manage-customers',
            'view-customers',
            'loyalty-transactions',
            'credit-management',

            // Expense Management
            'manage-expenses',
            'view-expenses',

            // Employee Management
            'manage-employees',
            'view-employees',

            // Reports
            'view-sales-reports',
            'view-inventory-reports',
            'view-financial-reports',

            // Settings
            'manage-store-settings',
            'manage-locations',

            // Suppliers
            'manage-suppliers',
            'view-suppliers',
            'manage-supplier-payments',
            'view-supplier-payments',

            // Offers
            'manage-coupons',
            'view-coupons',
            'manage-promotions',
            'view-promotions',

            // Stock Alert Permissions
            'view-stock-alerts',
            'resolve-stock-alerts',

            // Expiry Alert Permissions
            'view-expiry-alerts',
            'resolve-expiry-alerts',

            // Waste Permissions
            'view-waste-records',
            'manage-waste-records',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate([
                'name' => $permission,
                'guard_name' => 'tenant',
            ]);
        }

        $this->command->info('✓ Created ' . count($permissions) . ' permissions');

        // Flush cache so givePermissionTo() resolves the permissions we just created
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles and Assign Permissions

        // 1. OWNER ROLE (Full access)
        $owner = Role::updateOrCreate([
            'name' => 'owner',
            'guard_name' => 'tenant',
        ]);

        $owner->givePermissionTo(
            Permission::where('guard_name', 'tenant')->get()
        );

        $this->command->info('✓ Created role: Owner (Full access)');

        // 2. MANAGER ROLE
        $manager = Role::updateOrCreate([
            'name' => 'manager',
            'guard_name' => 'tenant',
        ]);

        $manager->givePermissionTo([
            'manage-products',
            'view-products',
            'manage-inventory',
            'view-inventory',
            'adjust-stock',
            'transfer-stock',
            'create-sales',
            'view-sales',
            'process-refunds',
            'apply-discounts',
            'manage-customers',
            'loyalty-transactions',
            'credit-management',
            'view-customers',
            'manage-expenses',
            'view-expenses',
            'view-employees',
            'view-sales-reports',
            'view-inventory-reports',
            'view-financial-reports',
            'manage-suppliers',
            'view-suppliers',
            'manage-coupons',
            'view-coupons',
            'manage-promotions',
            'view-promotions',
            'manage-supplier-payments',
            'view-supplier-payments',
            'manage-waste-records',
            'view-waste-records',
            'view-stock-alerts',
            'resolve-stock-alerts',
            'view-expiry-alerts',
            'resolve-expiry-alerts',
        ]);

        $this->command->info('✓ Created role: Manager (Store management)');

        // 3. CASHIER ROLE
        $cashier = Role::updateOrCreate([
            'name' => 'cashier',
            'guard_name' => 'tenant',
        ]);

        $cashier->givePermissionTo([
            'view-products',
            'view-inventory',
            'create-sales',
            'view-sales',
            'view-customers',
            'manage-customers',
            'apply-discounts',
            'view-coupons',
            'view-promotions',
            'view-supplier-payments',
            'view-stock-alerts',
            'view-expiry-alerts',
            'view-waste-records',
        ]);

        $this->command->info('✓ Created role: Cashier (POS operations)');

        $this->command->info("\n✓ Tenant roles and permissions seeded successfully!");
    }
}
