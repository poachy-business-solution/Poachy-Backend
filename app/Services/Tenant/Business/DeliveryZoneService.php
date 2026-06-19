<?php

namespace App\Services\Tenant\Business;

use App\Models\Tenant\TenantDeliveryZone;
use App\Repositories\Tenant\DeliveryZoneRepository;
use Illuminate\Database\Eloquent\Collection;

class DeliveryZoneService
{
    public function __construct(
        private readonly DeliveryZoneRepository $repository,
    ) {}

    /**
     * Retrieve all delivery zones with optional filtering.
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->repository->getAll($filters);
    }

    /**
     * Find a zone by ID or throw a model-not-found exception.
     */
    public function findOrFail(int $id): TenantDeliveryZone
    {
        $zone = $this->repository->findById($id);

        if (! $zone) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Delivery zone [{$id}] not found."
            );
        }

        return $zone;
    }

    /**
     * Create a new delivery zone.
     */
    public function store(array $data): TenantDeliveryZone
    {
        return $this->repository->create($data);
    }

    /**
     * Update an existing delivery zone.
     */
    public function update(TenantDeliveryZone $zone, array $data): TenantDeliveryZone
    {
        $this->repository->update($zone, $data);

        return $zone->fresh();
    }

    /**
     * Delete a delivery zone.
     */
    public function destroy(TenantDeliveryZone $zone): bool
    {
        return $this->repository->delete($zone);
    }

    /**
     * Bulk-update zone priorities.
     *
     * @param  array<int, array{id: int, priority: int}>  $zones
     */
    public function reorder(array $zones): void
    {
        $this->repository->reorder($zones);
    }

    /**
     * Check whether at least one active zone exists.
     */
    public function hasActiveZone(): bool
    {
        return $this->repository->hasActiveZone();
    }
}
