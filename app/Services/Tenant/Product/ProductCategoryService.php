<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\ProductCategory;
use App\Repositories\Tenant\ProductCategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCategoryService
{
    public function __construct(
        protected ProductCategoryRepository $repository
    ) {}

    /**
     * Get all categories with optional filtering
     */
    public function getAllCategories(array $filters = [], bool $paginate = false, int $perPage = 15): Collection|LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey('all', $filters, $paginate, $perPage);

        return Cache::tags(['tenant', tenant()->id, 'product_categories'])
            ->remember($cacheKey, 3600, function () use ($filters, $paginate, $perPage) {
                if ($paginate) {
                    return $this->repository->getPaginated($filters, $perPage);
                }

                return $this->repository->getAll($filters);
            });
    }

    /**
     * Get all categories with their products
     */
    public function getAllCategoriesWithProducts(array $filters = [], bool $paginate = false, int $perPage = 15): Collection|LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey('with_products', $filters, $paginate, $perPage);

        return Cache::tags(['tenant', tenant()->id, 'product_categories', 'products'])
            ->remember($cacheKey, 1800, function () use ($filters, $paginate, $perPage) {
                if ($paginate) {
                    return $this->repository->getPaginatedWithProducts($filters, $perPage);
                }

                return $this->repository->getAllWithProducts($filters);
            });
    }

    /**
     * Get category by ID
     */
    public function getCategoryById(int $id, bool $withRelations = false): ?ProductCategory
    {
        $cacheKey = $this->getCacheKey('single', ['id' => $id, 'with_relations' => $withRelations]);

        return Cache::tags(['tenant', tenant()->id, 'product_categories'])
            ->remember($cacheKey, 3600, function () use ($id, $withRelations) {
                if ($withRelations) {
                    return $this->repository->findByIdWithRelations($id);
                }

                return $this->repository->findById($id);
            });
    }

    /**
     * Get category by slug
     */
    public function getCategoryBySlug(string $slug): ?ProductCategory
    {
        $cacheKey = $this->getCacheKey('slug', ['slug' => $slug]);

        return Cache::tags(['tenant', tenant()->id, 'product_categories'])
            ->remember($cacheKey, 3600, function () use ($slug) {
                return $this->repository->findBySlug($slug);
            });
    }

    /**
     * Create a new category
     */
    public function createCategory(array $data): ProductCategory
    {
        return DB::transaction(function () use ($data) {
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            } else {
                // Validate slug uniqueness
                if ($this->repository->slugExists($data['slug'])) {
                    throw new \InvalidArgumentException('Category slug already exists');
                }
            }

            // Validate parent category exists if provided
            if (!empty($data['parent_id'])) {
                $parent = $this->repository->findById($data['parent_id']);
                if (!$parent) {
                    throw new \InvalidArgumentException('Parent category not found');
                }
            }

            $category = $this->repository->create($data);

            // Clear cache
            $this->clearCache();

            return $category;
        });
    }

    /**
     * Update a category
     */
    public function updateCategory(int $id, array $data): ProductCategory
    {
        return DB::transaction(function () use ($id, $data) {
            $category = $this->repository->findById($id);

            if (!$category) {
                throw new \InvalidArgumentException('Category not found');
            }

            // Validate slug uniqueness if changed
            if (!empty($data['slug']) && $data['slug'] !== $category->slug) {
                if ($this->repository->slugExists($data['slug'], $id)) {
                    throw new \InvalidArgumentException('Category slug already exists');
                }
            }

            // Validate parent category
            if (isset($data['parent_id'])) {
                // Prevent self-referencing
                if ($data['parent_id'] == $id) {
                    throw new \InvalidArgumentException('Category cannot be its own parent');
                }

                // Validate parent exists
                if ($data['parent_id'] !== null) {
                    $parent = $this->repository->findById($data['parent_id']);
                    if (!$parent) {
                        throw new \InvalidArgumentException('Parent category not found');
                    }

                    // Prevent circular reference (parent cannot be a child of this category)
                    if ($this->wouldCreateCircularReference($id, $data['parent_id'])) {
                        throw new \InvalidArgumentException('Cannot create circular category reference');
                    }
                }
            }

            $this->repository->update($category, $data);

            // Clear cache
            $this->clearCache();

            return $category->fresh();
        });
    }

    /**
     * Activate a category
     */
    public function activateCategory(int $id): ProductCategory
    {
        return DB::transaction(function () use ($id) {
            $category = $this->repository->findById($id);

            if (!$category) {
                throw new \InvalidArgumentException('Category not found');
            }

            $this->repository->update($category, ['is_active' => true]);

            // Clear cache
            $this->clearCache();

            return $category->fresh();
        });
    }

    /**
     * Deactivate a category
     */
    public function deactivateCategory(int $id): ProductCategory
    {
        return DB::connection('tenant')->transaction(function () use ($id) {
            $category = $this->repository->findById($id);

            if (!$category) {
                throw new \InvalidArgumentException('Category not found');
            }

            $this->repository->update($category, ['is_active' => false]);

            // Clear cache
            $this->clearCache();

            return $category->fresh();
        });
    }

    /**
     * Delete a category
     */
    public function deleteCategory(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $category = $this->repository->findById($id);

            if (!$category) {
                throw new \InvalidArgumentException('Category not found');
            }

            // Check if category has products
            if ($this->repository->hasProducts($category)) {
                throw new \InvalidArgumentException('Cannot delete category with associated products');
            }

            // Check if category has children
            if ($this->repository->hasChildren($category)) {
                throw new \InvalidArgumentException('Cannot delete category with child categories');
            }

            $deleted = $this->repository->delete($category);

            // Clear cache
            $this->clearCache();

            return $deleted;
        });
    }

    /**
     * Get root categories
     */
    public function getRootCategories(bool $activeOnly = true): Collection
    {
        $cacheKey = $this->getCacheKey('root', ['active_only' => $activeOnly]);

        return Cache::tags(['tenant', tenant()->id, 'product_categories'])
            ->remember($cacheKey, 3600, function () use ($activeOnly) {
                return $this->repository->getRootCategories($activeOnly);
            });
    }

    /**
     * Get children of a category
     */
    public function getCategoryChildren(int $parentId, bool $activeOnly = true): Collection
    {
        $cacheKey = $this->getCacheKey('children', ['parent_id' => $parentId, 'active_only' => $activeOnly]);

        return Cache::tags(['tenant', tenant()->id, 'product_categories'])
            ->remember($cacheKey, 3600, function () use ($parentId, $activeOnly) {
                return $this->repository->getChildren($parentId, $activeOnly);
            });
    }

    /**
     * Generate a unique slug from name
     */
    protected function generateUniqueSlug(string $name, int $attempt = 0): string
    {
        $slug = Str::slug($name);

        if ($attempt > 0) {
            $slug .= '-' . $attempt;
        }

        if ($this->repository->slugExists($slug)) {
            return $this->generateUniqueSlug($name, $attempt + 1);
        }

        return $slug;
    }

    /**
     * Check if setting parent_id would create circular reference
     */
    protected function wouldCreateCircularReference(int $categoryId, int $newParentId): bool
    {
        $parent = $this->repository->findById($newParentId);

        while ($parent) {
            if ($parent->id === $categoryId) {
                return true;
            }

            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $type, array $params = []): string
    {
        return sprintf(
            'category:%s:%s',
            $type,
            md5(json_encode($params))
        );
    }

    /**
     * Clear all category cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'product_categories'])->flush();
    }
}
