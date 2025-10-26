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
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $this->command->info('✓ Created ' . count($permissions) . ' permissions');

        // Create Roles and Assign Permissions
        
        // 1. OWNER ROLE (Full access)
        $owner = Role::create([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);
        
        $owner->givePermissionTo(Permission::all()); // All permissions
        
        $this->command->info('✓ Created role: Owner (Full access)');

        // 2. MANAGER ROLE
        $manager = Role::create([
            'name' => 'manager',
            'guard_name' => 'web',
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
            'view-customers',
            'manage-expenses',
            'view-expenses',
            'view-employees',
            'view-sales-reports',
            'view-inventory-reports',
            'view-financial-reports',
            'manage-suppliers',
            'view-suppliers',
        ]);
        
        $this->command->info('✓ Created role: Manager (Store management)');

        // 3. CASHIER ROLE
        $cashier = Role::create([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);
        
        $cashier->givePermissionTo([
            'view-products',
            'view-inventory',
            'create-sales',
            'view-sales',
            'view-customers',
            'manage-customers',
            'apply-discounts',
        ]);
        
        $this->command->info('✓ Created role: Cashier (POS operations)');

        $this->command->info("\n✓ Tenant roles and permissions seeded successfully!");
    }
}
