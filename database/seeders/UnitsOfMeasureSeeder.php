<?php

namespace Database\Seeders;

use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitsOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Ensure we're running on tenant connection
        if (!tenancy()->initialized) {
            $this->command->error('Tenancy not initialized. This seeder must run in tenant context.');
            return;
        }

        $this->command->info('Seeding Units of Measure for tenant: ' . tenant()->id);

        try {
            DB::connection('tenant')->transaction(function () {
                $this->seedCountUnits();
                $this->seedWeightUnits();
                $this->seedVolumeUnits();
                $this->seedLengthUnits();
                $this->seedAreaUnits();
                $this->seedTimeUnits();
            });

            $this->command->info('Units of Measure seeded successfully.');
        } catch (\Exception $e) {
            $this->command->error('Error seeding Units of Measure: ' . $e->getMessage());
            Log::error('UnitsOfMeasureSeeder failed', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Seed count-based units.
     *
     * @return void
     */
    protected function seedCountUnits(): void
    {
        $units = [
            [
                'code' => 'pcs',
                'name' => 'Piece',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => true,
                'is_active' => true,
                'description' => 'Single item',
            ],
            [
                'code' => 'pair',
                'name' => 'Pair',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Two pieces',
            ],
            [
                'code' => 'doz',
                'name' => 'Dozen',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '12 pieces',
            ],
            [
                'code' => 'pack',
                'name' => 'Pack',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Packaged items',
            ],
            [
                'code' => 'box',
                'name' => 'Box',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Retail box',
            ],
            [
                'code' => 'ctn',
                'name' => 'Carton',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Wholesale carton',
            ],
            [
                'code' => 'crate',
                'name' => 'Crate',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Wooden or plastic crate',
            ],
            [
                'code' => 'pallet',
                'name' => 'Pallet',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Logistics pallet',
            ],
            [
                'code' => 'bag',
                'name' => 'Bag',
                'type' => 'count',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Bag or sack',
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('  ✓ Count units seeded');
    }

    /**
     * Seed weight-based units.
     *
     * @return void
     */
    protected function seedWeightUnits(): void
    {
        $units = [
            [
                'code' => 'g',
                'name' => 'Gram',
                'type' => 'weight',
                'source_type' => 'system',
                'is_base_unit' => true,
                'is_active' => true,
                'description' => 'Base unit for weight',
            ],
            [
                'code' => 'kg',
                'name' => 'Kilogram',
                'type' => 'weight',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '1000 grams',
            ],
            [
                'code' => 'mg',
                'name' => 'Milligram',
                'type' => 'weight',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '0.001 grams',
            ],
            [
                'code' => 'tonne',
                'name' => 'Metric Tonne',
                'type' => 'weight',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '1,000,000 grams',
            ],
            [
                'code' => 'oz',
                'name' => 'Ounce',
                'type' => 'weight',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Imperial unit ~28.35 grams',
            ],
            [
                'code' => 'lb',
                'name' => 'Pound',
                'type' => 'weight',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Imperial unit ~453.59 grams',
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('  ✓ Weight units seeded');
    }

    /**
     * Seed volume-based units.
     *
     * @return void
     */
    protected function seedVolumeUnits(): void
    {
        $units = [
            [
                'code' => 'ml',
                'name' => 'Milliliter',
                'type' => 'volume',
                'source_type' => 'system',
                'is_base_unit' => true,
                'is_active' => true,
                'description' => 'Base unit for volume',
            ],
            [
                'code' => 'l',
                'name' => 'Liter',
                'type' => 'volume',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '1000 milliliters',
            ],
            [
                'code' => 'cl',
                'name' => 'Centiliter',
                'type' => 'volume',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '10 milliliters',
            ],
            [
                'code' => 'gal',
                'name' => 'Gallon',
                'type' => 'volume',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'US gallon ~3785.41 milliliters',
            ],
            [
                'code' => 'pint',
                'name' => 'Pint',
                'type' => 'volume',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'US pint ~473.18 milliliters',
            ],
            [
                'code' => 'qt',
                'name' => 'Quart',
                'type' => 'volume',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'US quart ~946.35 milliliters',
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('  ✓ Volume units seeded');
    }

    /**
     * Seed length-based units.
     *
     * @return void
     */
    protected function seedLengthUnits(): void
    {
        $units = [
            [
                'code' => 'm',
                'name' => 'Meter',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => true,
                'is_active' => true,
                'description' => 'Base unit for length',
            ],
            [
                'code' => 'cm',
                'name' => 'Centimeter',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '0.01 meters',
            ],
            [
                'code' => 'mm',
                'name' => 'Millimeter',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '0.001 meters',
            ],
            [
                'code' => 'km',
                'name' => 'Kilometer',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '1000 meters',
            ],
            [
                'code' => 'ft',
                'name' => 'Foot',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Imperial unit ~0.3048 meters',
            ],
            [
                'code' => 'inch',
                'name' => 'Inch',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Imperial unit ~0.0254 meters',
            ],
            [
                'code' => 'yd',
                'name' => 'Yard',
                'type' => 'length',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Imperial unit ~0.9144 meters',
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('  ✓ Length units seeded');
    }

    /**
     * Seed area-based units.
     *
     * @return void
     */
    protected function seedAreaUnits(): void
    {
        $units = [
            [
                'code' => 'sqm',
                'name' => 'Square Meter',
                'type' => 'area',
                'source_type' => 'system',
                'is_base_unit' => true,
                'is_active' => true,
                'description' => 'Base unit for area',
            ],
            [
                'code' => 'sqcm',
                'name' => 'Square Centimeter',
                'type' => 'area',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '0.0001 square meters',
            ],
            [
                'code' => 'sqft',
                'name' => 'Square Foot',
                'type' => 'area',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => 'Imperial unit ~0.0929 square meters',
            ],
            [
                'code' => 'acre',
                'name' => 'Acre',
                'type' => 'area',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '~4046.86 square meters',
            ],
            [
                'code' => 'ha',
                'name' => 'Hectare',
                'type' => 'area',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '10,000 square meters',
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('  ✓ Area units seeded');
    }

    /**
     * Seed time-based units.
     *
     * @return void
     */
    protected function seedTimeUnits(): void
    {
        $units = [
            [
                'code' => 'hr',
                'name' => 'Hour',
                'type' => 'time',
                'source_type' => 'system',
                'is_base_unit' => true,
                'is_active' => true,
                'description' => 'Base unit for time',
            ],
            [
                'code' => 'min',
                'name' => 'Minute',
                'type' => 'time',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '1/60 hour',
            ],
            [
                'code' => 'sec',
                'name' => 'Second',
                'type' => 'time',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '1/3600 hour',
            ],
            [
                'code' => 'day',
                'name' => 'Day',
                'type' => 'time',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '24 hours',
            ],
            [
                'code' => 'week',
                'name' => 'Week',
                'type' => 'time',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '168 hours',
            ],
            [
                'code' => 'month',
                'name' => 'Month',
                'type' => 'time',
                'source_type' => 'system',
                'is_base_unit' => false,
                'is_active' => true,
                'description' => '~730 hours (30.42 days average)',
            ],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('  ✓ Time units seeded');
    }
}
