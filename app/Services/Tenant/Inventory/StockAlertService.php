<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\StockAlertType;
use App\Events\Tenant\StockAlertCreated;
use App\Events\Tenant\StockAlertResolved;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockAlert;
use App\Models\Tenant\Store;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAlertService
{
    /**
     * Check and generate alerts for a specific inventory record
     *
     * @param Inventory $inventory
     * @return StockAlert|null
     */
    public function checkAndGenerateAlert(Inventory $inventory): ?StockAlert
    {
        // Check if stock alerts are enabled
        if (!TenantConfiguration::isEnabled('stock_alerts_enabled')) {
            return null;
        }

        return DB::transaction(function () use ($inventory) {
            // Get effective reorder level
            $reorderLevel = $inventory->getEffectiveReorderLevel();
            $currentQuantity = $inventory->quantity_available;

            // Determine alert type
            $alertType = null;
            if ($currentQuantity <= 0) {
                $alertType = StockAlertType::OUT_OF_STOCK;
            } elseif ($currentQuantity <= $reorderLevel) {
                $alertType = StockAlertType::LOW_STOCK;
            }

            // If no alert needed, resolve any existing alerts
            if (!$alertType) {
                $this->autoResolveAlertsForInventory($inventory);
                return null;
            }

            // Check for existing unresolved alert
            $existingAlert = StockAlert::where('store_id', $inventory->store_id)
                ->where('product_id', $inventory->product_id)
                ->where('product_variant_id', $inventory->product_variant_id)
                ->where('alert_type', $alertType)
                ->where('is_resolved', false)
                ->first();

            if ($existingAlert) {
                // Update existing alert
                $existingAlert->update([
                    'current_quantity' => $currentQuantity,
                    'threshold_quantity' => $reorderLevel,
                ]);

                return $existingAlert->fresh();
            }

            // Create new alert
            $alert = StockAlert::create([
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'product_variant_id' => $inventory->product_variant_id,
                'alert_type' => $alertType,
                'current_quantity' => $currentQuantity,
                'threshold_quantity' => $reorderLevel,
                'is_resolved' => false,
            ]);

            Log::info('Stock alert created', [
                'alert_id' => $alert->id,
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'variant_id' => $inventory->product_variant_id,
                'alert_type' => $alertType->value,
                'current_quantity' => $currentQuantity,
                'threshold_quantity' => $reorderLevel,
                'tenant_id' => tenant()->id,
            ]);

            // Dispatch event for notifications
            event(new StockAlertCreated($alert));

            return $alert->fresh(['product', 'productVariant', 'store']);
        });
    }

    /**
     * Auto-resolve alerts when stock is replenished
     *
     * @param Inventory $inventory
     * @return int Number of alerts resolved
     */
    public function autoResolveAlertsForInventory(Inventory $inventory): int
    {
        $resolved = 0;

        $alerts = StockAlert::where('store_id', $inventory->store_id)
            ->where('product_id', $inventory->product_id)
            ->where('product_variant_id', $inventory->product_variant_id)
            ->where('is_resolved', false)
            ->get();

        foreach ($alerts as $alert) {
            if (!$alert->isStillValid()) {
                $alert->resolve('Stock replenished - auto-resolved', 1); // System user
                event(new StockAlertResolved($alert));
                $resolved++;
            }
        }

        if ($resolved > 0) {
            Log::info('Stock alerts auto-resolved', [
                'count' => $resolved,
                'store_id' => $inventory->store_id,
                'product_id' => $inventory->product_id,
                'variant_id' => $inventory->product_variant_id,
                'tenant_id' => tenant()->id,
            ]);
        }

        return $resolved;
    }

    /**
     * Check all inventory in a store for alerts
     *
     * @param int $storeId
     * @return Collection
     */
    public function checkStoreInventory(int $storeId): Collection
    {
        $alerts = collect();

        $inventories = Inventory::byStore($storeId)
            ->with(['product', 'productVariant'])
            ->get();

        foreach ($inventories as $inventory) {
            $alert = $this->checkAndGenerateAlert($inventory);
            if ($alert) {
                $alerts->push($alert);
            }
        }

        Log::info('Store inventory checked for stock alerts', [
            'store_id' => $storeId,
            'inventories_checked' => $inventories->count(),
            'alerts_generated' => $alerts->count(),
            'tenant_id' => tenant()->id,
        ]);

        return $alerts;
    }

    /**
     * Check all stores for alerts (scheduled job)
     *
     * @return array Summary of results
     */
    public function checkAllStores(): array
    {
        $stores = Store::where('is_active', true)->get();
        $totalAlerts = 0;
        $storesChecked = 0;

        foreach ($stores as $store) {
            $alerts = $this->checkStoreInventory($store->id);
            $totalAlerts += $alerts->count();
            $storesChecked++;
        }

        Log::info('All stores checked for stock alerts', [
            'stores_checked' => $storesChecked,
            'total_alerts_generated' => $totalAlerts,
            'tenant_id' => tenant()->id,
        ]);

        return [
            'stores_checked' => $storesChecked,
            'total_alerts_generated' => $totalAlerts,
        ];
    }

    /**
     * Get active alerts with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAlerts(array $filters = []): LengthAwarePaginator
    {
        $query = StockAlert::withDetails();

        // Apply filters
        if (!empty($filters['store_id'])) {
            $query->byStore($filters['store_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->byProduct($filters['product_id']);
        }

        if (!empty($filters['alert_type'])) {
            $query->byType($filters['alert_type']);
        }

        if (isset($filters['is_resolved'])) {
            if ($filters['is_resolved']) {
                $query->resolved();
            } else {
                $query->active();
            }
        } else {
            // Default: show only active alerts
            $query->active();
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->recent()->paginate($perPage);
    }

    /**
     * Manually resolve an alert
     *
     * @param int $alertId
     * @param string|null $notes
     * @param int|null $userId
     * @return StockAlert
     */
    public function resolveAlert(int $alertId, ?string $notes = null, ?int $userId = null): StockAlert
    {
        return DB::transaction(function () use ($alertId, $notes, $userId) {
            $alert = StockAlert::findOrFail($alertId);

            if ($alert->is_resolved) {
                throw new \RuntimeException('Alert is already resolved');
            }

            $alert->resolve($notes, $userId);

            Log::info('Stock alert manually resolved', [
                'alert_id' => $alertId,
                'resolved_by' => $userId ?? Auth::id(),
                'tenant_id' => tenant()->id,
            ]);

            event(new StockAlertResolved($alert));

            return $alert->fresh();
        });
    }

    /**
     * Get alert summary for a store
     *
     * @param int $storeId
     * @return array
     */
    public function getStoreSummary(int $storeId): array
    {
        return [
            'total_active_alerts' => StockAlert::byStore($storeId)->active()->count(),
            'low_stock_count' => StockAlert::byStore($storeId)->active()->lowStock()->count(),
            'out_of_stock_count' => StockAlert::byStore($storeId)->active()->outOfStock()->count(),
            'resolved_today' => StockAlert::byStore($storeId)
                ->resolved()
                ->whereDate('resolved_at', today())
                ->count(),
        ];
    }

    /**
     * Get alerts for dashboard
     *
     * @param int $storeId
     * @param int $limit
     * @return Collection
     */
    public function getDashboardAlerts(int $storeId, int $limit = 10): Collection
    {
        return StockAlert::withDetails()
            ->byStore($storeId)
            ->active()
            ->orderByRaw("FIELD(alert_type, 'out_of_stock', 'low_stock')")
            ->recent()
            ->limit($limit)
            ->get();
    }
}
