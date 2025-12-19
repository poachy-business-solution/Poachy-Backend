<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\TaxRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TaxRateRepository
{
    /**
     * Get all tax rates
     */
    public function getAll(array $filters = []): Collection
    {
        $query = TaxRate::query()->orderBy('created_at', 'desc');

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    /**
     * Get paginated tax rates
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = TaxRate::query()->orderBy('created_at', 'desc');

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage);
    }

    /**
     * Find tax rate by ID
     */
    public function findById(int $id): ?TaxRate
    {
        return TaxRate::find($id);
    }

    /**
     * Create a new tax rate
     */
    public function create(array $data): TaxRate
    {
        return TaxRate::create($data);
    }

    /**
     * Update a tax rate
     */
    public function update(TaxRate $taxRate, array $data): bool
    {
        return $taxRate->update($data);
    }

    /**
     * Get default tax rate
     */
    public function getDefault(): ?TaxRate
    {
        return TaxRate::default()->first();
    }

    /**
     * Check if any default tax rate exists
     */
    public function hasDefault(): bool
    {
        return TaxRate::default()->exists();
    }

    /**
     * Unset all default tax rates
     */
    public function unsetAllDefaults(): int
    {
        $defaults = TaxRate::where('is_default', true)->get();

        foreach ($defaults as $taxRate) {
            $taxRate->update(['is_default' => false]);
        }

        return $defaults->count();
    }

    /**
     * Check whether a tax rate with the given name already exists
     * for the supplied effective-from date.
     */
    public function existsForDate(string $taxName, string $effectiveFrom): bool
    {
        return TaxRate::query()
            ->where('tax_name', $taxName)
            ->where('effective_from', $effectiveFrom)
            ->exists();
    }
}
