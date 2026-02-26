<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\TenantDeliveryZone;

class DeliveryZoneSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $zoneId,
        public readonly string $zoneName,
        public readonly string $zoneType,
        public readonly ?array $cities,
        public readonly ?array $counties,
        public readonly ?array $postalCodes,
        public readonly ?string $latitude,
        public readonly ?string $longitude,
        public readonly ?int $radiusKm,
        public readonly float $standardFee,
        public readonly ?float $expressFee,
        public readonly ?float $scheduledFee,
        public readonly ?float $freeDeliveryThreshold,
        public readonly ?string $standardDeliveryTime,
        public readonly ?string $expressDeliveryTime,
        public readonly ?string $scheduledDeliveryTime,
        public readonly array $supportedMethods,
        public readonly int $priority,
        public readonly bool $isActive,
    ) {}

    /**
     * Create DTO from tenant TenantDeliveryZone model.
     */
    public static function fromModel(TenantDeliveryZone $zone): self
    {
        if (! tenant()) {
            throw new \RuntimeException('Cannot create DeliveryZoneSyncDTO outside tenant context');
        }

        if (! $zone->id) {
            throw new \InvalidArgumentException('DeliveryZone must be persisted before syncing');
        }

        if (empty($zone->zone_name)) {
            throw new \InvalidArgumentException('DeliveryZone must have a zone_name to sync');
        }

        return new self(
            tenantId: tenant()->id,
            zoneId: $zone->id,
            zoneName: $zone->zone_name,
            zoneType: $zone->zone_type,
            cities: $zone->cities,
            counties: $zone->counties,
            postalCodes: $zone->postal_codes,
            latitude: $zone->latitude,
            longitude: $zone->longitude,
            radiusKm: $zone->radius_km,
            standardFee: (float) ($zone->standard_fee ?? 0),
            expressFee: $zone->express_fee !== null ? (float) $zone->express_fee : null,
            scheduledFee: $zone->scheduled_fee !== null ? (float) $zone->scheduled_fee : null,
            freeDeliveryThreshold: $zone->free_delivery_threshold !== null ? (float) $zone->free_delivery_threshold : null,
            standardDeliveryTime: $zone->standard_delivery_time,
            expressDeliveryTime: $zone->express_delivery_time,
            scheduledDeliveryTime: $zone->scheduled_delivery_time,
            supportedMethods: $zone->supported_methods ?? ['standard'],
            priority: $zone->priority ?? 100,
            isActive: (bool) $zone->is_active,
        );
    }

    /**
     * Create DTO from array (for queue deserialization on central side).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            zoneId: $data['zone_id'],
            zoneName: $data['zone_name'],
            zoneType: $data['zone_type'],
            cities: $data['cities'] ?? null,
            counties: $data['counties'] ?? null,
            postalCodes: $data['postal_codes'] ?? null,
            latitude: $data['latitude'] ?? null,
            longitude: $data['longitude'] ?? null,
            radiusKm: isset($data['radius_km']) ? (int) $data['radius_km'] : null,
            standardFee: (float) $data['standard_fee'],
            expressFee: isset($data['express_fee']) ? (float) $data['express_fee'] : null,
            scheduledFee: isset($data['scheduled_fee']) ? (float) $data['scheduled_fee'] : null,
            freeDeliveryThreshold: isset($data['free_delivery_threshold']) ? (float) $data['free_delivery_threshold'] : null,
            standardDeliveryTime: $data['standard_delivery_time'] ?? null,
            expressDeliveryTime: $data['express_delivery_time'] ?? null,
            scheduledDeliveryTime: $data['scheduled_delivery_time'] ?? null,
            supportedMethods: $data['supported_methods'] ?? ['standard'],
            priority: (int) ($data['priority'] ?? 100),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * Serialize DTO to array for queue payload storage.
     */
    public function toArray(): array
    {
        return [
            'tenant_id'               => $this->tenantId,
            'zone_id'                 => $this->zoneId,
            'zone_name'               => $this->zoneName,
            'zone_type'               => $this->zoneType,
            'cities'                  => $this->cities,
            'counties'                => $this->counties,
            'postal_codes'            => $this->postalCodes,
            'latitude'                => $this->latitude,
            'longitude'               => $this->longitude,
            'radius_km'               => $this->radiusKm,
            'standard_fee'            => $this->standardFee,
            'express_fee'             => $this->expressFee,
            'scheduled_fee'           => $this->scheduledFee,
            'free_delivery_threshold' => $this->freeDeliveryThreshold,
            'standard_delivery_time'  => $this->standardDeliveryTime,
            'express_delivery_time'   => $this->expressDeliveryTime,
            'scheduled_delivery_time' => $this->scheduledDeliveryTime,
            'supported_methods'       => $this->supportedMethods,
            'priority'                => $this->priority,
            'is_active'               => $this->isActive,
        ];
    }

    /**
     * Generate idempotency key for deduplication.
     */
    public function generateIdempotencyKey(string $action = 'create'): string
    {
        $payload = json_encode($this->toArray());
        $payloadHash = hash('sha256', $payload);

        return md5(
            $this->tenantId .
                'delivery_zone' .
                $this->zoneId .
                $action .
                $payloadHash
        );
    }
}
