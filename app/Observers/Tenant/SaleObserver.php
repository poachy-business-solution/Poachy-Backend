<?php

namespace App\Observers\Tenant;

use App\Jobs\Tenant\UpdateDailyAggregatesJob;
use App\Jobs\Tenant\UpdateUniqueCustomerCountJob;
use App\Models\Tenant\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SaleObserver
{
    /**
     * Handle the Sale "creating" event.
     */
    public function creating(Sale $sale): void
    {
        // Set created_by if not set
        if (!$sale->served_by) {
            $sale->served_by = Auth::id();
        }

        // Set sale_date if not set
        if (!$sale->sale_date) {
            $sale->sale_date = now();
        }
    }

    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void
    {
        try {
            $tenantId = tenant()->id;

            // Dispatch job to update aggregates
            UpdateDailyAggregatesJob::dispatch($tenantId, $sale->id);

            // Dispatch job to update unique customer count
            $aggregateDate = $sale->sale_date->toDateString();
            UpdateUniqueCustomerCountJob::dispatch($tenantId, $aggregateDate, $sale->store_id)
                ->delay(now()->addSeconds(5)); // Small delay to let aggregates update first

            // Create audit log
            $this->createAuditLog($sale, 'created', null, $sale->toArray());

            Log::info('Daily aggregate jobs dispatched', [
                'tenant_id' => $tenantId,
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
            ]);
        } catch (\Exception $e) {
            // Don't block sale creation if job dispatch fails
            Log::error('Failed to dispatch daily aggregate jobs', [
                'tenant_id' => tenant()->id ?? 'unknown',
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Handle the Sale "updated" event.
     */
    public function updated(Sale $sale): void
    {
        // Create audit log
        $this->createAuditLog($sale, 'updated', $sale->getOriginal(), $sale->getChanges());
    }

    /**
     * Handle the Sale "deleting" event.
     */
    public function deleting(Sale $sale): void {}

    /**
     * Handle the Sale "deleted" event.
     */
    public function deleted(Sale $sale): void
    {
        // Create audit log
        $this->createAuditLog($sale, 'deleted', $sale->toArray(), null);
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale): void
    {
        // Create audit log
        $this->createAuditLog($sale, 'restored', null, $sale->toArray());
    }

    /**
     * Handle the Sale "forceDeleted" event.
     */
    public function forceDeleted(Sale $sale): void {}

    /**
     * Create audit log entry
     */
    protected function createAuditLog(Sale $sale, string $action, ?array $oldValues, ?array $newValues): void
    {
        // \App\Models\Tenant\AuditLog::create([
        //     'user_id' => Auth::id(),
        //     'user_name' => Auth::user()?->name,
        //     'ip_address' => request()->ip(),
        //     'action' => $action,
        //     'model_type' => Sale::class,
        //     'model_id' => $sale->id,
        //     'old_values' => $oldValues,
        //     'new_values' => $newValues,
        //     'description' => $this->generateDescription($action, $sale),
        //     'tags' => 'sale,transaction',
        // ]);
    }

    /**
     * Generate human-readable description
     */
    protected function generateDescription(string $action, Sale $sale): string
    {
        $user = Auth::user()?->name ?? 'System';

        return match ($action) {
            'created' => "{$user} created sale {$sale->sale_number} for " . number_format($sale->total_amount, 2),
            'updated' => "{$user} updated sale {$sale->sale_number}",
            'deleted' => "{$user} deleted sale {$sale->sale_number}",
            'restored' => "{$user} restored sale {$sale->sale_number}",
            default => "{$user} performed {$action} on sale {$sale->sale_number}",
        };
    }
}
