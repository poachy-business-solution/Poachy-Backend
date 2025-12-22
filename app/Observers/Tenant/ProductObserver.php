<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Product;
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
