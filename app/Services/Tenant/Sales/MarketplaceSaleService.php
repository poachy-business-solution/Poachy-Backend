<?php

namespace App\Services\Tenant\Sales;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Events\Tenant\MarketplaceSaleFulfillmentSyncRequested;
use App\Models\Tenant\MarketplaceSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceSaleService
{
    /**
     * List marketplace sales for the tenant, optionally filtered by store/status.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MarketplaceSale::with(['store', 'items'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['fulfillment_status'])) {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }

        return $query->paginate($perPage);
    }

    public function getById(int $id): MarketplaceSale
    {
        return MarketplaceSale::with(['store', 'items'])->findOrFail($id);
    }

    /**
     * Update a marketplace sale's fulfillment status and sync the change to central.
     *
     * @param  array  $deliveryData  Optional delivery-tracking fields (courier_company,
     *                               courier_name, courier_phone, tracking_number,
     *                               tracking_url, delivery_proof_type, delivery_proof_data,
     *                               received_by_name, received_by_phone). Never persisted
     *                               tenant-side — passed through the sync payload to
     *                               central, where MarketplaceOrderDelivery is the real
     *                               store of record.
     */
    public function updateFulfillmentStatus(
        MarketplaceSale $sale,
        MarketplaceFulfillmentStatus $newStatus,
        array $deliveryData = [],
        ?string $notes = null
    ): MarketplaceSale {
        return DB::transaction(function () use ($sale, $newStatus, $deliveryData, $notes) {
            if (! $sale->fulfillment_status->canTransitionTo($newStatus)) {
                throw new \RuntimeException(
                    "Cannot transition fulfillment status from '{$sale->fulfillment_status->value}' to '{$newStatus->value}'."
                );
            }

            $sale->update([
                'fulfillment_status' => $newStatus,
                'notes' => $notes ?? $sale->notes,
            ]);

            Log::info('Marketplace sale fulfillment status updated', [
                'tenant_id' => tenant()->id,
                'sale_id' => $sale->id,
                'central_order_id' => $sale->central_order_id,
                'old_status' => $sale->getOriginal('fulfillment_status'),
                'new_status' => $newStatus->value,
            ]);

            $dto = MarketplaceFulfillmentSyncDTO::fromModel($sale->fresh(), $deliveryData, $notes);

            event(new MarketplaceSaleFulfillmentSyncRequested($dto));

            return $sale->fresh(['store', 'items']);
        });
    }
}
