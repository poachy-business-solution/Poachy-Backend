<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupplierObserver
{
    public function creating(Supplier $supplier): void {}

    public function created(Supplier $supplier): void
    {
        $this->clearCache();
    }

    public function updating(Supplier $supplier): void
    {
        $changes = $supplier->getDirty();

        if (!empty($changes)) {
            Log::info('Updating supplier', [
                'tenant_id' => tenant()->id,
                'supplier_id' => $supplier->id,
                'changes' => $changes,
            ]);
        }
    }

    public function updated(Supplier $supplier): void
    {
        $this->clearCache();
    }

    public function deleting(Supplier $supplier): void {}

    public function deleted(Supplier $supplier): void
    {
        $this->clearCache();
    }

    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'suppliers'])->flush();
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear supplier cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
