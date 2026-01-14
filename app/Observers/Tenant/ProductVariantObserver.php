<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductPriceHistory;
use App\Models\Tenant\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

    public function updating(ProductVariant $variant): void
    {
        // Check if variant price changed
        if ($variant->isDirty('variant_price')) {
            $oldPrice = $variant->getOriginal('variant_price');
            $newPrice = $variant->variant_price;

            // Only record if price actually changed
            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'base_uom_id' => $variant->uom_id,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1,
                    'effective_from' => now(),
                ]);
            }
        }

        // Check if variant online price changed
        if ($variant->isDirty('online_price')) {
            $oldPrice = $variant->getOriginal('online_price');
            $newPrice = $variant->online_price;

            // Only record if price actually changed
            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'base_uom_id' => $variant->uom_id,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1,
                    'effective_from' => now(),
                ]);
            }
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
