<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\StoreProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StoreProductObserver
{
    /**
     * Get cache tags for store products
     */
    protected function getCacheTags(StoreProduct $storeProduct): array
    {
        return [
            'tenant:' . tenant()->id,
            'store_products',
            'store:' . $storeProduct->store_id,
        ];
    }

    /**
     * Handle the StoreProduct "creating" event.
     */
    public function creating(StoreProduct $storeProduct): void {}

    /**
     * Handle the StoreProduct "created" event.
     */
    public function created(StoreProduct $storeProduct): void
    {
        // Clear cache
        Cache::tags($this->getCacheTags($storeProduct))->flush();

        // TODO: Dispatch event for marketplace sync if product is available online
        // if ($storeProduct->product->is_available_online && $storeProduct->is_available) {
        //     event(new ProductAvailabilityChanged($storeProduct));
        // }
    }

    /**
     * Handle the StoreProduct "updating" event.
     */
    public function updating(StoreProduct $storeProduct): void {}

    /**
     * Handle the StoreProduct "updated" event.
     */
    public function updated(StoreProduct $storeProduct): void
    {
        // Clear cache
        Cache::tags($this->getCacheTags($storeProduct))->flush();

        $changes = $storeProduct->getChanges();

        // Log successful update
        Log::info('Store product updated', [
            'tenant_id' => tenant()->id,
            'store_product_id' => $storeProduct->id,
            'changes' => $changes,
        ]);

        // Handle availability changes
        if (isset($changes['is_available'])) {
            $this->handleAvailabilityChange($storeProduct, $changes['is_available']);
        }

        // Handle price changes
        if (isset($changes['store_selling_price'])) {
            $this->handlePriceChange($storeProduct, $changes['store_selling_price']);
        }
    }

    /**
     * Handle the StoreProduct "deleting" event.
     */
    public function deleting(StoreProduct $storeProduct): void {}

    /**
     * Handle the StoreProduct "deleted" event.
     */
    public function deleted(StoreProduct $storeProduct): void
    {
        // Clear cache
        Cache::tags($this->getCacheTags($storeProduct))->flush();

        // TODO: Dispatch event for marketplace sync
        // if ($storeProduct->product->is_available_online) {
        //     event(new ProductRemovedFromStore($storeProduct));
        // }
    }

    /**
     * Handle the StoreProduct "restored" event.
     */
    public function restored(StoreProduct $storeProduct): void
    {
        Cache::tags($this->getCacheTags($storeProduct))->flush();
    }

    /**
     * Handle the StoreProduct "force deleted" event.
     */
    public function forceDeleted(StoreProduct $storeProduct): void
    {
        Cache::tags($this->getCacheTags($storeProduct))->flush();
    }

    /**
     * Handle availability changes
     */
    protected function handleAvailabilityChange(StoreProduct $storeProduct, bool $newAvailability): void
    {
        $status = $newAvailability ? 'available' : 'unavailable';

        // TODO: Dispatch availability change event for marketplace sync
        // if ($storeProduct->product->is_available_online) {
        //     event(new ProductAvailabilityChanged($storeProduct));
        // }
    }

    /**
     * Handle price changes
     */
    protected function handlePriceChange(StoreProduct $storeProduct, ?float $newPrice): void
    {
        $oldPrice = $storeProduct->getOriginal('store_selling_price');

        if ($newPrice === null) {
            Log::info('Store price override removed, using base price', [
                'tenant_id' => tenant()->id,
                'store_product_id' => $storeProduct->id,
                'old_price' => $oldPrice,
                'base_price' => $storeProduct->product->base_selling_price,
            ]);
        } else {
            Log::info('Store price changed', [
                'tenant_id' => tenant()->id,
                'store_product_id' => $storeProduct->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'difference' => $newPrice - ($oldPrice ?? $storeProduct->product->base_selling_price),
            ]);
        }

        // TODO: Dispatch price change event for marketplace sync
        // if ($storeProduct->product->is_available_online) {
        //     event(new ProductPriceChanged($storeProduct));
        // }
    }
}
