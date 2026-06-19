<?php

namespace Database\Seeders;

use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\UomConversion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UomConversionsSeeder extends Seeder
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

        $this->command->info('Seeding UOM Conversions for tenant: ' . tenant()->id);

        try {
            // Get all UOM codes mapped to IDs
            $uoms = UnitOfMeasure::pluck('id', 'code')->toArray();

            if (empty($uoms)) {
                $this->command->error('No Units of Measure found. Please run UnitsOfMeasureSeeder first.');
                return;
            }

            DB::transaction(function () use ($uoms) {
                $this->seedWeightConversions($uoms);
                $this->seedVolumeConversions($uoms);
                $this->seedLengthConversions($uoms);
                $this->seedAreaConversions($uoms);
                $this->seedTimeConversions($uoms);
                $this->seedCountConversions($uoms);
            });

            $this->command->info('UOM Conversions seeded successfully.');
        } catch (\Exception $e) {
            $this->command->error('Error seeding UOM Conversions: ' . $e->getMessage());
            Log::error('UomConversionsSeeder failed', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Seed weight conversions.
     *
     * @param array $uoms
     * @return void
     */
    protected function seedWeightConversions(array $uoms): void
    {
        $conversions = [
            // To grams (base unit)
            ['kg', 'g', 1000],
            ['mg', 'g', 0.001],
            ['tonne', 'g', 1000000],
            ['lb', 'g', 453.592],
            ['oz', 'g', 28.3495],
        ];

        $this->createConversions($conversions, $uoms, 'Weight');
    }

    /**
     * Seed volume conversions.
     *
     * @param array $uoms
     * @return void
     */
    protected function seedVolumeConversions(array $uoms): void
    {
        $conversions = [
            // To milliliters (base unit)
            ['l', 'ml', 1000],
            ['cl', 'ml', 10],
            ['gal', 'ml', 3785.41],
            ['pint', 'ml', 473.176],
            ['qt', 'ml', 946.353],
        ];

        $this->createConversions($conversions, $uoms, 'Volume');
    }

    /**
     * Seed length conversions.
     *
     * @param array $uoms
     * @return void
     */
    protected function seedLengthConversions(array $uoms): void
    {
        $conversions = [
            // To meters (base unit)
            ['km', 'm', 1000],
            ['cm', 'm', 0.01],
            ['mm', 'm', 0.001],
            ['ft', 'm', 0.3048],
            ['inch', 'm', 0.0254],
            ['yd', 'm', 0.9144],
        ];

        $this->createConversions($conversions, $uoms, 'Length');
    }

    /**
     * Seed area conversions.
     *
     * @param array $uoms
     * @return void
     */
    protected function seedAreaConversions(array $uoms): void
    {
        $conversions = [
            // To square meters (base unit)
            ['sqcm', 'sqm', 0.0001],
            ['sqft', 'sqm', 0.092903],
            ['acre', 'sqm', 4046.86],
            ['ha', 'sqm', 10000],
        ];

        $this->createConversions($conversions, $uoms, 'Area');
    }

    /**
     * Seed time conversions.
     *
     * @param array $uoms
     * @return void
     */
    protected function seedTimeConversions(array $uoms): void
    {
        $conversions = [
            // To hours (base unit)
            ['min', 'hr', 0.0166667],
            ['sec', 'hr', 0.000277778],
            ['day', 'hr', 24],
            ['week', 'hr', 168],
            ['month', 'hr', 730.001],
        ];

        $this->createConversions($conversions, $uoms, 'Time');
    }

    /**
     * Seed count conversions.
     *
     * @param array $uoms
     * @return void
     */
    protected function seedCountConversions(array $uoms): void
    {
        $conversions = [
            // To pieces (base unit)
            ['doz', 'pcs', 12],
            ['pair', 'pcs', 2],
            // Note: pack, box, ctn, crate, pallet, bag are product-specific
            // and should be defined per product in product_uoms table
        ];

        $this->createConversions($conversions, $uoms, 'Count');
    }

    /**
     * Create conversions from array data.
     *
     * @param array $conversions
     * @param array $uoms
     * @param string $type
     * @return void
     */
    protected function createConversions(array $conversions, array $uoms, string $type): void
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($conversions as [$fromCode, $toCode, $factor]) {
            // Check if UOMs exist
            if (!isset($uoms[$fromCode])) {
                $this->command->warn("  ⚠ Skipping conversion: UOM '{$fromCode}' not found");
                $skipped++;
                continue;
            }

            if (!isset($uoms[$toCode])) {
                $this->command->warn("  ⚠ Skipping conversion: UOM '{$toCode}' not found");
                $skipped++;
                continue;
            }

            try {
                $conversion = UomConversion::updateOrCreate(
                    [
                        'from_uom_id' => $uoms[$fromCode],
                        'to_uom_id' => $uoms[$toCode],
                    ],
                    [
                        'conversion_factor' => $factor,
                    ]
                );

                if ($conversion->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->command->error("Error creating conversion {$fromCode}→{$toCode}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->command->info("{$type} conversions: {$created} created, {$updated} updated, {$skipped} skipped");
    }

    /**
     * Optional: Create bidirectional conversions automatically.
     * This creates reverse conversions (e.g., if kg→g exists, create g→kg).
     *
     * @return void
     */
    protected function createBidirectionalConversions(): void
    {
        $this->command->info('Creating bidirectional conversions...');

        $conversions = UomConversion::with(['fromUom', 'toUom'])->get();
        $created = 0;

        foreach ($conversions as $conversion) {
            // Check if reverse exists
            $reverseExists = UomConversion::where('from_uom_id', $conversion->to_uom_id)
                ->where('to_uom_id', $conversion->from_uom_id)
                ->exists();

            if (!$reverseExists) {
                try {
                    UomConversion::create([
                        'from_uom_id' => $conversion->to_uom_id,
                        'to_uom_id' => $conversion->from_uom_id,
                        'conversion_factor' => 1 / $conversion->conversion_factor,
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    $this->command->warn("Could not create reverse conversion: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("Created {$created} reverse conversions");
    }
}
