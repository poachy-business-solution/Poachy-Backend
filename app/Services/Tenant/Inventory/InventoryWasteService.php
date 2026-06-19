<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\ExpiryAlertLevel;
use App\Enums\Tenant\ResolutionAction;
use App\Enums\Tenant\WasteApprovalStatus;
use App\Enums\Tenant\WasteType;
use App\Events\Tenant\WasteApprovalRequested;
use App\Events\Tenant\WasteApproved;
use App\Events\Tenant\WasteRejected;
use App\Models\Tenant\ExpiryAlert;
use App\Models\Tenant\InventoryWaste;
use App\Models\Tenant\ProductBatch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryWasteService
{
    public function __construct(
        private InventoryMovementService $inventoryMovementService,
        private ProductBatchService $productBatchService,
        private ExpiryAlertService $expiryAlertService
    ) {}

    /**
     * Record inventory waste
     *
     * @param array $data
     * @return InventoryWaste
     */
    public function recordWaste(array $data): InventoryWaste
    {
        return DB::transaction(function () use ($data) {
            // Get cost per base UOM
            $costPerBaseUom = $this->getCostPerBaseUom(
                $data['product_id'],
                $data['store_id'],
                $data['batch_id'] ?? null
            );

            // Calculate total loss
            $totalLoss = $data['quantity_wasted'] * $costPerBaseUom;

            // Create waste record
            $waste = InventoryWaste::create([
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'batch_id' => $data['batch_id'] ?? null,
                'waste_type' => $data['waste_type'],
                'quantity_wasted' => $data['quantity_wasted'],
                'cost_per_base_uom' => $costPerBaseUom,
                'total_loss' => $totalLoss,
                'waste_date' => $data['waste_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'approval_status' => WasteApprovalStatus::PENDING,
                'reported_by' => Auth::id(),
            ]);

            Log::info('Inventory waste recorded', [
                'waste_id' => $waste->id,
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'batch_id' => $data['batch_id'] ?? null,
                'waste_type' => $data['waste_type'],
                'quantity_wasted' => $data['quantity_wasted'],
                'total_loss' => $totalLoss,
                'tenant_id' => tenant()->id,
            ]);

            // Dispatch event for approval notification
            event(new WasteApprovalRequested($waste));

            return $waste->fresh(['product', 'batch', 'store', 'reportedBy']);
        });
    }

    /**
     * Approve waste record
     *
     * @param int $wasteId
     * @param int $approverId
     * @return InventoryWaste
     */
    public function approveWaste(int $wasteId, int $approverId): InventoryWaste
    {
        return DB::transaction(function () use ($wasteId, $approverId) {
            $waste = InventoryWaste::with(['product', 'batch'])->findOrFail($wasteId);

            if (!$waste->can_be_approved) {
                throw new \RuntimeException(
                    "Waste record cannot be approved. Current status: {$waste->approval_status->label()}"
                );
            }

            // Approve the waste record
            $waste->approve($approverId);

            // Process inventory deduction
            $this->processWasteInventoryDeduction($waste);

            Log::info('Inventory waste approved', [
                'waste_id' => $wasteId,
                'approved_by' => $approverId,
                'tenant_id' => tenant()->id,
            ]);

            // Dispatch event
            event(new WasteApproved($waste));

            return $waste->fresh();
        });
    }

    /**
     * Reject waste record
     *
     * @param int $wasteId
     * @param int $rejectorId
     * @param string|null $reason
     * @return InventoryWaste
     */
    public function rejectWaste(int $wasteId, int $rejectorId, ?string $reason = null): InventoryWaste
    {
        return DB::transaction(function () use ($wasteId, $rejectorId, $reason) {
            $waste = InventoryWaste::findOrFail($wasteId);

            if (!$waste->can_be_rejected) {
                throw new \RuntimeException(
                    "Waste record cannot be rejected. Current status: {$waste->approval_status->label()}"
                );
            }

            $waste->reject($rejectorId, $reason);

            Log::info('Inventory waste rejected', [
                'waste_id' => $wasteId,
                'rejected_by' => $rejectorId,
                'reason' => $reason,
                'tenant_id' => tenant()->id,
            ]);

            // Dispatch event
            event(new WasteRejected($waste));

            return $waste->fresh();
        });
    }

    /**
     * Process inventory deduction after waste approval
     *
     * @param InventoryWaste $waste
     * @return void
     */
    private function processWasteInventoryDeduction(InventoryWaste $waste): void
    {
        // If batch is specified, deplete batch first
        if ($waste->batch_id) {
            try {
                // Deplete the specific batch
                $batch = ProductBatch::findOrFail($waste->batch_id);

                if ($batch->quantity_remaining_in_base_uom >= $waste->quantity_wasted) {
                    $batch->decrement('quantity_remaining_in_base_uom', $waste->quantity_wasted);

                    Log::info('Batch depleted for waste', [
                        'batch_id' => $waste->batch_id,
                        'quantity_depleted' => $waste->quantity_wasted,
                    ]);

                    // Auto-resolve expiry alert if batch is depleted or if waste type is expired
                    if (
                        $batch->quantity_remaining_in_base_uom <= 0 ||
                        $waste->waste_type === WasteType::EXPIRED
                    ) {
                        $this->expiryAlertService->autoResolveAlertsForBatch($batch);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to deplete batch for waste', [
                    'waste_id' => $waste->id,
                    'batch_id' => $waste->batch_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Record inventory movement
        $movementType = $waste->waste_type->relatedMovementType();

        $this->inventoryMovementService->recordMovement([
            'store_id' => $waste->store_id,
            'product_id' => $waste->product_id,
            'variant_id' => null,
            'movement_type' => $movementType,
            'uom_id' => $waste->product->base_uom_id,
            'quantity' => -abs($waste->quantity_wasted), // Always negative
            'unit_cost' => $waste->cost_per_base_uom,
            'reference_type' => InventoryWaste::class,
            'reference_id' => $waste->id,
            'notes' => "Waste: {$waste->waste_type->label()} - {$waste->reason}",
        ]);

        Log::info('Inventory movement recorded for waste', [
            'waste_id' => $waste->id,
            'movement_type' => $movementType->value,
            'quantity' => $waste->quantity_wasted,
        ]);
    }

    /**
     * Get cost per base UOM for waste calculation
     *
     * @param int $productId
     * @param int $storeId
     * @param int|null $batchId
     * @return float
     */
    private function getCostPerBaseUom(int $productId, int $storeId, ?int $batchId = null): float
    {
        // If batch specified, use batch cost
        if ($batchId) {
            $batch = ProductBatch::findOrFail($batchId);
            return $batch->cost_per_base_uom;
        }

        // Otherwise, use latest batch cost or calculate average
        $latestBatch = ProductBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('quantity_remaining_in_base_uom', '>', 0)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestBatch) {
            return $latestBatch->cost_per_base_uom;
        }

        // Fallback: Use product selling price as estimate
        $product = \App\Models\Tenant\Product::findOrFail($productId);
        return $product->base_selling_price * 0.7; // Assume 70% of selling price as cost
    }

    /**
     * Get waste records with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getWasteRecords(array $filters = []): LengthAwarePaginator
    {
        $query = InventoryWaste::withDetails();

        // Apply filters
        if (!empty($filters['store_id'])) {
            $query->byStore($filters['store_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->byProduct($filters['product_id']);
        }

        if (!empty($filters['waste_type'])) {
            $query->byType($filters['waste_type']);
        }

        if (!empty($filters['approval_status'])) {
            match ($filters['approval_status']) {
                'pending' => $query->pending(),
                'approved' => $query->approved(),
                'rejected' => $query->rejected(),
                default => null,
            };
        }

        if (!empty($filters['from_date']) || !empty($filters['to_date'])) {
            $query->byDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->recent()->paginate($perPage);
    }

    /**
     * Get waste summary for a store
     *
     * @param int $storeId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array
     */
    public function getStoreSummary(int $storeId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = InventoryWaste::byStore($storeId);

        if ($fromDate || $toDate) {
            $query->byDateRange($fromDate, $toDate);
        }

        $approved = (clone $query)->approved();

        return [
            'total_waste_records' => $query->count(),
            'pending_approvals' => (clone $query)->pending()->count(),
            'approved_count' => $approved->count(),
            'rejected_count' => (clone $query)->rejected()->count(),
            'total_financial_loss' => $approved->sum('total_loss'),
            'total_quantity_wasted' => $approved->sum('quantity_wasted'),
            'waste_by_type' => $this->getWasteByType($storeId, $fromDate, $toDate),
        ];
    }

    /**
     * Get waste breakdown by type
     *
     * @param int $storeId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array
     */
    private function getWasteByType(int $storeId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = InventoryWaste::byStore($storeId)->approved();

        if ($fromDate || $toDate) {
            $query->byDateRange($fromDate, $toDate);
        }

        return $query->select('waste_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_loss) as total_loss'))
            ->groupBy('waste_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->waste_type->value => [
                        'count' => $item->count,
                        'total_loss' => $item->total_loss,
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * Update waste record (only if pending)
     *
     * @param int $wasteId
     * @param array $data
     * @return InventoryWaste
     */
    public function updateWaste(int $wasteId, array $data): InventoryWaste
    {
        return DB::transaction(function () use ($wasteId, $data) {
            $waste = InventoryWaste::findOrFail($wasteId);

            if (!$waste->is_pending) {
                throw new \RuntimeException('Only pending waste records can be updated');
            }

            // Recalculate if quantity changed
            if (isset($data['quantity_wasted']) && $data['quantity_wasted'] != $waste->quantity_wasted) {
                $data['total_loss'] = $data['quantity_wasted'] * $waste->cost_per_base_uom;
            }

            $waste->update($data);

            Log::info('Inventory waste updated', [
                'waste_id' => $wasteId,
                'tenant_id' => tenant()->id,
            ]);

            return $waste->fresh();
        });
    }
}
