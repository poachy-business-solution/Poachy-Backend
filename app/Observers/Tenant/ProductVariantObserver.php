<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductVariant;

class ProductVariantObserver
{
    /**
     * Handle the ProductVariant "created" event.
     */
    public function created(ProductVariant $variant): void
    {
        // TODO: Sync to marketplace if product is available online
    }

    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $variant): void
    {
        // TODO: Sync changes to marketplace
        if ($variant->wasChanged(['variant_price', 'stock_status', 'is_active'])) {
            // Trigger marketplace sync
        }
    }

    /**
     * Handle the ProductVariant "deleted" event.
     */
    public function deleted(ProductVariant $variant): void
    {
        // TODO: Remove from marketplace if was available online
    }

    /**
     * Handle the ProductVariant "restored" event.
     */
    public function restored(ProductVariant $variant): void {}
}
