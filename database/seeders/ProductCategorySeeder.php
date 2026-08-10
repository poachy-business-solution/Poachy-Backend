<?php

namespace Database\Seeders;

use App\Models\Tenant\ProductCategory;
use App\Services\Tenant\Business\OnboardingTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $template = app(OnboardingTemplateService::class)->forCurrentTenant();

        foreach ($template['categories'] as $parent) {
            $record = ProductCategory::updateOrCreate(
                ['slug' => $parent['slug']],
                [
                    'name' => $parent['name'],
                    'description' => $parent['description'],
                    'display_order' => $parent['display_order'],
                    'parent_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            foreach ($parent['children'] as $child) {
                ProductCategory::updateOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'name' => $child['name'],
                        'description' => $child['description'],
                        'parent_id' => $record->id,
                        'display_order' => $child['display_order'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $this->command?->info("Product categories seeded from onboarding template: {$template['template_key']}");
    }
}
