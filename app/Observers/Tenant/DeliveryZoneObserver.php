<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\TenantDeliveryZone;
use App\Services\Tenant\Sync\DeliveryZoneSyncService;
use Illuminate\Support\Facades\Log;

class DeliveryZoneObserver
{
    public function __construct(
        private DeliveryZoneSyncService $deliveryZoneSyncService
    ) {}

    /**
     * Handle the TenantDeliveryZone "created" event.
     */
    public function created(TenantDeliveryZone $zone): void
    {
        try {
            $this->deliveryZoneSyncService->syncToMarketplace($zone, 'create', 3);
        } catch (\Exception $e) {
            Log::error('Failed to sync new delivery zone to marketplace', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $zone->id,
                'error'     => $e->getMessage(),
            ]);
            // Don't throw — zone creation should succeed even if sync fails
        }
    }

    /**
     * Handle the TenantDeliveryZone "updated" event.
     */
    public function updated(TenantDeliveryZone $zone): void
    {
        try {
            $this->deliveryZoneSyncService->syncToMarketplace($zone, 'update', 3);
        } catch (\Exception $e) {
            Log::error('Failed to sync updated delivery zone to marketplace', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $zone->id,
                'error'     => $e->getMessage(),
            ]);
            // Don't throw — zone update should succeed even if sync fails
        }
    }

    /**
     * Handle the TenantDeliveryZone "deleted" event.
     */
    public function deleted(TenantDeliveryZone $zone): void
    {
        try {
            $this->deliveryZoneSyncService->syncToMarketplace($zone, 'delete', 3);
        } catch (\Exception $e) {
            Log::error('Failed to sync deleted delivery zone to marketplace', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $zone->id,
                'error'     => $e->getMessage(),
            ]);
            // Don't throw — zone deletion should succeed even if sync fails
        }
    }
}
