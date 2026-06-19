<?php

namespace App\Console\Commands;

use App\Models\BusinessDetail;
use App\Models\TenantDeliveryZone;
use Illuminate\Console\Command;

class MigrateDeliveryFeesToZones extends Command
{
    protected $signature = 'delivery:migrate-to-zones
                            {--enable : Automatically enable zone-based delivery after migration}';

    protected $description = 'Migrate existing flat delivery fees to zone-based delivery configuration';

    public function handle(): int
    {
        $this->info('Migrating flat delivery fees to zone-based configuration...');

        $businessDetails = BusinessDetail::on('central')
            ->whereNotNull('delivery_info')
            ->get();

        $migrated = 0;
        $skipped  = 0;

        $bar = $this->output->createProgressBar($businessDetails->count());
        $bar->start();

        foreach ($businessDetails as $detail) {
            $deliveryInfo = $detail->delivery_info;

            // Skip tenants with delivery disabled
            if (! ($deliveryInfo['available'] ?? false)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // Skip if a zone already exists for this tenant (already migrated)
            if (TenantDeliveryZone::on('central')->where('tenant_id', $detail->tenant_id)->exists()) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // Read legacy flat-fee data that may still exist in old DB records
            $areas     = $deliveryInfo['areas'] ?? [];
            $fee       = $deliveryInfo['fee'] ?? 0;
            $threshold = $deliveryInfo['free_delivery_threshold'] ?? null;
            $time      = $deliveryInfo['estimated_time'] ?? null;

            TenantDeliveryZone::on('central')->create([
                'tenant_id'               => $detail->tenant_id,
                'zone_name'               => 'Default Zone',
                'zone_type'               => 'city',
                'cities'                  => $areas,
                'standard_fee'            => $fee,
                'free_delivery_threshold' => $threshold,
                'standard_delivery_time'  => $time,
                'supported_methods'       => ['standard'],
                'priority'                => 100,
                'is_active'               => true,
            ]);

            // Only update zones_enabled — leave available as-is, strip legacy fields
            $detail->update([
                'delivery_info' => [
                    'available'     => true,
                    'zones_enabled' => (bool) $this->option('enable'),
                ],
            ]);

            $migrated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                ['Migrated', $migrated],
                ['Skipped', $skipped],
            ]
        );

        if ($migrated > 0 && ! $this->option('enable')) {
            $this->line('');
            $this->warn('Zone-based delivery is DISABLED by default.');
            $this->line('Tenants must enable it via: POST /business-details/delivery-settings/toggle-zones');
            $this->line('Or re-run with --enable to activate immediately: delivery:migrate-to-zones --enable');
        }

        return self::SUCCESS;
    }
}
