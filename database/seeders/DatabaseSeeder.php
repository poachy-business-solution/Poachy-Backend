<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed central database
        $this->call([
            BusinessTypeSeeder::class,
            SubscriptionPlanSeeder::class,
            CentralRolesPermissionsSeeder::class,
            CentralAdminSeeder::class,
            TenantSeeder::class,
            MarketplaceCategorySeeder::class,
            MarketplaceBrandSeeder::class,
        ]);

        // seed tenant database using php artisan tenant:seed
    }
}
