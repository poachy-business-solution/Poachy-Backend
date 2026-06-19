<?php

namespace App\Services\Tenant\Sync;

use App\Events\Tenant\DeliveryZoneMarketplaceSyncRequested;
use App\Models\Tenant\TenantDeliveryZone;
use Illuminate\Support\Facades\Log;

class DeliveryZoneSyncService
{
    /**
     * Trigger marketplace sync for a delivery zone.
     */
    public function syncToMarketplace(TenantDeliveryZone $zone, string $action = 'create', int $priority = 3): void
    {
        try {
            event(new DeliveryZoneMarketplaceSyncRequested($zone, $action, $priority));

            Log::info('Delivery zone marketplace sync triggered', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $zone->id,
                'zone_name' => $zone->zone_name,
                'action'    => $action,
                'priority'  => $priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger delivery zone marketplace sync', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $zone->id,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
