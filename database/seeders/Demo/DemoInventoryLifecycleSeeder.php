<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\WasteType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\Store;
use App\Models\Tenant\TenantConfiguration;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\User;
use App\Services\Tenant\Inventory\ExpiryAlertService;
use App\Services\Tenant\Inventory\InventoryWasteService;
use App\Services\Tenant\Inventory\StockReservationService;
use App\Services\Tenant\Inventory\StockTransferService;
use Illuminate\Database\Seeder;

class DemoInventoryLifecycleSeeder extends Seeder
{
    public function run(
        StockTransferService $stockTransferService,
        InventoryWasteService $inventoryWasteService,
        StockReservationService $stockReservationService,
        ExpiryAlertService $expiryAlertService,
    ): void {
        $this->seedExpiryAlerts($expiryAlertService);
        $this->seedStockTransfers($stockTransferService);
        $this->seedWaste($inventoryWasteService);
        $this->seedReservations($stockReservationService);

        $this->command->info('✓ Inventory lifecycle: expiry alerts swept, transfers, waste, reservations seeded');
    }

    /**
     * expiry_alerts_enabled defaults to disabled (no seeder writes it), and the
     * WARNING/URGENT alert path only runs via an explicit sweep (ExpiryAlertObserver
     * only auto-fires on an EXPIRED transition) — without this, expiry_alerts stays
     * empty despite Phase 3's near-expiry produce batches.
     */
    protected function seedExpiryAlerts(ExpiryAlertService $expiryAlertService): void
    {
        TenantConfiguration::set('expiry_alerts_enabled', true);
        TenantConfiguration::set('expiry_alerts_warning_days', 14);
        TenantConfiguration::set('expiry_alerts_urgent_days', 5);

        $expiryAlertService->checkAllStores();
    }

    protected function seedStockTransfers(StockTransferService $stockTransferService): void
    {
        $cbd = Store::mainStore()->firstOrFail();
        $westlands = Store::branches()->firstOrFail();
        $pcs = UnitOfMeasure::where('code', 'pcs')->firstOrFail();

        $coke = Product::where('name', 'Coca-Cola 500ml')->firstOrFail();
        $rice = Product::where('name', 'Pishori Rice 2kg')->firstOrFail();

        $completed = $stockTransferService->createTransfer([
            'from_store_id' => $cbd->id,
            'to_store_id' => $westlands->id,
            'notes' => 'Restocking Westlands ahead of the weekend.',
            'items' => [
                ['product_id' => $coke->id, 'uom_id' => $pcs->id, 'quantity' => 10],
            ],
        ]);
        $stockTransferService->approveTransfer($completed->id);
        $stockTransferService->sendTransfer($completed->id);
        $stockTransferService->receiveTransfer($completed->id, [
            $completed->items->first()->id => 10,
        ]);

        $inTransit = $stockTransferService->createTransfer([
            'from_store_id' => $cbd->id,
            'to_store_id' => $westlands->id,
            'notes' => 'Extra rice for Westlands — in transit.',
            'items' => [
                ['product_id' => $rice->id, 'uom_id' => $pcs->id, 'quantity' => 8],
            ],
        ]);
        $stockTransferService->approveTransfer($inTransit->id);
        $stockTransferService->sendTransfer($inTransit->id);
    }

    protected function seedWaste(InventoryWasteService $inventoryWasteService): void
    {
        $cbd = Store::mainStore()->firstOrFail();
        $manager = User::where('email', DemoStaffSeeder::ACCOUNTS[1]['email'])->firstOrFail();

        $tomatoes = Product::where('name', 'Fresh Tomatoes')->firstOrFail();
        $bananas = Product::where('name', 'Fresh Bananas')->firstOrFail();

        $tomatoBatch = ProductBatch::where('store_id', $cbd->id)->where('product_id', $tomatoes->id)->first();
        $bananaBatch = ProductBatch::where('store_id', $cbd->id)->where('product_id', $bananas->id)->first();

        $approved = $inventoryWasteService->recordWaste([
            'store_id' => $cbd->id,
            'product_id' => $tomatoes->id,
            'batch_id' => $tomatoBatch?->id,
            'waste_type' => WasteType::DAMAGED->value,
            'quantity_wasted' => 3,
            'reason' => 'Crushed during shelf restocking.',
        ]);
        $inventoryWasteService->approveWaste($approved->id, $manager->id);

        $rejected = $inventoryWasteService->recordWaste([
            'store_id' => $cbd->id,
            'product_id' => $bananas->id,
            'batch_id' => $bananaBatch?->id,
            'waste_type' => WasteType::QUALITY_ISSUE->value,
            'quantity_wasted' => 2,
            'reason' => 'Cashier flagged as overripe — manager judged them still sellable.',
        ]);
        $inventoryWasteService->rejectWaste($rejected->id, $manager->id, 'Still within sellable condition — declined.');
    }

    protected function seedReservations(StockReservationService $stockReservationService): void
    {
        $cbd = Store::mainStore()->firstOrFail();
        $pcs = UnitOfMeasure::where('code', 'pcs')->firstOrFail();
        $kettle = Product::where('name', 'Ramtons Electric Kettle')->firstOrFail();
        $microwave = Product::where('name', 'LG Microwave 20L')->firstOrFail();
        $clock = Product::where('name', 'Wall Clock')->firstOrFail();

        // Left active — as if a marketplace checkout is still in progress.
        $stockReservationService->reserveStock('MarketplaceOrder', 90001, [
            ['product_id' => $kettle->id, 'variant_id' => null, 'quantity' => 1, 'uom_id' => $pcs->id, 'store_id' => $cbd->id],
        ]);

        // Confirmed — order completed, reservation converted to a real movement.
        $toConfirm = $stockReservationService->reserveStock('MarketplaceOrder', 90002, [
            ['product_id' => $microwave->id, 'variant_id' => null, 'quantity' => 1, 'uom_id' => $pcs->id, 'store_id' => $cbd->id],
        ]);
        $stockReservationService->confirmReservation($toConfirm->first()->id);

        // Released — customer abandoned checkout.
        $toRelease = $stockReservationService->reserveStock('MarketplaceOrder', 90003, [
            ['product_id' => $clock->id, 'variant_id' => null, 'quantity' => 2, 'uom_id' => $pcs->id, 'store_id' => $cbd->id],
        ]);
        $stockReservationService->releaseReservation($toRelease->first()->id, 'Checkout abandoned by customer.');
    }
}
