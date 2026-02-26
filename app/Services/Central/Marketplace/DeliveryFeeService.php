<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\DeliveryMethod;
use App\Models\BusinessDetail;
use App\Models\CustomerAddress;
use App\Models\TenantDeliveryZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DeliveryFeeService
{
    /**
     * Calculate the delivery fee for a single tenant order.
     *
     * @return array{
     *     fee: float,
     *     original_fee: float,
     *     zone_id: int|null,
     *     zone_name: string|null,
     *     method: string,
     *     free_delivery_applied: bool,
     *     estimated_time: string|null
     * }
     *
     * @throws \RuntimeException when delivery is not available to the address or method is unsupported
     */
    public function calculateDeliveryFee(
        string $tenantId,
        CustomerAddress $address,
        DeliveryMethod $method,
        float $orderSubtotal
    ): array {
        $deliveryInfo = $this->getDeliveryInfo($tenantId);

        // Zones not yet enabled — delivery is free until tenant configures zones
        if (! ($deliveryInfo['zones_enabled'] ?? false)) {
            return $this->zeroFeeResponse($method);
        }

        $zone = $this->findMatchingZone($tenantId, $address);

        if (! $zone) {
            throw new \RuntimeException(
                'Delivery is not available to your address. Please contact the merchant.'
            );
        }

        if (! $zone->supportsMethod($method)) {
            $available = collect($zone->supported_methods)
                ->map(fn ($m) => DeliveryMethod::from($m)->label())
                ->join(', ');

            throw new \RuntimeException(
                "The '{$method->label()}' delivery method is not available for your area. Available: {$available}."
            );
        }

        $originalFee = $zone->getFeeForMethod($method);
        $threshold   = $zone->free_delivery_threshold;
        $fee         = ($threshold !== null && $orderSubtotal >= (float) $threshold) ? 0.0 : $originalFee;

        return [
            'fee'                   => $fee,
            'original_fee'          => $originalFee,
            'zone_id'               => $zone->id,
            'zone_name'             => $zone->zone_name,
            'method'                => $method->value,
            'free_delivery_applied' => $originalFee > 0 && $fee === 0.0,
            'estimated_time'        => $zone->getEstimatedTimeForMethod($method),
        ];
    }

    /**
     * Return all available delivery methods with their fees for an address.
     * Useful for the delivery preview endpoint.
     *
     * @return array<int, array{method: string, label: string, fee: float, original_fee: float, free_delivery_applied: bool, estimated_time: string|null, zone_name: string|null}>
     */
    public function getAvailableMethodsForAddress(
        string $tenantId,
        CustomerAddress $address,
        float $orderSubtotal = 0
    ): array {
        $available = [];

        foreach (DeliveryMethod::cases() as $method) {
            try {
                $details = $this->calculateDeliveryFee($tenantId, $address, $method, $orderSubtotal);

                $available[] = [
                    'method'                => $details['method'],
                    'label'                 => $method->label(),
                    'fee'                   => $details['fee'],
                    'original_fee'          => $details['original_fee'],
                    'free_delivery_applied' => $details['free_delivery_applied'],
                    'estimated_time'        => $details['estimated_time'],
                    'zone_name'             => $details['zone_name'],
                ];
            } catch (\RuntimeException) {
                // Method not available for this address — skip
            }
        }

        return $available;
    }

    /**
     * Flush the cached zones for a tenant (call after zone updates).
     */
    public function flushZoneCache(string $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:delivery_zones");
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Find the highest-priority zone that covers the address (lower priority number wins).
     */
    private function findMatchingZone(string $tenantId, CustomerAddress $address): ?TenantDeliveryZone
    {
        return $this->getZonesForTenant($tenantId)
            ->first(fn (TenantDeliveryZone $zone) => $zone->matchesAddress($address));
    }

    /**
     * Retrieve active zones for a tenant, ordered by priority, with caching.
     *
     * @return Collection<int, TenantDeliveryZone>
     */
    private function getZonesForTenant(string $tenantId): Collection
    {
        return Cache::remember(
            "tenant:{$tenantId}:delivery_zones",
            300,
            fn () => TenantDeliveryZone::on('central')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('priority')
                ->get()
        );
    }

    /**
     * Return a zero-fee response when zones are not yet enabled.
     *
     * @return array{fee: float, original_fee: float, zone_id: null, zone_name: null, method: string, free_delivery_applied: bool, estimated_time: null}
     */
    private function zeroFeeResponse(DeliveryMethod $method): array
    {
        return [
            'fee'                   => 0.0,
            'original_fee'          => 0.0,
            'zone_id'               => null,
            'zone_name'             => null,
            'method'                => $method->value,
            'free_delivery_applied' => false,
            'estimated_time'        => null,
        ];
    }

    /**
     * Fetch the delivery_info for a tenant from the central BusinessDetail.
     */
    private function getDeliveryInfo(string $tenantId): array
    {
        $businessDetail = BusinessDetail::on('central')
            ->where('tenant_id', $tenantId)
            ->first();

        return $businessDetail?->delivery_info ?? [];
    }
}
