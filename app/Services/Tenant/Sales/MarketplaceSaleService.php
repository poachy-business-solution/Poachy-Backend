<?php

namespace App\Services\Tenant\Sales;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Events\Tenant\MarketplaceSaleFulfillmentSyncRequested;
use App\Models\MarketplaceOrder;
use App\Models\Tenant\MarketplaceSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceSaleService
{
    /** @var array<int, string> */
    private const RELATIONS = ['store', 'items.product:id,name', 'items.productVariant:id,variant_name'];

    /**
     * List marketplace sales for the tenant, optionally filtered by store/status.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MarketplaceSale::with(self::RELATIONS)
            ->orderBy('created_at', 'desc');

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['fulfillment_status'])) {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }

        // "open" = still to hand over (the Marketplace tab's default list)
        if (! empty($filters['open'])) {
            $query->whereNotIn('fulfillment_status', [MarketplaceFulfillmentStatus::DELIVERED, MarketplaceFulfillmentStatus::CANCELLED]);
        }

        $page = $query->paginate($perPage);
        $this->attachCentralOrders($page->getCollection());

        return $page;
    }

    public function getById(int $id): MarketplaceSale
    {
        $sale = MarketplaceSale::with(self::RELATIONS)->findOrFail($id);
        $this->attachCentralOrders(collect([$sale]));

        return $sale;
    }

    /**
     * The buyer, delivery address and fee live on the central order: load them for these sales in
     * one query (this shop's orders only) as the `centralOrder` relation.
     *
     * @param  Collection<int, MarketplaceSale>  $sales
     */
    public function attachCentralOrders(Collection $sales): void
    {
        $ids = $sales->pluck('central_order_id')->filter()->unique()->values();
        $orders = $ids->isEmpty() ? collect() : MarketplaceOrder::on('central')
            ->with(['customer.user:id,name', 'deliveryAddress', 'delivery'])
            ->where('tenant_id', (string) tenant()->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $sales->each(fn (MarketplaceSale $sale) => $sale->setRelation('centralOrder', $orders->get($sale->central_order_id)));
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

            $fresh = $sale->fresh(self::RELATIONS);
            $this->attachCentralOrders(collect([$fresh]));

            return $fresh;
        });
    }
}
