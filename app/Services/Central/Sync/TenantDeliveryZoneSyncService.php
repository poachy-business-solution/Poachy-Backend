<?php

namespace App\Services\Central\Sync;

use App\DataTransferObjects\Sync\DeliveryZoneSyncDTO;
use App\Models\TenantDeliveryZone;
use Illuminate\Support\Facades\Log;

class TenantDeliveryZoneSyncService
{
    /**
     * Create a central delivery zone from DTO.
     * If the record already exists (idempotent retry), update it instead.
     */
    public function createDeliveryZone(DeliveryZoneSyncDTO $dto): TenantDeliveryZone
    {
        $existing = TenantDeliveryZone::where('tenant_id', $dto->tenantId)
            ->where('tenant_zone_id', $dto->zoneId)
            ->first();

        if ($existing) {
            Log::info('Delivery zone already exists in central, updating (idempotent create)', [
                'tenant_id'           => $dto->tenantId,
                'tenant_zone_id'      => $dto->zoneId,
                'central_zone_id'     => $existing->id,
            ]);

            return $this->applyZoneData($existing, $dto);
        }

        $zone = TenantDeliveryZone::create(array_merge(
            $this->buildZoneAttributes($dto),
            [
                'tenant_id'      => $dto->tenantId,
                'tenant_zone_id' => $dto->zoneId,
            ]
        ));

        Log::info('Delivery zone created in central', [
            'tenant_id'       => $dto->tenantId,
            'tenant_zone_id'  => $dto->zoneId,
            'central_zone_id' => $zone->id,
        ]);

        return $zone;
    }

    /**
     * Update an existing central delivery zone from DTO.
     * If the record does not exist (out-of-order delivery), create it instead.
     */
    public function updateDeliveryZone(DeliveryZoneSyncDTO $dto): TenantDeliveryZone
    {
        $zone = TenantDeliveryZone::where('tenant_id', $dto->tenantId)
            ->where('tenant_zone_id', $dto->zoneId)
            ->first();

        if (! $zone) {
            Log::warning('Delivery zone not found in central for update — creating (out-of-order delivery)', [
                'tenant_id'      => $dto->tenantId,
                'tenant_zone_id' => $dto->zoneId,
            ]);

            return $this->createDeliveryZone($dto);
        }

        return $this->applyZoneData($zone, $dto);
    }

    /**
     * Delete a central delivery zone by tenant zone ID.
     * If the record does not exist, the desired state is already achieved — log and return.
     */
    public function deleteDeliveryZone(DeliveryZoneSyncDTO $dto): void
    {
        $zone = TenantDeliveryZone::where('tenant_id', $dto->tenantId)
            ->where('tenant_zone_id', $dto->zoneId)
            ->first();

        if (! $zone) {
            Log::warning('Delivery zone not found in central for delete — already absent (idempotent)', [
                'tenant_id'      => $dto->tenantId,
                'tenant_zone_id' => $dto->zoneId,
            ]);

            return;
        }

        $zone->delete();

        Log::info('Delivery zone deleted from central', [
            'tenant_id'       => $dto->tenantId,
            'tenant_zone_id'  => $dto->zoneId,
            'central_zone_id' => $zone->id,
        ]);
    }

    private function applyZoneData(TenantDeliveryZone $zone, DeliveryZoneSyncDTO $dto): TenantDeliveryZone
    {
        $zone->update(array_merge(
            $this->buildZoneAttributes($dto),
            [
                'last_synced_at' => now(),
                'sync_status'    => 'synced',
            ]
        ));

        Log::info('Delivery zone updated in central', [
            'tenant_id'       => $dto->tenantId,
            'tenant_zone_id'  => $dto->zoneId,
            'central_zone_id' => $zone->id,
        ]);

        return $zone->fresh();
    }

    private function buildZoneAttributes(DeliveryZoneSyncDTO $dto): array
    {
        return [
            'zone_name'               => $dto->zoneName,
            'zone_type'               => $dto->zoneType,
            'cities'                  => $dto->cities,
            'counties'                => $dto->counties,
            'postal_codes'            => $dto->postalCodes,
            'latitude'                => $dto->latitude,
            'longitude'               => $dto->longitude,
            'radius_km'               => $dto->radiusKm,
            'standard_fee'            => $dto->standardFee,
            'express_fee'             => $dto->expressFee,
            'scheduled_fee'           => $dto->scheduledFee,
            'free_delivery_threshold' => $dto->freeDeliveryThreshold,
            'standard_delivery_time'  => $dto->standardDeliveryTime,
            'express_delivery_time'   => $dto->expressDeliveryTime,
            'scheduled_delivery_time' => $dto->scheduledDeliveryTime,
            'supported_methods'       => $dto->supportedMethods,
            'priority'                => $dto->priority,
            'is_active'               => $dto->isActive,
            'last_synced_at'          => now(),
            'sync_status'             => 'synced',
        ];
    }
}
