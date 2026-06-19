<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // WithoutModelEvents intentionally omitted — TenantSeeder relies on the
    // TenantCreated Eloquent event to trigger Stancl's database creation pipeline.
    // Suppressing events here would prevent the tenant DB from being created.

    public function run(): void
    {
        $this->call([
            BusinessTypeSeeder::class,
            SubscriptionPlanSeeder::class,
            CentralRolesPermissionsSeeder::class,
            CentralAdminSeeder::class,
            TenantSeeder::class,
            MarketplaceCategorySeeder::class,
            MarketplaceBrandSeeder::class,
        ]);
    }
}
