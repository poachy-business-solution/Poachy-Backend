<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\ProductBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductBrandRepository
{
    /**
     * Get all brands with optional filtering
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->applyFilters(ProductBrand::query(), $filters)
            ->ordered()
            ->get();
    }

    /**
     * Get paginated brands
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyFilters(ProductBrand::query(), $filters)
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * Get brand by ID
     */
    public function findById(int $id): ?ProductBrand
    {
        return ProductBrand::find($id);
    }

    /**
     * Get brand by ID with products
     */
    public function findByIdWithProducts(int $id): ?ProductBrand
    {
        return ProductBrand::with([
            'products' => function ($query) {
                $query->active()
                    ->select([
                        'id',
                        'brand_id',
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
     * Get brand by slug
     */
    public function findBySlug(string $slug): ?ProductBrand
    {
        return ProductBrand::where('slug', $slug)->first();
    }

    /**
     * Create a new brand
     */
    public function create(array $data): ProductBrand
    {
        return ProductBrand::create($data);
    }

    /**
     * Update a brand
     */
    public function update(ProductBrand $brand, array $data): bool
    {
        return $brand->update($data);
    }

    /**
     * Delete a brand
     */
    public function delete(ProductBrand $brand): bool
    {
        return $brand->delete();
    }

    /**
     * Check if slug exists (excluding given ID)
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = ProductBrand::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if brand has products
     */
    public function hasProducts(ProductBrand $brand): bool
    {
        return $brand->hasProducts();
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by featured status
        if (isset($filters['is_featured'])) {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query;
    }
}
