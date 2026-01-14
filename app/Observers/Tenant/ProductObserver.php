<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPriceHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     */
    public function creating(Product $product): void
    {
        // Ensure UUID is set
        if (empty($product->uuid)) {
            $product->uuid = \Illuminate\Support\Str::uuid()->toString();
        }
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        // TODO: Dispatch ProductCreated event for sync queue
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        // TODO: If is_available_online changed, trigger sync
        if ($product->wasChanged('is_available_online')) {
            // Dispatch event to sync with marketplace
        }

        // TODO: If inventory-related fields changed, trigger sync
        if ($product->wasChanged(['base_selling_price', 'online_price', 'stock_status'])) {
            // Dispatch event to update marketplace
        }
    }

    public function updating(Product $product): void
    {
        // Check if selling price changed
        if ($product->isDirty('base_selling_price')) {
            $oldPrice = $product->getOriginal('base_selling_price');
            $newPrice = $product->base_selling_price;

            // Only record if price actually changed
            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'base_uom_id' => $product->base_uom_id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1, // Default to system user if no auth
                    'effective_from' => now(),
                ]);

                Log::info('Product price change recorded', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => Auth::id(),
                    'tenant_id' => tenant()->id,
                ]);
            }
        }

        // Check if online price changed
        if ($product->isDirty('online_price')) {
            $oldPrice = $product->getOriginal('online_price');
            $newPrice = $product->online_price;

            // Only record if price actually changed
            if ($oldPrice != $newPrice) {
                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'base_uom_id' => $product->base_uom_id,
                    'old_selling_price' => $oldPrice,
                    'new_selling_price' => $newPrice,
                    'change_reason' => 'manual',
                    'changed_by' => Auth::id() ?? 1, // Default to system user if no auth
                    'effective_from' => now(),
                ]);

                Log::info('Product price change recorded', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => Auth::id(),
                    'tenant_id' => tenant()->id,
                ]);
            }
        }
    }


    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        // TODO: If was available online, remove from marketplace
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void {}

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void {}
}
