<?php

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\ExpiryAlertLevel;
use App\Enums\Tenant\ResolutionAction;
use App\Events\Tenant\ExpiryAlertCreated;
use App\Events\Tenant\ExpiryAlertResolved;
use App\Models\Tenant\ExpiryAlert;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\Store;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpiryAlertService
{
    /**
     * Check and generate alerts for a specific batch
     *
     * @param ProductBatch $batch
     * @return ExpiryAlert|null
     */
    public function checkAndGenerateAlert(ProductBatch $batch): ?ExpiryAlert
    {
        // Check if expiry alerts are enabled
        if (!TenantConfiguration::isEnabled('expiry_alerts_enabled')) {
            return null;
        }

        // Skip if batch has no expiry date or is already expired
        if (!$batch->expiry_date || $batch->is_expired) {
            return null;
        }

        // Skip if batch is depleted
        if ($batch->quantity_remaining_in_base_uom <= 0) {
            $this->autoResolveAlertsForBatch($batch);
            return null;
        }

        return DB::transaction(function () use ($batch) {
            // Get threshold days from configuration
            $warningDays = TenantConfiguration::get('expiry_alerts_warning_days', 60);
            $urgentDays = TenantConfiguration::get('expiry_alerts_urgent_days', 30);

            // Calculate days until expiry
            $daysUntilExpiry = now()->diffInDays($batch->expiry_date, false);

            // Determine alert level
            $alertLevel = null;
            if ($daysUntilExpiry < 0) {
                $alertLevel = ExpiryAlertLevel::EXPIRED;
            } elseif ($daysUntilExpiry <= $urgentDays) {
                $alertLevel = ExpiryAlertLevel::URGENT;
            } elseif ($daysUntilExpiry <= $warningDays) {
                $alertLevel = ExpiryAlertLevel::WARNING;
            }

            // No alert needed
            if (!$alertLevel) {
                return null;
            }

            // Check for existing unresolved alert
            $existingAlert = ExpiryAlert::where('batch_id', $batch->id)
                ->where('is_resolved', false)
                ->first();

            if ($existingAlert) {
                // Update existing alert if level changed or days updated
                if (
                    $existingAlert->alert_level !== $alertLevel ||
                    $existingAlert->days_until_expiry !== $daysUntilExpiry
                ) {

                    $existingAlert->update([
                        'alert_level' => $alertLevel,
                        'days_until_expiry' => $daysUntilExpiry,
                        'alert_date' => now()->toDateString(),
                    ]);

                    Log::info('Expiry alert updated', [
                        'alert_id' => $existingAlert->id,
                        'batch_id' => $batch->id,
                        'alert_level' => $alertLevel->value,
                        'days_until_expiry' => $daysUntilExpiry,
                        'tenant_id' => tenant()->id,
                    ]);
                }

                return $existingAlert->fresh();
            }

            // Create new alert
            $alert = ExpiryAlert::create([
                'batch_id' => $batch->id,
                'alert_level' => $alertLevel,
                'alert_date' => now()->toDateString(),
                'days_until_expiry' => $daysUntilExpiry,
                'is_resolved' => false,
            ]);

            Log::info('Expiry alert created', [
                'alert_id' => $alert->id,
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'alert_level' => $alertLevel->value,
                'days_until_expiry' => $daysUntilExpiry,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'tenant_id' => tenant()->id,
            ]);

            // Dispatch event for notifications
            event(new ExpiryAlertCreated($alert));

            return $alert->fresh(['batch', 'batch.product', 'batch.store']);
        });
    }

    /**
     * Auto-resolve alerts when batch is depleted or sold
     *
     * @param ProductBatch $batch
     * @return int Number of alerts resolved
     */
    public function autoResolveAlertsForBatch(ProductBatch $batch): int
    {
        $resolved = 0;

        $alerts = ExpiryAlert::where('batch_id', $batch->id)
            ->where('is_resolved', false)
            ->get();

        foreach ($alerts as $alert) {
            if (!$alert->isStillValid()) {
                $alert->resolve(
                    ResolutionAction::SOLD,
                    'Batch depleted - auto-resolved',
                    1 // System user
                );
                event(new ExpiryAlertResolved($alert));
                $resolved++;
            }
        }

        if ($resolved > 0) {
            Log::info('Expiry alerts auto-resolved', [
                'count' => $resolved,
                'batch_id' => $batch->id,
                'tenant_id' => tenant()->id,
            ]);
        }

        return $resolved;
    }

    /**
     * Check all batches in a store for expiry alerts
     *
     * @param int $storeId
     * @return Collection
     */
    public function checkStoreBatches(int $storeId): Collection
    {
        $alerts = collect();

        $batches = ProductBatch::byStore($storeId)
            ->available()
            ->whereNotNull('expiry_date')
            ->with(['product', 'productVariant'])
            ->get();

        foreach ($batches as $batch) {
            $alert = $this->checkAndGenerateAlert($batch);
            if ($alert) {
                $alerts->push($alert);
            }
        }

        Log::info('Store batches checked for expiry alerts', [
            'store_id' => $storeId,
            'batches_checked' => $batches->count(),
            'alerts_generated' => $alerts->count(),
            'tenant_id' => tenant()->id,
        ]);

        return $alerts;
    }

    /**
     * Check all stores for expiry alerts (scheduled job)
     *
     * @return array Summary of results
     */
    public function checkAllStores(): array
    {
        $stores = Store::where('is_active', true)->get();
        $totalAlerts = 0;
        $storesChecked = 0;

        foreach ($stores as $store) {
            $alerts = $this->checkStoreBatches($store->id);
            $totalAlerts += $alerts->count();
            $storesChecked++;
        }

        Log::info('All stores checked for expiry alerts', [
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
        $query = ExpiryAlert::withDetails();

        // Apply filters
        if (!empty($filters['store_id'])) {
            $query->byStore($filters['store_id']);
        }

        if (!empty($filters['alert_level'])) {
            $query->byLevel($filters['alert_level']);
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

        // Sort by priority
        $query->byPriority();

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    /**
     * Manually resolve an alert
     *
     * @param int $alertId
     * @param ResolutionAction $action
     * @param string|null $notes
     * @param int|null $userId
     * @return ExpiryAlert
     */
    public function resolveAlert(
        int $alertId,
        ResolutionAction $action,
        ?string $notes = null,
        ?int $userId = null
    ): ExpiryAlert {
        return DB::transaction(function () use ($alertId, $action, $notes, $userId) {
            $alert = ExpiryAlert::findOrFail($alertId);

            if ($alert->is_resolved) {
                throw new \RuntimeException('Alert is already resolved');
            }

            $alert->resolve($action, $notes, $userId);

            Log::info('Expiry alert manually resolved', [
                'alert_id' => $alertId,
                'resolution_action' => $action->value,
                'resolved_by' => $userId ?? Auth::id(),
                'tenant_id' => tenant()->id,
            ]);

            event(new ExpiryAlertResolved($alert));

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
            'total_active_alerts' => ExpiryAlert::byStore($storeId)->active()->count(),
            'expired_count' => ExpiryAlert::byStore($storeId)->active()->expired()->count(),
            'urgent_count' => ExpiryAlert::byStore($storeId)->active()->urgent()->count(),
            'warning_count' => ExpiryAlert::byStore($storeId)->active()->warning()->count(),
            'resolved_today' => ExpiryAlert::byStore($storeId)
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
        return ExpiryAlert::withDetails()
            ->byStore($storeId)
            ->active()
            ->byPriority()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark expired batches
     *
     * @param int|null $storeId
     * @return int Count of batches marked
     */
    public function markExpiredBatches(?int $storeId = null): int
    {
        $batchService = app(ProductBatchService::class);
        return $batchService->markExpiredBatches($storeId);
    }
}
