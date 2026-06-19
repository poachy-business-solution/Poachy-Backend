<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductUom;
use Illuminate\Support\Facades\Log;

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
        // If base UOM changed, this might affect pricing calculations
        if ($productUom->wasChanged('is_base_uom') || $productUom->wasChanged('conversion_to_base')) {
            // TODO: Trigger recalculation of inventory values
            // TODO: Sync to marketplace if product is online
        }
    }

    /**
     * Handle the ProductUom "deleted" event.
     */
    public function deleted(ProductUom $productUom): void {}
}
