<?php

namespace Database\Seeders\Demo;

use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoTenantDataSeeder extends Seeder
{
    /**
     * Run inside the demo tenant's own database (see App\Console\Commands\Tenant\SeedDemoTenant).
     * Assumes TenantDatabaseSeeder's baseline (roles, product categories/brands, UOMs, tenant
     * configuration) has already run — it does not reseed that.
     */
    public function run(): void
    {
        $this->call([
            DemoStaffSeeder::class,
            DemoCatalogSeeder::class,
        ]);

        // Many tenant services record "who did this" via Auth::id() (created_by /
        // approved_by / etc. columns are NOT NULL) rather than accepting an actor in
        // the data array — impersonate the manager for the rest of the run so those
        // inserts succeed, same as a real cashier/manager session would provide.
        Auth::shouldUse('tenant');
        Auth::guard('tenant')->setUser(
            User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))->firstOrFail()
        );

        $this->call([
            DemoStockSeeder::class,
            DemoCustomerSeeder::class,
            DemoPromotionSeeder::class,
            DemoShiftSeeder::class,
            DemoSalesSeeder::class,
            DemoInventoryLifecycleSeeder::class,
            DemoExpenseSeeder::class,
            DemoReviewSeeder::class,
        ]);
    }
}
