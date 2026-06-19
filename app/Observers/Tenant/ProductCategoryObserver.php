<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductCategoryObserver
{
    /**
     * Handle the ProductCategory "creating" event.
     */
    public function creating(ProductCategory $category): void
    {
        // Log the creation
        Log::info('Creating product category', [
            'tenant_id' => tenant()->id,
            'name' => $category->name,
            'parent_id' => $category->parent_id,
        ]);
    }

    /**
     * Handle the ProductCategory "created" event.
     */
    public function created(ProductCategory $category): void
    {
        // Clear category cache
        $this->clearCache();

        Log::info('Product category created', [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'name' => $category->name,
        ]);
    }

    /**
     * Handle the ProductCategory "updating" event.
     */
    public function updating(ProductCategory $category): void
    {
        // Log changes
        $changes = $category->getDirty();

        if (!empty($changes)) {
            Log::info('Updating product category', [
                'tenant_id' => tenant()->id,
                'category_id' => $category->id,
                'changes' => $changes,
            ]);
        }
    }

    /**
     * Handle the ProductCategory "updated" event.
     */
    public function updated(ProductCategory $category): void
    {
        // Clear category cache
        $this->clearCache();

        Log::info('Product category updated', [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'name' => $category->name,
        ]);
    }

    /**
     * Handle the ProductCategory "deleting" event.
     */
    public function deleting(ProductCategory $category): void
    {
        Log::warning('Deleting product category', [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'name' => $category->name,
            'has_products' => $category->hasProducts(),
            'has_children' => $category->hasChildren(),
        ]);
    }

    /**
     * Handle the ProductCategory "deleted" event.
     */
    public function deleted(ProductCategory $category): void
    {
        // Clear category cache
        $this->clearCache();

        Log::info('Product category deleted', [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'name' => $category->name,
        ]);
    }

    /**
     * Handle the ProductCategory "restored" event.
     */
    public function restored(ProductCategory $category): void
    {
        // Clear category cache
        $this->clearCache();

        Log::info('Product category restored', [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'name' => $category->name,
        ]);
    }

    /**
     * Handle the ProductCategory "force deleted" event.
     */
    public function forceDeleted(ProductCategory $category): void
    {
        // Clear category cache
        $this->clearCache();

        Log::warning('Product category force deleted', [
            'tenant_id' => tenant()->id,
            'category_id' => $category->id,
            'name' => $category->name,
        ]);
    }

    /**
     * Clear all category-related cache
     */
    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'product_categories'])->flush();
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear category cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
