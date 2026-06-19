<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\TenantDeliveryZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DeliveryZoneRepository
{
    /**
     * Get all zones with optional filtering, ordered by priority.
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->applyFilters(TenantDeliveryZone::query(), $filters)
            ->byPriority()
            ->get();
    }

    /**
     * Find a zone by ID.
     */
    public function findById(int $id): ?TenantDeliveryZone
    {
        return TenantDeliveryZone::find($id);
    }

    /**
     * Create a new zone.
     */
    public function create(array $data): TenantDeliveryZone
    {
        return TenantDeliveryZone::create($data);
    }

    /**
     * Update a zone.
     */
    public function update(TenantDeliveryZone $zone, array $data): bool
    {
        return $zone->update($data);
    }

    /**
     * Delete a zone.
     */
    public function delete(TenantDeliveryZone $zone): bool
    {
        return $zone->delete();
    }

    /**
     * Bulk update zone priorities.
     *
     * @param  array<int, array{id: int, priority: int}>  $zones
     */
    public function reorder(array $zones): void
    {
        foreach ($zones as $item) {
            TenantDeliveryZone::where('id', $item['id'])
                ->update(['priority' => $item['priority']]);
        }
    }

    /**
     * Check whether at least one active zone exists.
     */
    public function hasActiveZone(): bool
    {
        return TenantDeliveryZone::active()->exists();
    }

    /**
     * Apply filters to the query builder.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['zone_type'])) {
            $query->where('zone_type', $filters['zone_type']);
        }

        if (! empty($filters['search'])) {
            $query->where('zone_name', 'like', '%' . $filters['search'] . '%');
        }

        return $query;
    }
}
