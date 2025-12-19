<?php

namespace App\Services\Tenant\Supplier;

use App\Models\Tenant\Supplier;
use App\Repositories\Tenant\SupplierRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        protected SupplierRepository $repository
    ) {}

    /**
     * Get all suppliers
     */
    public function getAllSuppliers(array $filters = [], bool $paginate = false, int $perPage = 15): Collection|LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey('all', $filters, $paginate, $perPage);

        return Cache::tags(['tenant', tenant()->id, 'suppliers'])
            ->remember($cacheKey, 3600, function () use ($filters, $paginate, $perPage) {
                if ($paginate) {
                    return $this->repository->getPaginated($filters, $perPage);
                }

                return $this->repository->getAll($filters);
            });
    }

    /**
     * Get supplier by ID with products
     */
    public function getSupplierById(int $id, bool $withProducts = false): ?Supplier
    {
        $cacheKey = $this->getCacheKey('single', ['id' => $id, 'with_products' => $withProducts]);

        return Cache::tags(['tenant', tenant()->id, 'suppliers'])
            ->remember($cacheKey, 3600, function () use ($id, $withProducts) {
                if ($withProducts) {
                    return $this->repository->findByIdWithProducts($id);
                }

                return $this->repository->findById($id);
            });
    }

    /**
     * Create supplier with personal details
     */
    public function createSupplierPersonalDetails(array $data): Supplier
    {
        $this->validateUniqueness($data);

        return DB::transaction(function () use ($data) {
            $supplier = $this->repository->create($data);

            $this->clearCache();

            return $supplier;
        });
    }

    /**
     * Update supplier personal details
     */
    public function updateSupplierPersonalDetails(int $id, array $data): Supplier
    {
        $this->validateUniqueness($data, $id);

        return DB::transaction(function () use ($id, $data) {
            $supplier = $this->repository->findById($id);

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier not found');
            }

            $this->repository->update($supplier, $data);

            $this->clearCache();

            return $supplier->fresh();
        });
    }

    /**
     * Update supplier financial details
     */
    public function updateSupplierFinancialDetails(int $id, array $data): Supplier
    {
        return DB::transaction(function () use ($id, $data) {
            $supplier = $this->repository->findById($id);

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier not found');
            }

            $this->repository->update($supplier, $data);

            $this->clearCache();

            return $supplier->fresh();
        });
    }

    /**
     * Toggle supplier active status
     */
    public function toggleActiveStatus(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $supplier = $this->repository->findById($id);

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier not found');
            }

            $newStatus = !$supplier->is_active;
            $this->repository->update($supplier, ['is_active' => $newStatus]);

            $this->clearCache();

            return [
                'is_active' => $newStatus,
                'message' => $newStatus ? 'Supplier activated successfully' : 'Supplier deactivated successfully'
            ];
        });
    }

    /**
     * Validate that supplier name and email combination is unique
     * 
     */
    protected function validateUniqueness(array $data, ?int $excludeId = null): void
    {
        $errors = [];

        // Check if name is provided and validate uniqueness
        if (isset($data['name'])) {
            $nameExists = $this->repository->existsByName($data['name'], $excludeId);
            if ($nameExists) {
                $errors['name'] = ['A supplier with this name already exists.'];
            }
        }

        // Check if email is provided and validate uniqueness
        if (isset($data['email']) && !empty($data['email'])) {
            $emailExists = $this->repository->existsByEmail($data['email'], $excludeId);
            if ($emailExists) {
                $errors['email'] = ['A supplier with this email already exists.'];
            }
        }

        // Check if both name and email combination exists
        if (isset($data['name']) && isset($data['email']) && !empty($data['email'])) {
            $combinationExists = $this->repository->existsByNameAndEmail(
                $data['name'],
                $data['email'],
                $excludeId
            );

            if ($combinationExists && empty($errors)) {
                $errors['supplier'] = ['A supplier with this name and email combination already exists.'];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $type, array $params = []): string
    {
        return sprintf(
            'supplier:%s:%s',
            $type,
            md5(json_encode($params))
        );
    }

    /**
     * Clear all supplier cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'suppliers'])->flush();
    }
}
