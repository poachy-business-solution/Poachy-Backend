<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TenantDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed tenant database
        $this->call([
            TenantRolesAndPermissionsSeeder::class,
            ProductCategorySeeder::class,
        ]);
    }
}
