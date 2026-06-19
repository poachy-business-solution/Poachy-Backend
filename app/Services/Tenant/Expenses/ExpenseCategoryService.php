<?php

namespace App\Services\Tenant\Expenses;

use App\Models\Tenant\ExpenseCategory;
use App\Repositories\Tenant\ExpenseCategoryRepository;
use Illuminate\Support\Str;

class ExpenseCategoryService
{
    public function __construct(
        protected ExpenseCategoryRepository $repository
    ) {}

    /**
     * Get all categories
     */
    public function getAllCategories(bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->all($activeOnly);
    }

    /**
     * Get hierarchical tree
     */
    public function getCategoryTree(bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->tree($activeOnly);
    }

    /**
     * Get category by ID
     */
    public function getCategoryById(int $id): ?ExpenseCategory
    {
        return $this->repository->findById($id);
    }

    /**
     * Get children of a category
     */
    public function getCategoryChildren(int $parentId, bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getChildren($parentId, $activeOnly);
    }

    /**
     * Create new category
     */
    public function createCategory(array $data): ExpenseCategory
    {
        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateCode($data['name']);
        } else {
            $data['code'] = strtoupper($data['code']);
        }

        // Validate circular reference
        if (!empty($data['parent_id'])) {
            $this->validateParentId($data['parent_id']);
        }

        return $this->repository->create($data);
    }

    /**
     * Update category
     */
    public function updateCategory(int $id, array $data): ExpenseCategory
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new \Exception('Expense category not found.');
        }

        // Uppercase code if provided
        if (!empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        // Validate parent change doesn't create circular reference
        if (isset($data['parent_id']) && $data['parent_id'] !== $category->parent_id) {
            if ($category->wouldCreateCircularReference($data['parent_id'])) {
                throw new \Exception('Cannot set parent: this would create a circular reference.');
            }
        }

        return $this->repository->update($category, $data);
    }

    /**
     * Delete category
     */
    public function deleteCategory(int $id): bool
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new \Exception('Expense category not found.');
        }

        return $this->repository->delete($category);
    }

    /**
     * Toggle category active status
     */
    public function toggleActiveStatus(int $id): ExpenseCategory
    {
        $category = $this->repository->findById($id);

        if (!$category) {
            throw new \Exception('Expense category not found.');
        }

        return $this->repository->update($category, [
            'is_active' => !$category->is_active
        ]);
    }

    /**
     * Get recurring-eligible categories
     */
    public function getRecurringEligibleCategories(bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getRecurringEligible($activeOnly);
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(array $orderMap): void
    {
        $this->repository->reorder($orderMap);
    }

    /**
     * ============================================
     * HELPER METHODS
     * ============================================
     */

    /**
     * Generate unique category code from name
     */
    protected function generateCode(string $name): string
    {
        $baseCode = strtoupper(Str::slug($name, '_'));
        $code = $baseCode;
        $counter = 1;

        while (!$this->repository->isCodeUnique($code)) {
            $code = $baseCode . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Validate parent ID exists and is active
     */
    protected function validateParentId(int $parentId): void
    {
        $parent = $this->repository->findById($parentId);

        if (!$parent) {
            throw new \Exception('Parent category not found.');
        }

        if (!$parent->is_active) {
            throw new \Exception('Cannot assign inactive category as parent.');
        }
    }
}
