<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductCategoryRepository
{
    /**
     * Get all categories with optional filtering
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->applyFilters(ProductCategory::query(), $filters)
            ->ordered()
            ->get();
    }

    /**
     * Get paginated categories
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyFilters(ProductCategory::query(), $filters)
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * Get categories with their products
     */
    public function getAllWithProducts(array $filters = []): Collection
    {
        return $this->applyFilters(ProductCategory::query(), $filters)
            ->with([
                'products' => function ($query) {
                    $query->active()
                        ->select([
                            'id',
                            'category_id',
                            'name',
                            'description',
                            'product_type',
                            'base_selling_price',
                            'stock_status',
                            'primary_image',
                        ]);
                }
            ])
            ->ordered()
            ->get();
    }

    /**
     * Get paginated categories with products
     */
    public function getPaginatedWithProducts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyFilters(ProductCategory::query(), $filters)
            ->with([
                'products' => function ($query) {
                    $query->active()
                        ->select([
                            'id',
                            'category_id',
                            'name',
                            'description',
                            'product_type',
                            'base_selling_price',
                            'stock_status',
                            'primary_image',
                        ]);
                }
            ])
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * Find category by ID
     */
    public function findById(int $id): ?ProductCategory
    {
        return ProductCategory::find($id);
    }

    /**
     * Find category by ID with relationships
     */
    public function findByIdWithRelations(int $id): ?ProductCategory
    {
        return ProductCategory::with([
            'parent',
            'children' => function ($query) {
                $query->active()->ordered();
            },
            'products' => function ($query) {
                $query->active()
                    ->select([
                        'id',
                        'category_id',
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
     * Find category by slug
     */
    public function findBySlug(string $slug): ?ProductCategory
    {
        return ProductCategory::where('slug', $slug)->first();
    }

    /**
     * Create a new category
     */
    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    /**
     * Update a category
     */
    public function update(ProductCategory $category, array $data): bool
    {
        return $category->update($data);
    }

    /**
     * Delete a category
     */
    public function delete(ProductCategory $category): bool
    {
        return $category->delete();
    }

    /**
     * Check if slug exists (excluding given ID)
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = ProductCategory::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get root categories (no parent)
     */
    public function getRootCategories(bool $activeOnly = false): Collection
    {
        $query = ProductCategory::rootCategories()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get children of a category
     */
    public function getChildren(int $parentId, bool $activeOnly = false): Collection
    {
        $query = ProductCategory::where('parent_id', $parentId)->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Check if category has products
     */
    public function hasProducts(ProductCategory $category): bool
    {
        return $category->hasProducts();
    }

    /**
     * Check if category has children
     */
    public function hasChildren(ProductCategory $category): bool
    {
        return $category->hasChildren();
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

        // Filter by parent_id
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null' || $filters['parent_id'] === null) {
                $query->rootCategories();
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Include children
        if (isset($filters['with_children']) && $filters['with_children']) {
            $query->with(['children' => function ($q) use ($filters) {
                $q->ordered();
                if (isset($filters['is_active'])) {
                    $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
                }
            }]);
        }

        // Include parent
        if (isset($filters['with_parent']) && $filters['with_parent']) {
            $query->with('parent');
        }

        return $query;
    }
}
