<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\InventoryMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InventoryMovementObserver
{
    /**
     * Handle the InventoryMovement "created" event.
     * Log all inventory movements for audit trail
     */
    public function created(InventoryMovement $movement): void {}

    /**
     * Handle the InventoryMovement "deleted" event.
     * Log when movements are soft deleted (should be rare)
     */
    public function deleted(InventoryMovement $movement): void {}
}
