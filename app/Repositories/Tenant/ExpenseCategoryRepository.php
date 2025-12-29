<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\ExpenseCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryRepository
{
    protected string $cachePrefix = 'expense_categories';
    protected int $cacheTtl = 3600; // 1 hour

    /**
     * Get cache key scoped to tenant
     */
    protected function getCacheKey(string $key): string
    {
        return sprintf('tenant:%s:%s:%s', tenant()->id, $this->cachePrefix, $key);
    }

    /**
     * Clear all category cache for this tenant
     */
    public function clearCache(): void
    {
        Cache::tags([
            'tenant',
            tenant()->id,
            $this->cachePrefix
        ])->flush();
    }

    /**
     * Get all categories with caching
     */
    public function all(bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = $this->getCacheKey($activeOnly ? 'all_active' : 'all');

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($activeOnly) {
                $query = ExpenseCategory::with('parent')->orderedByDisplay();

                if ($activeOnly) {
                    $query->active();
                }

                return $query->get();
            });
    }

    /**
     * Get hierarchical tree structure
     */
    public function tree(bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = $this->getCacheKey($activeOnly ? 'tree_active' : 'tree');

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($activeOnly) {
                $query = ExpenseCategory::with('children')
                    ->rootCategories()
                    ->orderedByDisplay();

                if ($activeOnly) {
                    $query->active();
                }

                return $query->get();
            });
    }

    /**
     * Find category by ID
     */
    public function findById(int $id): ?ExpenseCategory
    {
        $cacheKey = $this->getCacheKey("id:{$id}");

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($id) {
                return ExpenseCategory::with(['parent', 'children'])->find($id);
            });
    }

    /**
     * Find category by code
     */
    public function findByCode(string $code): ?ExpenseCategory
    {
        $cacheKey = $this->getCacheKey("code:{$code}");

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($code) {
                return ExpenseCategory::where('code', $code)->first();
            });
    }

    /**
     * Get children of a category
     */
    public function getChildren(int $parentId, bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = $this->getCacheKey("children:{$parentId}:" . ($activeOnly ? 'active' : 'all'));

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($parentId, $activeOnly) {
                $query = ExpenseCategory::where('parent_id', $parentId)
                    ->orderedByDisplay();

                if ($activeOnly) {
                    $query->active();
                }

                return $query->get();
            });
    }

    /**
     * Create new category
     */
    public function create(array $data): ExpenseCategory
    {
        DB::beginTransaction();

        try {
            $category = ExpenseCategory::create($data);

            DB::commit();
            $this->clearCache();

            return $category->fresh(['parent', 'children']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update category
     */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        DB::beginTransaction();

        try {
            $category->update($data);

            DB::commit();
            $this->clearCache();

            return $category->fresh(['parent', 'children']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete category (with validation)
     */
    public function delete(ExpenseCategory $category): bool
    {
        // Validate deletion is allowed
        if (!$category->is_deletable) {
            throw new \Exception(
                'Cannot delete category: it has associated expenses, active budgets, or child categories.'
            );
        }

        DB::beginTransaction();

        try {
            $deleted = $category->delete();

            DB::commit();
            $this->clearCache();

            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if code is unique (excluding specific ID)
     */
    public function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = ExpenseCategory::where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * Get categories eligible for recurring expenses
     */
    public function getRecurringEligible(bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = $this->getCacheKey('recurring_eligible');

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($activeOnly) {
                $query = ExpenseCategory::recurringEligible()->orderedByDisplay();

                if ($activeOnly) {
                    $query->active();
                }

                return $query->get();
            });
    }

    /**
     * Get root categories only
     */
    public function getRootCategories(bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = $this->getCacheKey($activeOnly ? 'roots_active' : 'roots');

        return Cache::tags(['tenant', tenant()->id, $this->cachePrefix])
            ->remember($cacheKey, $this->cacheTtl, function () use ($activeOnly) {
                $query = ExpenseCategory::rootCategories()->orderedByDisplay();

                if ($activeOnly) {
                    $query->active();
                }

                return $query->get();
            });
    }

    /**
     * Reorder categories
     */
    public function reorder(array $orderMap): void
    {
        // $orderMap format: ['category_id' => display_order, ...]
        DB::beginTransaction();

        try {
            foreach ($orderMap as $categoryId => $order) {
                ExpenseCategory::where('id', $categoryId)
                    ->update(['display_order' => $order]);
            }

            DB::commit();
            $this->clearCache();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
