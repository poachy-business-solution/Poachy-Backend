<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductBrand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductBrandObserver
{
    public function creating(ProductBrand $brand): void
    {
        Log::info('Creating product brand', [
            'tenant_id' => tenant()->id,
            'name' => $brand->name,
        ]);
    }

    public function created(ProductBrand $brand): void
    {
        $this->clearCache();
    }

    public function updating(ProductBrand $brand): void
    {
        $changes = $brand->getDirty();

        if (!empty($changes)) {
            Log::info('Updating product brand', [
                'tenant_id' => tenant()->id,
                'brand_id' => $brand->id,
                'changes' => $changes,
            ]);
        }
    }

    public function updated(ProductBrand $brand): void
    {
        $this->clearCache();
    }

    public function deleting(ProductBrand $brand): void
    {
        $this->clearCache();
    }

    public function deleted(ProductBrand $brand): void
    {
        $this->clearCache();
    }

    public function restored(ProductBrand $brand): void
    {
        $this->clearCache();
    }

    public function forceDeleted(ProductBrand $brand): void
    {
        $this->clearCache();
    }

    // Clear all brand-related cache
    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'product_brands'])->flush();
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear brand cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
