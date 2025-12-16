<?php

namespace App\Services\Tenant\Tax;

use App\Models\Tenant\TaxRate;
use App\Repositories\Tenant\TaxRateRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaxRateService
{
    public function __construct(
        protected TaxRateRepository $repository
    ) {}

    /**
     * Get all tax rates
     */
    public function getAllTaxRates(array $filters = [], bool $paginate = false, int $perPage = 15): Collection|LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey('all', $filters, $paginate, $perPage);

        return Cache::tags(['tenant', tenant()->id, 'tax_rates'])
            ->remember($cacheKey, 3600, function () use ($filters, $paginate, $perPage) {
                if ($paginate) {
                    return $this->repository->getPaginated($filters, $perPage);
                }

                return $this->repository->getAll($filters);
            });
    }

    /**
     * Create a new tax rate
     */
    public function createTaxRate(array $data): TaxRate
    {
        if ($this->repository->existsForDate($data['tax_name'], $data['effective_from'])) {
            throw new InvalidArgumentException(
                "A tax rate {$data['tax_name']} already exists for the date {$data['effective_from']}."
            );
        }

        return DB::transaction(function () use ($data) {
            if (!$this->repository->hasDefault()) {
                $data['is_default'] = true;
            }

            if (isset($data['is_default']) && $data['is_default'] === true) {
                $this->repository->unsetAllDefaults();
            }

            $taxRate = $this->repository->create($data);
            $this->clearCache();

            return $taxRate;
        });
    }

    /**
     * Toggle active status
     */
    public function toggleActiveStatus(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $taxRate = $this->repository->findById($id);

            if (!$taxRate) {
                throw new \InvalidArgumentException('Tax rate not found');
            }

            $newStatus = !$taxRate->is_active;
            $this->repository->update($taxRate, ['is_active' => $newStatus]);

            $this->clearCache();

            return [
                'is_active' => $newStatus,
                'message' => $newStatus ? 'Tax rate activated successfully' : 'Tax rate deactivated successfully'
            ];
        });
    }

    /**
     * Toggle default status
     */
    public function toggleDefaultStatus(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $taxRate = $this->repository->findById($id);

            if (!$taxRate) {
                throw new \InvalidArgumentException('Tax rate not found');
            }

            $newDefaultStatus = !$taxRate->is_default;

            // If setting as default, unset all other defaults
            if ($newDefaultStatus) {
                $this->repository->unsetAllDefaults();
                $this->repository->update($taxRate, ['is_default' => true]);
                $message = 'Tax rate set as default successfully';
            } else {
                // Cannot unset default if it's the only one
                if ($this->repository->hasDefault() && $taxRate->is_default) {
                    throw new \InvalidArgumentException('Cannot remove default status. At least one tax rate must be default');
                }

                $this->repository->update($taxRate, ['is_default' => false]);
                $message = 'Default status removed successfully';
            }

            $this->clearCache();

            return [
                'is_default' => $newDefaultStatus,
                'message' => $message
            ];
        });
    }

    /**
     * Update effective until date
     */
    public function updateEffectiveUntil(int $id, ?string $effectiveUntil): TaxRate
    {
        return DB::transaction(function () use ($id, $effectiveUntil) {
            $taxRate = $this->repository->findById($id);

            if (!$taxRate) {
                throw new \InvalidArgumentException('Tax rate not found');
            }

            $this->repository->update($taxRate, ['effective_until' => $effectiveUntil]);

            $this->clearCache();

            return $taxRate->fresh();
        });
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $type, array $params = []): string
    {
        return sprintf(
            'tax_rate:%s:%s',
            $type,
            md5(json_encode($params))
        );
    }

    /**
     * Clear all tax rate cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'tax_rates'])->flush();
    }
}
