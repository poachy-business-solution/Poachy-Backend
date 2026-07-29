<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;

class ProductUomObserver
{
    /**
     * Handle the ProductUom "created" event.
     */
    public function created(ProductUom $productUom): void {}

    /**
     * Handle the ProductUom "updated" event.
     */
    public function updated(ProductUom $productUom): void
    {
        // conversion_to_base changes alone have no effect on the marketplace payload
        // (ProductSyncDTO only carries which UOM is the base, not its conversion factor),
        // and there's no cached inventory valuation anywhere to invalidate — it's always
        // computed live from ProductBatch rows (see ProductBatchService::getInventoryValuation()).
        // Only a genuine is_base_uom promotion needs to propagate anywhere.
        if ($productUom->wasChanged('is_base_uom') && $productUom->is_base_uom) {
            // A normal Eloquent update (not a query-builder mass update) so this fires
            // ProductObserver::updated() → handleMarketplaceSync() normally, same as any
            // other marketplace-relevant field edit.
            Product::find($productUom->product_id)?->update(['base_uom_id' => $productUom->uom_id]);
        }
    }

    /**
     * Handle the ProductUom "deleted" event.
     */
    public function deleted(ProductUom $productUom): void {}
}
