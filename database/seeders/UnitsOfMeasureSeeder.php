<?php

namespace Database\Seeders;

use App\Models\Tenant\UnitOfMeasure;
use App\Services\Tenant\Business\OnboardingTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitsOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        if (! tenancy()->initialized) {
            $this->command->error('Tenancy not initialized. This seeder must run in tenant context.');

            return;
        }

        $template = app(OnboardingTemplateService::class)->forCurrentTenant();

        $this->command->info('Seeding Units of Measure for tenant: '.tenant()->id);

        try {
            DB::connection('tenant')->transaction(function () use ($template) {
                foreach ($template['units_of_measure'] as $unit) {
                    UnitOfMeasure::updateOrCreate(
                        ['code' => $unit['code']],
                        [
                            'name' => $unit['name'],
                            'type' => $unit['type'],
                            'source_type' => $unit['source_type'],
                            'is_base_unit' => $unit['is_base_unit'],
                            'is_active' => $unit['is_active'],
                            'description' => $unit['description'],
                        ]
                    );
                }
            });

            $this->command->info("Units of Measure seeded from onboarding template: {$template['template_key']}");
        } catch (\Exception $e) {
            $this->command->error('Error seeding Units of Measure: '.$e->getMessage());
            Log::error('UnitsOfMeasureSeeder failed', [
                'tenant_id' => tenant()->id,
                'template_key' => $template['template_key'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
