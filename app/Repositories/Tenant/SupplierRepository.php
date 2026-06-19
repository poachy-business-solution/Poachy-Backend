<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierRepository
{
    /**
     * Get all suppliers
     */
    public function getAll(array $filters = []): Collection
    {
        $query = Supplier::query()->ordered();

        return $this->applyFilters($query, $filters)->get();
    }

    /**
     * Get paginated suppliers
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Supplier::query()->ordered();

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * Find supplier by ID
     */
    public function findById(int $id): ?Supplier
    {
        return Supplier::find($id);
    }

    /**
     * Find supplier by ID with products
     */
    public function findByIdWithProducts(int $id): ?Supplier
    {
        return Supplier::with([
            'products' => function ($query) {
                $query->active()
                    ->select([
                        'id',
                        'supplier_id',
                        'name',
                        'description',
                        'product_type',
                        'base_selling_price',
                        'stock_status',
                        'primary_image',
                    ]);
            }
        ])->find($id);
    }

    /**
     * Create a new supplier
     */
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * Update a supplier
     */
    public function update(Supplier $supplier, array $data): bool
    {
        return $supplier->update($data);
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters)
    {
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['supplier_type'])) {
            $query->where('supplier_type', $filters['supplier_type']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query;
    }

    /**
     * Check if a supplier with the given name exists
     * 
     * @param string $name
     * @param int|null $excludeId ID to exclude from the check (for updates)
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $query = Supplier::where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if a supplier with the given email exists
     * 
     * @param string $email
     * @param int|null $excludeId ID to exclude from the check (for updates)
     * @return bool
     */
    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $query = Supplier::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if a supplier with the given name and email combination exists
     * 
     * @param string $name
     * @param string $email
     * @param int|null $excludeId ID to exclude from the check (for updates)
     * @return bool
     */
    public function existsByNameAndEmail(string $name, string $email, ?int $excludeId = null): bool
    {
        $query = Supplier::where('name', $name)
            ->where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Find supplier by name
     */
    public function findByName(string $name): ?Supplier
    {
        return Supplier::where('name', $name)->first();
    }
}
