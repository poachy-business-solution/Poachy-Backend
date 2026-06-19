<?php

namespace App\Services\Central\Admin\Tenant;

use App\Models\TenantDeliveryZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantDeliveryZoneService
{
    public function __construct(private readonly TenantDeliveryZone $model) {}

    /**
     * Return a paginated list of all tenant delivery zones, with optional filters.
     *
     * @param array{tenant_id?: string, sync_status?: string, is_active?: bool} $filters
     */
    public function getAllDeliveryZones(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = TenantDeliveryZone::with('tenant')->orderBy('priority')->latest();

        if (! empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }

        if (isset($filters['sync_status'])) {
            $query->where('sync_status', $filters['sync_status']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Return a single delivery zone by its central ID, eager loading the tenant.
     */
    public function getDeliveryZone(int $id): TenantDeliveryZone
    {
        return TenantDeliveryZone::with('tenant')->findOrFail($id);
    }
}
