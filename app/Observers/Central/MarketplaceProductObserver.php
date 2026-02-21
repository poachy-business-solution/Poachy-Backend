<?php

namespace App\Observers\Central;

use App\Jobs\Central\UpdateTenantProductMetricsJob;
use App\Models\MarketplaceProduct;
use Illuminate\Support\Facades\Log;

class MarketplaceProductObserver
{
    public function created(MarketplaceProduct $product): void
    {
        $this->dispatchMetricsJob($product);

        Log::info('MarketplaceProduct created', [
            'product_id' => $product->id,
            'tenant_id'  => $product->tenant_id,
            'is_active'  => $product->is_active,
        ]);
    }

    public function updated(MarketplaceProduct $product): void
    {
        // Only recalculate metrics if is_active changed
        if ($product->wasChanged('is_active')) {
            $this->dispatchMetricsJob($product);
        }

        Log::info('MarketplaceProduct updated', [
            'product_id' => $product->id,
            'tenant_id'  => $product->tenant_id,
            'changes'    => $product->getChanges(),
        ]);
    }

    public function deleted(MarketplaceProduct $product): void
    {
        $this->dispatchMetricsJob($product);

        Log::info('MarketplaceProduct soft-deleted', [
            'product_id' => $product->id,
            'tenant_id'  => $product->tenant_id,
        ]);
    }

    public function restored(MarketplaceProduct $product): void
    {
        $this->dispatchMetricsJob($product);

        Log::info('MarketplaceProduct restored', [
            'product_id' => $product->id,
            'tenant_id'  => $product->tenant_id,
        ]);
    }

    protected function dispatchMetricsJob(MarketplaceProduct $product): void
    {
        UpdateTenantProductMetricsJob::dispatch($product->tenant_id)
            ->onQueue('sync-normal')
            ->afterCommit();
    }
}
