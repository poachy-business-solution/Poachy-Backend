<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\SaleItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SaleItemObserver
{
    /**
     * Handle the SaleItem "creating" event.
     */
    public function creating(SaleItem $saleItem): void {}

    /**
     * Handle the SaleItem "created" event.
     */
    public function created(SaleItem $saleItem): void
    {
        // Create audit log
        $this->createAuditLog($saleItem, 'created', null, $saleItem->toArray());
    }

    /**
     * Handle the SaleItem "updated" event.
     */
    public function updated(SaleItem $saleItem): void
    {
        // Create audit log
        $this->createAuditLog($saleItem, 'updated', $saleItem->getOriginal(), $saleItem->getChanges());
    }

    /**
     * Handle the SaleItem "deleted" event.
     */
    public function deleted(SaleItem $saleItem): void
    {
        // Create audit log
        $this->createAuditLog($saleItem, 'deleted', $saleItem->toArray(), null);
    }

    /**
     * Handle the SaleItem "restored" event.
     */
    public function restored(SaleItem $saleItem): void
    {
        // Create audit log
        $this->createAuditLog($saleItem, 'restored', null, $saleItem->toArray());
    }

    /**
     * Create audit log entry
     */
    protected function createAuditLog(SaleItem $saleItem, string $action, ?array $oldValues, ?array $newValues): void
    {
        // Load sale for context
        $sale = $saleItem->sale;

        // \App\Models\Tenant\AuditLog::create([
        //     'user_id' => Auth::id(),
        //     'user_name' => Auth::user()?->name,
        //     'ip_address' => request()->ip(),
        //     'action' => $action,
        //     'model_type' => SaleItem::class,
        //     'model_id' => $saleItem->id,
        //     'old_values' => $oldValues,
        //     'new_values' => $newValues,
        //     'description' => $this->generateDescription($action, $saleItem, $sale),
        //     'tags' => 'sale_item,transaction',
        // ]);
    }

    /**
     * Generate human-readable description
     */
    protected function generateDescription(string $action, SaleItem $saleItem, $sale): string
    {
        $user = Auth::user()?->name ?? 'System';
        $saleNumber = $sale?->sale_number ?? "Sale #{$saleItem->sale_id}";
        $productName = $saleItem->product?->name ?? "Product #{$saleItem->product_id}";

        return match ($action) {
            'created' => "{$user} added {$saleItem->quantity} x {$productName} to {$saleNumber}",
            'updated' => "{$user} updated item in {$saleNumber}",
            'deleted' => "{$user} removed {$productName} from {$saleNumber}",
            'restored' => "{$user} restored item in {$saleNumber}",
            default => "{$user} performed {$action} on sale item",
        };
    }
}
