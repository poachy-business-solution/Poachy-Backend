<?php

namespace Database\Seeders;

use App\Services\Tenant\Business\OnboardingTemplateService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $template = app(OnboardingTemplateService::class)->forCurrentTenant();
        $hasBusinessSelection = $template['business_type'] || $template['business_category'];

        // Seed tenant database
        $seeders = [
            TenantRolesAndPermissionsSeeder::class,

            ProductBrandSeeder::class,

            TenantConfigurationSeeder::class,
        ];

        if ($hasBusinessSelection) {
            array_splice($seeders, 2, 0, [
                ProductCategorySeeder::class,
                UnitsOfMeasureSeeder::class,
                UomConversionsSeeder::class,
            ]);
        } else {
            $this->command?->warn('Skipping catalog template seed until tenant business details are available.');
        }

        $this->call($seeders);
    }
}
