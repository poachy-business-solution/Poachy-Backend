<?php

namespace App\Services\Central\Sync;

use App\DataTransferObjects\Sync\InventoryCountSyncDTO;
use App\Models\MarketplaceProduct;
use Illuminate\Support\Facades\Log;

class MarketplaceInventoryCountSyncService
{
    /**
     * Update the available_quantity and stock_status on the central MarketplaceProduct
     * matching the given inventory count DTO.
     *
     * If no MarketplaceProduct exists for this tenant/product, the update is skipped —
     * the next full ProductSync will create the central record with current quantities.
     */
    public function updateInventoryCount(InventoryCountSyncDTO $dto): void
    {
        $query = MarketplaceProduct::where('tenant_id', $dto->tenantId)
            ->where('tenant_product_id', $dto->productId);

        if ($dto->entityType === 'variant') {
            $query->where('tenant_variant_id', $dto->variantId);
        } else {
            $query->whereNull('tenant_variant_id');
        }

        $marketplaceProduct = $query->first();

        if (!$marketplaceProduct) {
            Log::warning('MarketplaceProduct not found for inventory count sync — product not yet synced', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'variant_id' => $dto->variantId,
                'entity_type' => $dto->entityType,
            ]);

            return;
        }

        $marketplaceProduct->update([
            'available_quantity' => $dto->availableQuantity,
            'stock_status' => $dto->stockStatus,
            'last_synced_at' => now(),
        ]);

        Log::info('MarketplaceProduct inventory count updated', [
            'tenant_id' => $dto->tenantId,
            'marketplace_product_id' => $marketplaceProduct->id,
            'product_id' => $dto->productId,
            'variant_id' => $dto->variantId,
            'available_quantity' => $dto->availableQuantity,
            'stock_status' => $dto->stockStatus,
        ]);
    }
}
